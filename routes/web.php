<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/sistemas', [SystemController::class, 'index'])->name('systems.index');

    Route::get('/administradores', [AdminUserController::class, 'index'])->name('admins.index');
    Route::post('/administradores', [AdminUserController::class, 'store'])->name('admins.store');
    Route::put('/administradores/{admin}', [AdminUserController::class, 'update'])->name('admins.update');
    Route::delete('/administradores/{admin}', [AdminUserController::class, 'destroy'])->name('admins.destroy');

    Route::get('/usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/usuarios/nuevo', [UserManagementController::class, 'create'])->name('users.create');
    Route::get('/usuarios/detalle', [UserManagementController::class, 'detail'])->name('users.detail');
    Route::post('/usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/usuarios/sistemas/{system}/roles', [UserManagementController::class, 'rolesFor'])->name('users.roles');
    Route::put('/usuarios/cuentas/{system}', [UserManagementController::class, 'updateAccount'])->name('users.accounts.update');
    Route::patch('/usuarios/cuentas/{system}/estado', [UserManagementController::class, 'updateAccountStatus'])->name('users.accounts.status');
    Route::post('/usuarios/cuentas/{system}/roles', [UserManagementController::class, 'addAccountRole'])->name('users.accounts.roles');
    Route::delete('/usuarios/cuentas/{system}/roles', [UserManagementController::class, 'removeAccountRole'])->name('users.accounts.roles.destroy');
});

require __DIR__.'/auth.php';
