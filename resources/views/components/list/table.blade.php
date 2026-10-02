@props(['prefix', 'paginator'])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-6']) }}>
    <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
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
