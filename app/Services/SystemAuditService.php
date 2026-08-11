<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SystemEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centraliza los registros de auditoría de los sistemas externos que
 * exponen su propia tabla de auditoría/bitácora (no todos los sistemas
 * tienen una). Cada sistema tiene un esquema de auditoría distinto, así
 * que a diferencia del resto del IAM (que usa mapeo de columnas genérico
 * configurable desde /sistemas) acá el mapeo va fijo en código, verificado
 * contra el esquema real de cada uno:
 *
 *  - bolipost (SIOP): eventos_auditoria (tabla enorme, ~1.3M filas) + tabla
 *    de catálogo "auditoria" (tipo de evento) + users (autor).
 *  - sigec: "vitacora" (~475k filas) con id_usuario -> users. El texto de
 *    accion_realizada trae HTML embebido (<b>...</b>) que se limpia acá.
 *  - apiweb: "site_page_change_logs" (bitácora de cambios del CMS), ya trae
 *    el autor denormalizado (created_by_name/email), no necesita join.
 *
 * Además de los 3 sistemas externos, se suma "iam" como cuarta fuente: la
 * propia auditoría del IAM (tabla audit_logs). OJO: esa tabla y el código
 * que escribe en ella NO viven en este proyecto — ya existen en la base de
 * datos compartida, alimentados por otro despliegue de esta misma app. Acá
 * solo se LEE (ver AuditLog), no se duplica el logueo para no chocar con
 * lo que ya la está escribiendo. Al ser una tabla chica, sí soporta
 * paginación real con COUNT(*) sin problema de rendimiento (a diferencia
 * de las tablas remotas de millones de filas).
 */
class SystemAuditService
{
    private const REMOTE_SOURCES = ['bolipost', 'sigec', 'apiweb'];

    private const IAM_KEY = 'iam';

    /**
     * Sistemas con auditoría disponible: el propio IAM (siempre disponible)
     * más los sistemas externos que están activos y con conexión configurada.
     */
    public function availableSystems(): array
    {
        $remote = SystemEntry::whereIn('key', self::REMOTE_SOURCES)
            ->where('status', 'active')
            ->connectable()
            ->orderBy('name')
            ->get(['key', 'name'])
            ->map(fn ($s) => ['key' => $s->key, 'name' => $s->name])
            ->values()
            ->all();

        return [['key' => self::IAM_KEY, 'name' => 'IAM AGBC'], ...$remote];
    }

