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

        return Inertia::render('Dashboard', [
            'stats' => [
                'systemsActive' => $systems->where('status', 'active')->count(),
                'systemsPending' => $systems->where('status', 'pending')->count(),
                'people' => Person::count(),
                'accounts' => SystemAccount::count(),
                'liveAccounts' => $this->liveAccountCount(),
                'pendingRequests' => AccessRequestItem::where('status', 'pending')->count(),
            ],
            'systems' => $systems,
        ]);
    }

    /**
     * Total real de cuentas en los 16+ sistemas conectados, contando en vivo
     * (COUNT por sistema, sin traer datos — rápido incluso en red). Se cachea
     * porque igual son ~16 idas y vueltas por red en cada carga del dashboard.
     */
    private function liveAccountCount(): int
    {
        return Cache::remember('dashboard.live_account_count', now()->addMinutes(15), function () {
            // Modelos completos (no la selección recortada de columnas de
            // arriba): remoteConnectionName() necesita db_driver/db_host/
            // db_username/db_password, que ahí no se cargan.
            $systems = SystemEntry::where('status', 'active')->connectable()->get();
            $total = 0;

            foreach ($systems as $system) {
                try {
                    $total += DB::connection($system->remoteConnectionName())
                        ->table($system->users_table ?: 'users')
                        ->count();
                } catch (Throwable $e) {
                    Log::warning("Fallo contando cuentas en [{$system->key}]: {$e->getMessage()}");
                }
            }

            return $total;
        });
    }
}
