<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="lg:ms-0">
        <div class="mx-auto flex min-h-[calc(100dvh-1.5rem)] w-full max-w-7xl flex-col gap-6">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