    /**
     * Lista de usuarios reales de un sistema, para poblar el <select> de
     * filtro por usuario en /auditoria (usa el email como valor: es lo que
     * se manda de vuelta a los fetchX() como filtro por LIKE). Se trae de
     * la tabla de usuarios real del sistema (no de la tabla de auditoría):
     * son cientos de filas como mucho, no millones, así que no hace falta
     * capar ni paginar.
     */
    public function actorsFor(string $systemKey): array
    {
        if ($systemKey === self::IAM_KEY) {
            return AuditLog::query()
                ->whereNotNull('actor_email')
                ->select('actor_name', 'actor_email')
                ->distinct()
                ->orderBy('actor_name')
                ->get()
                ->map(fn ($row) => ['value' => $row->actor_email, 'label' => "{$row->actor_name} ({$row->actor_email})"])
                ->all();
        }

        $system = SystemEntry::where('key', $systemKey)->where('status', 'active')->connectable()->first();

        if (! $system) {
            return [];
        }

        try {
            $conn = $system->remoteConnectionName();

            $rows = match ($systemKey) {
                'bolipost' => DB::connection($conn)->table('users')->select('name', 'email')->whereNotNull('email')->orderBy('name')->get(),
                'sigec' => DB::connection($conn)->table('users')->select('nombre as name', 'email')->whereNotNull('email')->orderBy('nombre')->get(),
                'apiweb' => DB::connection($conn)->table('users')->select('name', 'email')->whereNotNull('email')->orderBy('name')->get(),
                default => collect(),
            };

            return $rows
                ->map(fn ($row) => ['value' => $row->email, 'label' => "{$row->name} ({$row->email})"])
                ->all();
        } catch (Throwable $e) {
            Log::warning("Fallo trayendo usuarios de [{$systemKey}] para el filtro de auditoría: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Trae una página de registros de auditoría. Si $systemKey es null,
     * junta los más recientes de cada sistema disponible (capados, sin
     * paginación real: cruzar conexiones de motores distintos —Postgres y
     * MySQL— no permite un UNION ni un COUNT(*) combinado eficiente). Con
     * un $systemKey puntual sí pagina de verdad sobre esa conexión con
     * simplePaginate (evita el COUNT(*) sobre tablas de cientos de miles/
     * millones de filas, que sería lento en cada búsqueda).
     */
    public function search(?string $systemKey, ?string $query, ?string $actor = null, int $page = 1, int $perPage = 30): array
    {
        if ($systemKey) {
            return $this->searchOneSystem($systemKey, $query, $actor, $page, $perPage);
        }

        $combined = [];
        foreach ([self::IAM_KEY, ...self::REMOTE_SOURCES] as $key) {
            $rows = $this->fetchRows($key, $query, $actor, offset: 0, limit: 40);
            $combined = array_merge($combined, $rows);
        }

        usort($combined, fn ($a, $b) => strcmp($b['occurred_at'] ?? '', $a['occurred_at'] ?? ''));

        return [
            'items' => array_slice($combined, 0, $perPage),
            'has_more' => false,
            'capped' => true,
        ];
    }

    private function searchOneSystem(string $systemKey, ?string $query, ?string $actor, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        // Se pide una fila de más para saber si hay siguiente página sin
        // hacer un COUNT(*) aparte.
        $rows = $this->fetchRows($systemKey, $query, $actor, $offset, $perPage + 1);

        $hasMore = count($rows) > $perPage;

        return [
            'items' => array_slice($rows, 0, $perPage),
            'has_more' => $hasMore,
            'capped' => false,
        ];
    }

    private function fetchRows(string $systemKey, ?string $query, ?string $actor, int $offset, int $limit): array
    {
        if ($systemKey === self::IAM_KEY) {
            try {
                return $this->fetchIam($query, $actor, $offset, $limit);
            } catch (Throwable $e) {
                Log::warning("Fallo trayendo auditoría interna del IAM: {$e->getMessage()}");

                return [];
            }
        }

        $system = SystemEntry::where('key', $systemKey)->where('status', 'active')->connectable()->first();

        if (! $system) {
            return [];
        }

        try {
            $conn = $system->remoteConnectionName();

            $rows = match ($systemKey) {
                'bolipost' => $this->fetchBolipost($conn, $query, $actor, $offset, $limit),
                'sigec' => $this->fetchSigec($conn, $query, $actor, $offset, $limit),
                'apiweb' => $this->fetchApiweb($conn, $query, $actor, $offset, $limit),
                default => [],
            };

            return array_map(fn ($row) => $this->normalize($row, $system), $rows);
        } catch (Throwable $e) {
            Log::warning("Fallo trayendo auditoría de [{$systemKey}]: {$e->getMessage()}");

            return [];
        }
    }

    private function fetchBolipost(string $conn, ?string $query, ?string $actor, int $offset, int $limit): array
    {
        $builder = DB::connection($conn)
            ->table('eventos_auditoria as e')
            ->join('auditoria as a', 'a.id', '=', 'e.auditoria_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->select([
                'e.id',
                'e.created_at as occurred_at',
                'u.name as actor_name',
                'u.email as actor_email',
                'a.nombre_evento as category',
                'e.codigo as description',
                DB::raw('NULL as ip_address'),
            ]);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('e.codigo', 'like', "%{$query}%")
                    ->orWhere('u.name', 'like', "%{$query}%")
                    ->orWhere('u.email', 'like', "%{$query}%")
                    ->orWhere('a.nombre_evento', 'like', "%{$query}%");
            });
        }

