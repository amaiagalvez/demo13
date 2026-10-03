@props(['search', 'prefix'])

<form method="GET" action="{{ $search['action'] }}"
    data-list-search
    class="flex w-full items-center gap-2 sm:max-w-md">
    <div class="relative min-w-0 flex-1">
        <flux:input x-ref="search" class="min-w-0 flex-1" name="search"
            :aria-label="__('Search')" :placeholder="$search['placeholder']"
            :value="$search['value']" maxlength="255" icon="magnifying-glass"
            class:input="pe-10" :data-test="$prefix.'-search'" />
        @if ($search['value'] !== '')
            <div class="absolute inset-e-1 top-1/2 z-10 -translate-y-1/2">
                <flux:button :href="$search['action']" variant="ghost" size="sm" square
                    icon="x-mark" title="{{ __('Clear') }}" :aria-label="__('Clear')"
                    data-list-search-clear :data-test="$prefix.'-search-clear'" />
            </div>
        @endif
    </div>
</form>
