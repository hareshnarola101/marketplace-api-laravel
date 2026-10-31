<?php

namespace App\Providers;

use App\Billing\CommissionCalculator;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommissionCalculator::class, fn () => new CommissionCalculator(
            config()->integer('marketplace.commission.default_rate_bps'),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::define('access-admin', fn (User $user) => $user->isAdmin());
        Gate::define('act-as-vendor', fn (User $user) => $user->approvedVendor() !== null);

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
