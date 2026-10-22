<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl" level="1" class="font-display text-3xl! font-semibold! tracking-tight">
            {{ __('Dashboard') }}
        </flux:heading>

        <x-projects13::dashboard.summary :summary="$summary" :name="auth()->user()->name" />
    </div>
</x-layouts::app>
