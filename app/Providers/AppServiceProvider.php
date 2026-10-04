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
        $this->blockDeletionWithChildren();

        $this->configureDefaults();
        $this->authorizeLogViewer();
        /* @chisel-password-confirmation */
        Livewire::addPersistentMiddleware([RequirePasswordForLivewire::class]);
        /* @end-chisel-password-confirmation */
    }

    /**
     * A customer cannot reach the trash while it has projects, and a project cannot while it has
     * epics; trashed children count too. The row is locked inside the guard's own transaction, so a
     * child that appears between the check and the delete makes the delete wait and then fail.
     *
     * The hook is what makes this safe, not the caller: it holds the guarantee for every path that
     * soft deletes a record, including a plain `$model->delete()` outside a controller.
     */
    protected function blockDeletionWithChildren(): void
    {
        Customer::deleting(function (Customer $customer): bool {
            $locked = DB::transaction(static fn (): Customer => Customer::withTrashed()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! $locked->projects()->withTrashed()->exists();
        });

        Project::deleting(function (Project $project): bool {
            $locked = DB::transaction(static fn (): Project => Project::withTrashed()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! $locked->epics()->withTrashed()->exists();
        });
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
