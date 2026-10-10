<?php

namespace App\Providers;

use App\Models\Epic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use App\Models\Project;
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
     * A project cannot reach the trash while it has epics, and an epic cannot
     * be deleted while it has comments; trashed children count too. The row is
     * locked inside the guard's own transaction, so a child that appears
     * between the check and the delete makes the delete wait and then fail.
     *
     * The hook is what makes this safe, not the caller: it holds the guarantee
     * for every path that soft deletes a record, including a plain
     * `$model->delete()` outside a controller. (Customers are guarded by the
     * customers13 package.)
     */
    protected function blockDeletionWithChildren(): void
    {
        Project::deleting(function (Project $project): bool {
            $locked = DB::transaction(static fn(): Project => Project::withTrashed()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! $locked->epics()->withTrashed()->exists();
        });

        Epic::deleting(function (Epic $epic): bool {
            $locked = DB::transaction(static fn(): Epic => Epic::withTrashed()
                ->whereKey($epic->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! $locked->comments()->exists();
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
        Gate::define('downloadLogFile', fn(): bool => false);
        Gate::define('downloadLogFolder', fn(): bool => false);
        Gate::define('deleteLogFile', fn(): bool => false);
        Gate::define('deleteLogFolder', fn(): bool => false);
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
            fn(): ?Password => app()->isProduction()
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
