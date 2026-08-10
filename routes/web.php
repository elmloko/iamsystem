<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAccessRequestController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Formulario público de solicitud de acceso: sin login, para que cualquier
// empleado pida acceso a los sistemas que necesita.
Route::get('/solicitar-acceso', [PublicAccessRequestController::class, 'create'])->name('access-requests.create');
Route::post('/solicitar-acceso', [PublicAccessRequestController::class, 'store'])->name('access-requests.store');
Route::get('/solicitar-acceso/enviada', [PublicAccessRequestController::class, 'sent'])->name('access-requests.sent');
Route::get('/solicitar-acceso/sistemas/{system}/roles', [PublicAccessRequestController::class, 'rolesFor'])->name('access-requests.roles');
Route::get('/solicitar-acceso/sistemas/{system}/campos-extra', [PublicAccessRequestController::class, 'extraFieldsFor'])->name('access-requests.extra-fields');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/solicitudes', [AccessRequestController::class, 'index'])->name('access-requests.index');
    Route::post('/solicitudes/items/{item}/aprobar', [AccessRequestController::class, 'approve'])->name('access-requests.approve');
    Route::post('/solicitudes/items/{item}/rechazar', [AccessRequestController::class, 'reject'])->name('access-requests.reject');
    Route::delete('/solicitudes/persona', [AccessRequestController::class, 'destroyForPerson'])->name('access-requests.destroy-person');

    Route::get('/sistemas', [SystemController::class, 'index'])->name('systems.index');
    Route::post('/sistemas', [SystemController::class, 'store'])->name('systems.store');
    Route::put('/sistemas/{system}', [SystemController::class, 'update'])->name('systems.update');
    Route::post('/sistemas/probar-conexion', [SystemController::class, 'testConnection'])->name('systems.test-connection');
    Route::patch('/sistemas/{system}/visibilidad', [SystemController::class, 'toggleVisibility'])->name('systems.toggle-visibility');

    Route::get('/administradores', [AdminUserController::class, 'index'])->name('admins.index');
    Route::post('/administradores', [AdminUserController::class, 'store'])->name('admins.store');
    Route::put('/administradores/{admin}', [AdminUserController::class, 'update'])->name('admins.update');
    Route::delete('/administradores/{admin}', [AdminUserController::class, 'destroy'])->name('admins.destroy');

    Route::get('/usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/usuarios/nuevo', [UserManagementController::class, 'create'])->name('users.create');
    Route::get('/usuarios/detalle', [UserManagementController::class, 'detail'])->name('users.detail');
    Route::post('/usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/usuarios/sistemas/{system}/roles', [UserManagementController::class, 'rolesFor'])->name('users.roles');
    Route::get('/usuarios/sistemas/{system}/campos-extra', [UserManagementController::class, 'extraFieldsFor'])->name('users.extra-fields');
    Route::put('/usuarios/cuentas/{system}', [UserManagementController::class, 'updateAccount'])->name('users.accounts.update');
    Route::patch('/usuarios/cuentas/{system}/estado', [UserManagementController::class, 'updateAccountStatus'])->name('users.accounts.status');
    Route::post('/usuarios/cuentas/{system}/roles', [UserManagementController::class, 'addAccountRole'])->name('users.accounts.roles');
    Route::delete('/usuarios/cuentas/{system}/roles', [UserManagementController::class, 'removeAccountRole'])->name('users.accounts.roles.destroy');
});

require __DIR__.'/auth.php';
