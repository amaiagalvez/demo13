<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="lg:ms-0">
        <div class="ms-0 me-auto flex min-h-[calc(100dvh-1.5rem)] w-full max-w-none flex-col gap-6">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
