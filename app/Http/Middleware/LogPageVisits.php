<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogPageVisits
{
    /**
     * Pestañas del panel a registrar en la auditoría ("entró a X"). Solo las
     * visitas de página completa (no recargas parciales de Inertia por
     * filtros/paginación, ni las peticiones fetch/JSON de cada pantalla).
     */
    private const PAGES = [
        'dashboard' => 'Dashboard',
        'systems.index' => 'Sistemas',
        'users.index' => 'Administrador de Usuarios',
        'users.create' => 'Crear usuario',
        'access-requests.index' => 'Solicitudes',
        'admins.index' => 'Usuarios internos',
        'profile.edit' => 'Mi perfil',
        'audit.index' => 'Auditoría',
    ];

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $routeName = $request->route()?->getName();

        if (
            $request->isMethod('get')
            && $routeName
            && isset(self::PAGES[$routeName])
            && ! $request->header('X-Inertia-Partial-Component')
            && $response->getStatusCode() < 400
        ) {
            Audit::log('page_view', 'Entró a la pestaña "'.self::PAGES[$routeName].'".');
        }

        return $response;
    }
}
