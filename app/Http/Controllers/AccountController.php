<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    /**
     * GET /dashboard/settings — Account settings.
     *
     * This page is only about the account itself: profile, password and
     * deletion. The practice overview (stats, recent debates, reports) lives
     * on the dashboard — see DashboardController.
     *
     * No statistics are computed here. The page used to carry a copy of the
     * dashboard's numbers, which asked the settings page to be a report as
     * well as a form, and cost a query on every load for a strip the
     * dashboard already shows.
     */
    public function index(Request $request)
    {
        return view('account', [
            'user' => $request->user(),
        ]);
    }

    /**
     * PATCH /dashboard/settings — Update the profile (name and email).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Normalize before validating so the uniqueness rule checks the same
        // string that will be stored.
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return redirect()
            ->route('account.index')
            ->with('success', 'Profile updated.');
    }

    /**
     * PATCH /dashboard/settings/password — Change the password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('account.index')
            ->with('success', 'Password changed.');
    }

    /**
     * The password policy applied to registration and password changes.
     */
    private function passwordRule(): Password
    {
        return Password::min(10)
            ->letters()
            ->mixedCase()
            ->numbers();
    }

    /**
     * DELETE /dashboard/settings — Delete the account and every debate it owns.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        // Debates cascade-delete along with their rounds, turns, rewrites
        // and adjudications.
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('success', 'Your account has been deleted.');
    }
}
