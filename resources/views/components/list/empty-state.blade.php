@props(['colspan', 'message'])

<flux:table.row>
    <flux:table.cell :colspan="$colspan" class="py-8 text-center text-zinc-500" data-test="list-empty-state">
        <div class="flex flex-col items-center gap-3 px-4 py-4">
            <flux:icon.magnifying-glass class="size-8 text-zinc-400 dark:text-zinc-500"
                aria-hidden="true" />
            <span>{{ $message }}</span>
            {{ $slot }}
        </div>
    </flux:table.cell>
</flux:table.row>
