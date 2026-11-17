<?php

namespace App\Providers;

use App\Services\Billing\BillingDirectory;
use App\Services\Customers\CustomerDirectory;
use App\Services\Numbers\NumberDirectory;
use App\Services\Subscriptions\SubscriptionDirectory;
use App\Services\TaskQueue\QueueServiceClient;
use App\Services\TaskQueue\TaskQueue;
use App\Services\Usage\UsageDirectory;
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
        $this->app->singleton(QueueServiceClient::class, fn ($app) => new QueueServiceClient(
            $app['config']->get('services.queue'),
        ));

        $this->app->singleton(TaskQueue::class, fn ($app) => new TaskQueue(
            $app->make(QueueServiceClient::class),
            $app['config']->get('services.queue'),
        ));

        $this->app->singleton(CustomerDirectory::class, fn ($app) => new CustomerDirectory(
            $app->make(TaskQueue::class),
            (int) $app['config']->get('services.customers.timeout_ms'),
        ));

        $this->app->singleton(BillingDirectory::class, fn ($app) => new BillingDirectory(
            $app->make(TaskQueue::class),
            (int) $app['config']->get('services.billing.timeout_ms'),
        ));

        $this->app->singleton(NumberDirectory::class, fn ($app) => new NumberDirectory(
            $app->make(TaskQueue::class),
            (int) $app['config']->get('services.msisdn.timeout_ms'),
        ));

        $this->app->singleton(SubscriptionDirectory::class, fn ($app) => new SubscriptionDirectory(
            $app->make(TaskQueue::class),
            (int) $app['config']->get('services.subscriptions.timeout_ms'),
        ));

        $this->app->singleton(UsageDirectory::class, fn ($app) => new UsageDirectory(
            $app->make(TaskQueue::class),
            (int) $app['config']->get('services.usage.timeout_ms'),
        ));
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
