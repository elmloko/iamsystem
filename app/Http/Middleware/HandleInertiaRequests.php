<?php

namespace App\Http\Middleware;

use App\Models\AccessRequestItem;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Se usa en el menú para mostrar el número de solicitudes de
            // acceso pendientes junto al link "Solicitudes". Con closure
            // para que Inertia solo la evalúe si hace falta (ej. respuestas
            // parciales) y no en cada request de por vida.
            'pendingAccessRequestsCount' => fn () => $request->user()
                ? AccessRequestItem::where('status', 'pending')->count()
                : 0,
            'flash' => [
                'provisionResults' => $request->session()->get('provisionResults'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
