<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\ReportType;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogLockout;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Policies\ReportPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureAuditTrail();
    }

    /**
     * Audit trail for authentication events.
     *
     * Registered explicitly rather than relying on listener auto-discovery: a
     * silently unregistered listener means a silently missing audit trail.
     */
    protected function configureAuditTrail(): void
    {
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        Event::listen(Lockout::class, LogLockout::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Behind Dokploy/Traefik, SSL terminates at the proxy — the app sees a
        // plain-http connection. trustProxies() alone depends on the proxy
        // forwarding X-Forwarded-Proto correctly; this forces the scheme from
        // APP_URL directly so asset()/url() never regress to http and trip
        // mixed-content blocking, regardless of proxy header behavior.
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

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

    /**
     * Authorization wiring.
     *
     * Note: Praktika deliberately does NOT use a `Gate::before()` Super Admin bypass.
     * RolePermissionSeeder grants the Super Admin role every permission in
     * PermissionCatalog explicitly, so a bypass would be redundant — and worse, it
     * would return `true` before UserPolicy runs, silently disabling the guards that
     * stop a Super Admin from deleting their own account or the last Super Admin.
     * Authorization therefore flows through policies for every role alike.
     */
    protected function configureAuthorization(): void
    {
        // A report has no model, so the enum itself is the policy target: Gate
        // resolves a policy from the class of the object handed to authorize().
        Gate::policy(ReportType::class, ReportPolicy::class);
    }
}
