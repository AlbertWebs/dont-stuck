<?php

namespace App\Providers;

use App\Models\LoanAccount;
use App\Services\WebsitePageCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.client', function ($view): void {
            $user = Auth::user();
            $activeLoans = $user?->role === 'client'
                ? LoanAccount::query()
                    ->whereHas('borrower', fn (Builder $query): Builder => $query->where('user_id', $user->id))
                    ->whereIn('status', LoanAccount::OPEN_STATUSES)
                    ->with('application.product')
                    ->latest()
                    ->get()
                : collect();

            $view->with('sidebarActiveLoans', $activeLoans);
        });

        View::composer('layouts.site', function ($view): void {
            $view->with('siteContact', app(WebsitePageCatalog::class)->publicContent('contact')['content']);
        });

        RateLimiter::for('loan-applications', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('client-otp-request', function (Request $request): array {
            $emailKey = hash('sha256', strtolower(trim((string) $request->input('email', ''))));

            return [
                Limit::perMinute(1)->by('client-otp-request:ip:'.$request->ip().':'.$emailKey),
                Limit::perHour(5)->by('client-otp-request:email:'.$emailKey),
            ];
        });

        RateLimiter::for('client-register', function (Request $request): array {
            $emailKey = hash('sha256', strtolower(trim((string) $request->input('email', ''))));

            return [
                Limit::perMinute(3)->by('client-register:ip:'.$request->ip()),
                Limit::perHour(5)->by('client-register:email:'.$emailKey),
            ];
        });

        RateLimiter::for('client-otp-verify', function (Request $request): Limit {
            $emailKey = hash('sha256', (string) $request->session()->get('client_login_email', ''));

            return Limit::perMinute(8)->by('client-otp-verify:'.$request->ip().':'.$emailKey);
        });

        RateLimiter::for('client-document-upload', function (Request $request): Limit {
            return Limit::perHour(10)->by('client-document-upload:'.$request->user()?->id);
        });
    }
}
