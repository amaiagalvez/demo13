<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>

        <nav aria-label="{{ __('Platform') }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <a href="{{ route('customers.index') }}" wire:navigate data-test="dashboard-customers"
                class="group rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="flex items-center gap-4">
                    <span class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition-colors group-hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:group-hover:bg-zinc-700">
                        <flux:icon.users class="size-6" aria-hidden="true" />
                    </span>
                    <flux:heading size="lg">{{ __('Customers') }}</flux:heading>
                </div>
            </a>
            <a href="{{ route('projects.index') }}" wire:navigate data-test="dashboard-projects"
                class="group rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="flex items-center gap-4">
                    <span class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition-colors group-hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:group-hover:bg-zinc-700">
                        <flux:icon.briefcase class="size-6" aria-hidden="true" />
                    </span>
                    <flux:heading size="lg">{{ __('Projects') }}</flux:heading>
                </div>
            </a>
            <a href="{{ route('epics.index') }}" wire:navigate data-test="dashboard-epics"
                class="group rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="flex items-center gap-4">
                    <span class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition-colors group-hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:group-hover:bg-zinc-700">
                        <flux:icon.rectangle-stack class="size-6" aria-hidden="true" />
                    </span>
                    <flux:heading size="lg">{{ __('Epics') }}</flux:heading>
                </div>
            </a>
        </nav>
    </div>
</x-layouts::app>
