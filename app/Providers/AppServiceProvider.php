<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use App\Models\Customer;
/* @chisel-password-confirmation */
use App\Http\Middleware\RequirePasswordForLivewire;
use Livewire\Livewire;
/* @end-chisel-password-confirmation */
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
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
        Customer::deleting(
            static fn (Customer $customer): bool => ! $customer->projects()->withTrashed()->exists(),
        );
        Project::deleting(
            static fn (Project $project): bool => ! $project->epics()->withTrashed()->exists(),
        );

        $this->configureDefaults();
        $this->authorizeLogViewer();
        /* @chisel-password-confirmation */
        Livewire::addPersistentMiddleware([RequirePasswordForLivewire::class]);
        /* @end-chisel-password-confirmation */
    }

    /**
     * The log viewer is registered by its own package, so its gates are defined here. Deleting and
     * downloading log files stay closed: only reading the entries is allowed.
     */
    protected function authorizeLogViewer(): void
    {
        Gate::define(
            'viewLogViewer',
            fn (User $user): bool => in_array($user->email, config('log-viewer.allowed_emails', []), true),
        );
        Gate::define('downloadLogFile', fn (): bool => true);
        Gate::define('downloadLogFolder', fn (): bool => false);
        Gate::define('deleteLogFile', fn (): bool => false);
        Gate::define('deleteLogFolder', fn (): bool => false);
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
