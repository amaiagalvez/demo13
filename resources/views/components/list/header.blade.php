{{--
    Breadcrumb and compact toolbar for resource list navigation.
--}}
@props(['list', 'prefix', 'createLabel', 'createClick'])

<div
    class="flex flex-wrap items-center justify-between gap-4 border-zinc-200 dark:border-zinc-800">
    <flux:breadcrumbs class="text-xs text-zinc-500 dark:text-zinc-400">
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
            {{ __('Dashboard') }}
        </flux:breadcrumbs.item>
        @if (!$list['create'])
            <flux:breadcrumbs.item :href="$list['navigation']['url']" wire:navigate>
                {{ $list['resource'] }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="request()->fullUrl()">
                {{ $list['state'] === 'inactive' ? __('Inactive') : __('Trash') }}
            </flux:breadcrumbs.item>
        @else
            <flux:breadcrumbs.item :href="request()->fullUrl()">
                {{ $list['resource'] }}
            </flux:breadcrumbs.item>
        @endif
    </flux:breadcrumbs>

    <div class="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900"
        role="toolbar" aria-label="{{ __('Actions') }}">
        @if ($list['create'])
            <flux:modal.trigger :name="$prefix.
            '-form'">
                <flux:tooltip :content="$createLabel">
                    <flux:button size="sm" square variant="primary" icon="plus"
                        :aria-label="$createLabel" x-on:click="{{ $createClick }}"
                        :data-test="$prefix.
                        '-create-button'" />
                </flux:tooltip>
            </flux:modal.trigger>
            <flux:tooltip :content="__('Inactive')">
                <flux:button :href="$list['inactiveUrl']" size="sm" square variant="ghost"
                    icon="lock-closed" :aria-label="__('Inactive')"
                    wire:navigate :data-test="$prefix.'-inactive-link'" />
            </flux:tooltip>
        @endif
        <flux:tooltip :content="$list['navigation']['label']">
            <flux:button :href="$list['navigation']['url']" size="sm" square variant="ghost"
                :icon="$list['navigation']['icon']" :aria-label="$list['navigation']['label']"
                wire:navigate :data-test="$list['navigation']['test']" />
        </flux:tooltip>
    </div>
</div>
