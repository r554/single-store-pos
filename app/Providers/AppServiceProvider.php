<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
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
        // Semua notifikasi auth mengarah ke frontend SPA, bukan halaman blade bawaan.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return config('app.frontend_url', config('app.url'))
                .'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth_features.email_verification.expire', 60)),
                ['id' => $notifiable->getKey()]
            );

            // Teruskan path API + query signature ke halaman SPA untuk diverifikasi.
            $path = parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);

            return config('app.frontend_url', config('app.url'))
                .'/email-verify?api='.rawurlencode($path.'?'.$query);
        });
    }
}
