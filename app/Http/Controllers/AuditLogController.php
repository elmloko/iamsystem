<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\SystemAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(private SystemAuditService $audit)
    {
    }

    public function index(Request $request): Response
    {
        $systemKey = $request->string('system')->value() ?: null;

        if ($systemKey && $systemKey !== 'iam') {
            return $this->externalIndex($request, $systemKey);
        }

        $query = AuditLog::with('user:id,name,email')->latest('created_at');

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->string('action')->trim()->value()) {
            $query->where('action', $action);
        }

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%");
            });
        }

        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $logs = $query->paginate(30)->withQueryString();

        return Inertia::render('Audit/Index', [
            'mode' => 'iam',
            'systems' => $this->audit->availableSystems(),
            'logs' => $logs,
            'filters' => $request->only(['user_id', 'action', 'q', 'from', 'to', 'system']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /**
     * Auditoría de un sistema externo puntual (SIOP/SIGEC/API Web) — ver
     * SystemAuditService, que trae los registros en vivo de la base de
     * datos real de cada sistema (no vive en audit_logs).
     */
    private function externalIndex(Request $request, string $systemKey): Response
    {
        $query = $request->string('q')->trim()->value() ?: null;
        $actor = $request->string('actor')->trim()->value() ?: null;
        $page = max(1, (int) $request->integer('page', 1));

        $results = $this->audit->search($systemKey, $query, $actor, $page);

        return Inertia::render('Audit/Index', [
            'mode' => 'external',
            'systems' => $this->audit->availableSystems(),
            'filters' => ['system' => $systemKey, 'q' => $query, 'actor' => $actor],
            'page' => $page,
            'results' => $results,
        ]);
    }

    public function actors(Request $request)
    {
        $systemKey = $request->string('system')->value();

        return response()->json([
            'actors' => $systemKey ? $this->audit->actorsFor($systemKey) : [],
        ]);
    }
}
