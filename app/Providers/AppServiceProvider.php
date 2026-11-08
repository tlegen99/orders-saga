<?php

namespace App\Providers;

use App\Messaging\LogMessageBus;
use App\Messaging\MessageBus;
use App\Messaging\NatsMessageBus;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return new Client(new Configuration([
                'host' => config('services.nats.host'),
                'port' => config('services.nats.port'),
                'timeout' => 2,
            ]));
        });

        $this->app->singleton(MessageBus::class, NatsMessageBus::class);

        $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
