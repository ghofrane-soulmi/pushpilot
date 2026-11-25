<?php

namespace App\Providers;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Policies\DeploymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ServerPolicy;
use App\Support\Ssh\PhpseclibSshClient;
use App\Support\Ssh\SshClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SshClient::class, PhpseclibSshClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Domain models live outside App\Models, so map their policies explicitly.
        Gate::policy(Server::class, ServerPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Deployment::class, DeploymentPolicy::class);

        // Spec 9.1: rate limit the public webhook endpoint per project and source.
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(30)
            ->by($request->ip().'|'.(string) $request->route('slug')));
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
