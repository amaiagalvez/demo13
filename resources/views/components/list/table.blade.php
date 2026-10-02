@props(['prefix', 'paginator'])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-6']) }}>
    <div class="resource-list-table overflow-x-auto rounded-md border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <flux:table>
            {{ $slot }}
        </flux:table>
    </div>

    @if ($paginator->hasPages())
        <nav aria-label="{{ __('Pagination') }}" data-test="{{ $prefix }}-pagination">
            {{ $paginator->links() }}
        </nav>
    @endif
</div>
