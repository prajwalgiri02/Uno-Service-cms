<?php

namespace App\Http\Controllers\cms;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('cms/settings/index', [
            'profile' => $request->user()->only(['name', 'email']),
            'admins' => User::role(UserRole::ADMIN->value)->orderBy('name')->get(['id', 'name', 'email', 'last_login_at']),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);

        $admin = User::query()->create([...$data, 'email_verified_at' => now()]);
        $admin->assignRole(UserRole::ADMIN->value);

        return back()->with('success', "{$admin->name} can now sign in to the admin console.");
    }
}
