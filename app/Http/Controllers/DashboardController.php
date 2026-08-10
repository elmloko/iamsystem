<?php

namespace App\Http\Controllers;

use App\Models\AccessRequestItem;
use App\Models\Person;
use App\Models\SystemAccount;
use App\Models\SystemEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $systems = SystemEntry::orderBy('name')->get(['id', 'key', 'name', 'status', 'db_host', 'db_database']);
        $accountBreakdown = $this->liveAccountBreakdown();

        return Inertia::render('Dashboard', [
            'stats' => [
                'systemsActive' => $systems->where('status', 'active')->count(),
                'systemsPending' => $systems->where('status', 'pending')->count(),
                'people' => Person::count(),
                'accounts' => SystemAccount::count(),
                'liveAccounts' => array_sum(array_column($accountBreakdown, 'count')),
                'pendingRequests' => AccessRequestItem::where('status', 'pending')->count(),
                'approvedRequests' => AccessRequestItem::where('status', 'approved')->count(),
                'rejectedRequests' => AccessRequestItem::where('status', 'rejected')->count(),
                'activeAccounts' => array_sum(array_column($accountBreakdown, 'active')),
                'inactiveAccounts' => array_sum(array_column($accountBreakdown, 'inactive')),
            ],
            'topSystems' => collect($accountBreakdown)
                ->sortByDesc('count')
                ->take(6)
                ->values(),
            'mostRequestedSystems' => AccessRequestItem::selectRaw('system_id, count(*) as total')
                ->with('system:id,name')
                ->groupBy('system_id')
                ->orderByDesc('total')
                ->take(6)
                ->get()
                ->map(fn ($row) => [
                    'key' => $row->system_id,
                    'name' => $row->system?->name ?? '(sistema eliminado)',
                    'count' => (int) $row->total,
                ]),
            'recentActivity' => AccessRequestItem::with(['accessRequest', 'system', 'decidedBy'])
                ->whereIn('status', ['approved', 'rejected'])
                ->whereNotNull('decided_at')
                ->orderByDesc('decided_at')
                ->take(6)
                ->get()
                ->map(fn (AccessRequestItem $item) => [
                    'id' => $item->id,
                    'person_name' => $item->accessRequest?->name,
                    'system_name' => $item->system?->name ?? '(sistema eliminado)',
                    'status' => $item->status,
                    'decided_by_name' => $item->decidedBy?->name,
                    'decided_at' => $item->decided_at,
                ]),
            'systems' => $systems,
        ]);
    }

    /**
     * Valores de texto que se consideran "activo" cuando el sistema no
     * define una lista propia en active_values.
     */
    private const DEFAULT_ACTIVE_TEXT_VALUES = [
        'activo', 'active', 'habilitado', 'enabled', 'si', 'sí', 'true', '1', 'a',
    ];

    /**
     * Cuentas reales por sistema (COUNT por conexión, sin traer datos — rápido
     * incluso en red), más el desglose activo/de baja cuando el sistema tiene
     * columna de estado mapeada. Se cachea porque igual son ~16 idas y
     * vueltas por red en cada carga del dashboard.
     */
    private function liveAccountBreakdown(): array
    {
        return Cache::remember('dashboard.live_account_breakdown', now()->addMinutes(15), function () {
            // Modelos completos (no la selección recortada de columnas de
            // arriba): remoteConnectionName() necesita db_driver/db_host/
            // db_username/db_password, que ahí no se cargan.
            $systems = SystemEntry::where('status', 'active')->connectable()->get();
            $breakdown = [];

            foreach ($systems as $system) {
                try {
                    $table = DB::connection($system->remoteConnectionName())
                        ->table($system->users_table ?: 'users');

                    $count = $table->count();
                    [$active, $inactive] = $this->activeInactiveSplit($system, $count);

                    $breakdown[] = [
                        'key' => $system->key,
                        'name' => $system->name,
                        'count' => $count,
                        'active' => $active,
                        'inactive' => $inactive,
                    ];
                } catch (Throwable $e) {
                    Log::warning("Fallo contando cuentas en [{$system->key}]: {$e->getMessage()}");
                }
            }

            return $breakdown;
        });
    }

    /**
     * [activas, de_baja] para un sistema, calculado con COUNT en SQL (sin
     * traer filas). Si el sistema no tiene columna de estado mapeada,
     * devuelve [0, 0] — no se suma al total de activas/de baja global.
     */
    private function activeInactiveSplit(SystemEntry $system, int $totalCount): array
    {
        if (! $system->active_column || ! $system->active_type) {
            return [0, 0];
        }

        $table = DB::connection($system->remoteConnectionName())->table($system->users_table ?: 'users');

        try {
            return match ($system->active_type) {
                'soft_delete' => (function () use ($table, $system, $totalCount) {
                    $active = (clone $table)->whereNull($system->active_column)->count();

                    return [$active, $totalCount - $active];
                })(),
                'boolean' => (function () use ($table, $system, $totalCount) {
                    $active = (clone $table)->where($system->active_column, true)->count();

                    return [$active, $totalCount - $active];
                })(),
                'text' => (function () use ($table, $system, $totalCount) {
                    $values = $system->active_values
                        ? json_decode($system->active_values, true)
                        : self::DEFAULT_ACTIVE_TEXT_VALUES;

                    $active = (clone $table)
                        ->whereRaw('LOWER(TRIM(' . $system->active_column . ')) IN (' . implode(',', array_fill(0, count($values), '?')) . ')', $values)
                        ->count();

                    return [$active, $totalCount - $active];
                })(),
                default => [0, 0],
            };
        } catch (Throwable $e) {
            Log::warning("Fallo calculando activos/de baja en [{$system->key}]: {$e->getMessage()}");

            return [0, 0];
        }
    }
}
