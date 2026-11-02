<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterClientRequest;
use App\Http\Requests\RequestClientLoginCodeRequest;
use App\Http\Requests\VerifyClientLoginCodeRequest;
use App\Models\User;
use App\Services\ClientLoginCodeService;
use App\Services\ClientRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->role === 'client') {
            return to_route('client.dashboard');
        }

        return view('auth.client-login');
    }

    public function registrationForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->role === 'client') {
            return to_route('client.dashboard');
        }

        return view('auth.client-register');
    }

    public function register(RegisterClientRequest $request, ClientRegistrationService $registration, ClientLoginCodeService $loginCodes): RedirectResponse
    {
        $data = $request->validated();
        $data['email'] = Str::lower(trim($data['email']));
        $registration->register($data);
        $loginCodes->requestCode($data['email']);
        $request->session()->put('client_login_email', $data['email']);

        return to_route('client.login')->with('status', 'Your details were accepted. If the email can be verified, a sign-in code has been sent.');
    }

    public function requestCode(RequestClientLoginCodeRequest $request, ClientLoginCodeService $loginCodes): RedirectResponse
    {
        $email = Str::lower(trim($request->validated('email')));
        $loginCodes->requestCode($email);
        $request->session()->put('client_login_email', $email);

        return to_route('client.login')->with('status', 'If this email is linked to a client account, a sign-in code has been sent.');
    }

    public function verifyCode(VerifyClientLoginCodeRequest $request, ClientLoginCodeService $loginCodes): RedirectResponse
    {
        $email = $request->session()->get('client_login_email');
        $user = is_string($email) ? $loginCodes->verifyCode($email, $request->validated('code')) : null;

        if (! $user instanceof User) {
            return back()->withErrors(['code' => 'That code is invalid or has expired. Request a new code and try again.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('client_login_email');

        return to_route('client.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('client.login');
    }
}
