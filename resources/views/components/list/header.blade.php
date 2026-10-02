{{--
    List page heading with the navigation button (trash / back) and, on the active list,
    the "new" button that opens the "{prefix}-form" drawer.
--}}
@props(['list', 'prefix', 'createLabel', 'createClick'])

<div class="flex items-center justify-between gap-4">
    <div>
        <flux:heading size="xl">{{ $list['title'] }}</flux:heading>
        <flux:subheading>{{ $list['subtitle'] }}</flux:subheading>
    </div>
    <div class="flex items-center gap-2">
        <flux:button :href="$list['navigation']['url']" variant="ghost"
            :icon="$list['navigation']['icon']" wire:navigate
            :data-test="$list['navigation']['test']">
            {{ $list['navigation']['label'] }}
        </flux:button>
        @if ($list['create'])
            <flux:modal.trigger :name="$prefix.'-form'">
                <flux:button variant="primary" icon="plus" x-on:click="{{ $createClick }}"
                    :data-test="$prefix.'-create-button'">
                    {{ $createLabel }}
                </flux:button>
            </flux:modal.trigger>
        @endif
    </div>
</div>
