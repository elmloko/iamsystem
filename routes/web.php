<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemUserSearchController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('users.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/usuarios/nuevo', [UserManagementController::class, 'create'])->name('users.create');
    Route::get('/usuarios/detalle', [UserManagementController::class, 'detail'])->name('users.detail');
    Route::post('/usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/usuarios/sistemas/{system}/roles', [UserManagementController::class, 'rolesFor'])->name('users.roles');

    Route::get('/buscar', [SystemUserSearchController::class, 'index'])->name('search.index');
    Route::get('/buscar/resultados', [SystemUserSearchController::class, 'search'])->name('search.query');
});

require __DIR__.'/auth.php';
