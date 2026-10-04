<?php

namespace App\Providers;

use App\Models\User;
use Livewire\Livewire;
use App\Models\Project;
/* @chisel-password-confirmation */
use App\Models\Customer;
use Carbon\CarbonImmutable;
/* @end-chisel-password-confirmation */
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Http\Middleware\RequirePasswordForLivewire;

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
        Customer::deleting(static function (Customer $customer): bool {
            return DB::transaction(static function () use ($customer): bool {
                $lockedCustomer = Customer::withTrashed()
                    ->whereKey($customer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                return ! $lockedCustomer->projects()->withTrashed()->exists();
            });
        });
        Project::deleting(static function (Project $project): bool {
            return DB::transaction(static function () use ($project): bool {
                $lockedProject = Project::withTrashed()
                    ->whereKey($project->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                return ! $lockedProject->epics()->withTrashed()->exists();
            });
        });

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
            static function (User $user): bool {
                $allowedEmails = config('log-viewer.allowed_emails', []);

                return is_array($allowedEmails) && in_array($user->email, $allowedEmails, true);
            },
        );
        Gate::define('downloadLogFile', fn (): bool => false);
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
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
