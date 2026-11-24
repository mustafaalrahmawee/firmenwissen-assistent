<?php

namespace App\Providers;

use App\Http\CurrentTenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(8)
            ->letters()
            ->rules([
                'regex:/^\p{Lu}/u',
                'regex:/[0-9]/',
            ]));

        RateLimiter::for('login', function (Request $request): Limit {
            $email = $request->input('email');

            return Limit::perMinute(5)
                ->by(Str::lower(Str::ascii(is_string($email) ? $email : '')).'|'.$request->ip());
        });

        RateLimiter::for('register', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute|'.$request->ip()),
            Limit::perDay(20)->by('day|'.$request->ip()),
        ]);

        RateLimiter::for('documents', fn (Request $request): array => [
            Limit::perMinute(30)->by('minute|'.$request->user()->id),
            Limit::perDay(200)->by('day|'.$request->user()->id),
        ]);
    }
}
