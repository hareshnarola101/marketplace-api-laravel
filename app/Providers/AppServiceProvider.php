<?php

namespace App\Providers;

use App\Billing\CommissionCalculator;
use App\Models\User;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use App\Payments\StripeGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommissionCalculator::class, fn () => new CommissionCalculator(
            config()->integer('marketplace.commission.default_rate_bps'),
        ));

        $this->app->singleton(PaymentGateway::class, function (Application $app) {
            $driver = config()->string('marketplace.payments.gateway');

            if ($driver === 'stripe') {
                return new StripeGateway(
                    new StripeClient(config()->string('services.stripe.secret')),
                    config()->string('services.stripe.webhook_secret'),
                );
            }

            if ($app->isProduction()) {
                throw new RuntimeException('The fake payment gateway cannot be used in production.');
            }

            return new FakeGateway(config()->string('marketplace.payments.fake.webhook_secret'));
        });
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
