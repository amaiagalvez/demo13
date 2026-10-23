<x-layouts::app :title="__('Dashboard')">
    <flux:heading size="xl" level="1" class="font-display text-3xl! font-semibold! tracking-tight" data-test="dashboard-greeting">
        {{ __('Hello, :name', ['name' => auth()->user()->name]) }}!
    </flux:heading>
</x-layouts::app>
