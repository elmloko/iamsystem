<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admins/Index', [
            'admins' => User::orderBy('name')->get(['id', 'name', 'email', 'email_verified_at', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        Audit::log('admins.created', "Creó el usuario interno \"{$admin->name}\" ({$admin->email}).", $admin);

        return redirect()->route('admins.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $admin->name = $data['name'];
        $admin->email = $data['email'];

        $passwordChanged = ! empty($data['password']);
        if ($passwordChanged) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();

        Audit::log(
            'admins.updated',
            "Editó el usuario interno \"{$admin->name}\" ({$admin->email}).",
            $admin,
            ['password_changed' => $passwordChanged]
        );

        return redirect()->route('admins.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $admin): RedirectResponse
    {
        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if (User::count() <= 1) {
            return back()->with('error', 'Debe quedar al menos un usuario administrador.');
        }

        $name = $admin->name;
        $email = $admin->email;
        $admin->delete();

        Audit::log('admins.deleted', "Eliminó el usuario interno \"{$name}\" ({$email}).");

        return redirect()->route('admins.index')->with('success', 'Usuario eliminado.');
    }
}
