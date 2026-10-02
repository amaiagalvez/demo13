@props(['search', 'prefix'])

<form method="GET" action="{{ $search['action'] }}" class="flex w-full items-end gap-2 sm:max-w-xl">
    <flux:input name="search" :label="__('Search')" :placeholder="$search['placeholder']"
        :value="$search['value']" maxlength="255" icon="magnifying-glass"
        :data-test="$prefix.'-search'" />
    <flux:button type="submit" variant="primary" icon="magnifying-glass"
        :data-test="$prefix.'-search-submit'">
        {{ __('Search') }}
    </flux:button>
    @if ($search['value'] !== '')
        <flux:button :href="$search['action']" variant="ghost" icon="x-mark" wire:navigate
            :data-test="$prefix.'-search-clear'">
            {{ __('Clear') }}
        </flux:button>
    @endif
</form>