        if ($actor) {
            $builder->where(function ($q) use ($actor) {
                $q->where('u.name', 'like', "%{$actor}%")
                    ->orWhere('u.email', 'like', "%{$actor}%");
            });
        }

        return $builder->orderByDesc('e.id')->offset($offset)->limit($limit)->get()->all();
    }

    private function fetchSigec(string $conn, ?string $query, ?string $actor, int $offset, int $limit): array
    {
        $builder = DB::connection($conn)
            ->table('vitacora as v')
            ->leftJoin('users as u', 'u.id', '=', 'v.id_usuario')
            ->select([
                'v.id',
                'v.fecha_hora as occurred_at',
                'u.nombre as actor_name',
                'u.email as actor_email',
                DB::raw('NULL as category'),
                'v.accion_realizada as description',
                'v.ip_usuario as ip_address',
            ]);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('v.accion_realizada', 'like', "%{$query}%")
                    ->orWhere('u.nombre', 'like', "%{$query}%")
                    ->orWhere('u.email', 'like', "%{$query}%")
                    ->orWhere('v.ip_usuario', 'like', "%{$query}%");
            });
        }

        if ($actor) {
            $builder->where(function ($q) use ($actor) {
                $q->where('u.nombre', 'like', "%{$actor}%")
                    ->orWhere('u.email', 'like', "%{$actor}%");
            });
        }

        return $builder->orderByDesc('v.id')->offset($offset)->limit($limit)->get()->all();
    }

    private function fetchApiweb(string $conn, ?string $query, ?string $actor, int $offset, int $limit): array
    {
        $builder = DB::connection($conn)
            ->table('site_page_change_logs')
            ->select([
                'id',
                'created_at as occurred_at',
                'created_by_name as actor_name',
                'created_by_email as actor_email',
                'action as category',
                'summary as description',
                DB::raw('NULL as ip_address'),
            ]);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('summary', 'like', "%{$query}%")
                    ->orWhere('created_by_name', 'like', "%{$query}%")
                    ->orWhere('created_by_email', 'like', "%{$query}%")
                    ->orWhere('action', 'like', "%{$query}%")
                    ->orWhere('section_key', 'like', "%{$query}%");
            });
        }

        if ($actor) {
            $builder->where(function ($q) use ($actor) {
                $q->where('created_by_name', 'like', "%{$actor}%")
                    ->orWhere('created_by_email', 'like', "%{$actor}%");
            });
        }

        return $builder->orderByDesc('id')->offset($offset)->limit($limit)->get()->all();
    }

    private function fetchIam(?string $query, ?string $actor, int $offset, int $limit): array
    {
        $builder = AuditLog::query();

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('description', 'like', "%{$query}%")
                    ->orWhere('actor_name', 'like', "%{$query}%")
                    ->orWhere('actor_email', 'like', "%{$query}%")
                    ->orWhere('action', 'like', "%{$query}%");
            });
        }

        if ($actor) {
            $builder->where(function ($q) use ($actor) {
                $q->where('actor_name', 'like', "%{$actor}%")
                    ->orWhere('actor_email', 'like', "%{$actor}%");
            });
        }

        return $builder->orderByDesc('id')->offset($offset)->limit($limit)->get()
            ->map(fn (AuditLog $log) => [
                'system_key' => self::IAM_KEY,
                'system_name' => 'IAM AGBC',
                'occurred_at' => $log->created_at?->toDateTimeString(),
                'actor_name' => $log->actor_name,
                'actor_email' => $log->actor_email,
                'category' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
            ])
            ->all();
    }

    private function normalize(object $row, SystemEntry $system): array
    {
        return [
            'system_key' => $system->key,
            'system_name' => $system->name,
            'occurred_at' => $row->occurred_at,
            'actor_name' => $row->actor_name ?: null,
            'actor_email' => $row->actor_email ?: null,
            'category' => $row->category ?: null,
            'description' => $this->cleanText($row->description ?? ''),
            'ip_address' => $row->ip_address ?: null,
        ];
    }

    private function cleanText(?string $value): string
    {
        if (! $value) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES));
    }
}
