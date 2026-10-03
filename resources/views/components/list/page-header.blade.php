{{--
    Page heading for resource lists: breadcrumbs, H1 and the state tabs. Rendered outside the
    "list-results" fragment so it stays stable while the results morph on search.

    The breadcrumbs already name the page, so the H1 is only there for the document outline and for
    screen readers: it is announced once, not repeated on screen.
--}}
@props(['list', 'prefix'])

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <flux:breadcrumbs class="text-xs text-zinc-500 dark:text-zinc-400"
            :data-test="$prefix.'-breadcrumbs'">
            @foreach ($list['breadcrumbs'] as $breadcrumb)
                @if ($loop->last)
                    {{-- The current page links to its own URL, search included. --}}
                    <flux:breadcrumbs.item :href="request()->fullUrl()" aria-current="page">
                        {{ $breadcrumb['label'] }}
                    </flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item :href="$breadcrumb['url']" wire:navigate>
                        {{ $breadcrumb['label'] }}
                    </flux:breadcrumbs.item>
                @endif
            @endforeach
        </flux:breadcrumbs>

        <flux:heading level="1" size="xl" class="sr-only" :data-test="$prefix.'-heading'">
            {{ collect($list['breadcrumbs'])->pluck('label')->implode(' / ') }}
        </flux:heading>
    </div>

    <x-list.tabs :tabs="$list['tabs']" :prefix="$prefix" />
</div>
