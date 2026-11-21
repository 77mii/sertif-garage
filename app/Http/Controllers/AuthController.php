<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterTenantRequest;
use App\Services\TenantRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantRegistrationService $registrationService
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => strtolower(trim((string) $request->validated('email'))),
            'password' => (string) $request->validated('password'),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Email atau kata sandi tidak sesuai.',
                ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null && $user->isAdmin()) {
            return redirect()->route('admin.garages.index');
        }

        if ($user !== null && $user->isApproved()) {
            return redirect()->route('tenant.rentals.create');
        }

        return redirect()->route('tenant.status');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && ! $user->isRejected()) {
            return redirect()->route('home');
        }

        return view('auth.register', [
            'rejectedUser' => $user?->isRejected() ? $user : null,
        ]);
    }

    public function register(RegisterTenantRequest $request): RedirectResponse
    {
        $currentUser = $request->user();

        if ($currentUser !== null && ! $currentUser->isRejected()) {
            return redirect()->route('home');
        }

        $wasRejectedSession = $currentUser !== null && $currentUser->isRejected();
        $registeredUser = $this->registrationService->register([
            ...$request->validated(),
            'password_confirmation' => $request->input('password_confirmation'),
        ]);

        if ($wasRejectedSession) {
            Auth::login($registeredUser);
            $request->session()->regenerate();

            return redirect()
                ->route('tenant.status')
                ->with('success', 'Pendaftaran ulang berhasil dikirim. Akun Anda kini menunggu verifikasi admin.');
        }

        return redirect()
            ->route('login')
            ->with('success', 'Pendaftaran berhasil dikirim. Silakan masuk untuk memantau status verifikasi akun Anda.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
