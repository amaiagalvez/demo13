{{--
    Page heading for resource lists: breadcrumbs, H1 and the state tabs. Rendered outside the
    "list-results" fragment so it stays stable while the results morph on search.

    The breadcrumbs already name the page, so the H1 is only there for the document outline and for
    screen readers: it is announced once, not repeated on screen.
--}}
@props(['list', 'prefix'])

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <div
            class="-mx-6 -mt-6 mb-2 w-[calc(100%+3rem)] border-b border-zinc-200 bg-zinc-50 pb-5 dark:border-zinc-700 dark:bg-zinc-900 lg:-mx-8 lg:-mt-8 lg:w-[calc(100%+4rem)]">
            <flux:breadcrumbs class="translate-y-1 ps-3 text-sm text-zinc-500 dark:text-zinc-400"
                :data-test="$prefix.
                '-breadcrumbs'">
                @foreach ($list['breadcrumbs'] as $breadcrumb)
                    @if ($loop->last)
                        {{-- The current page links to its own URL, search included. --}}
                        <flux:breadcrumbs.item :href="request()->fullUrl()" aria-current="page"
                            class="font-semibold text-zinc-900 dark:text-white">
                            {{ $breadcrumb['label'] }}
                        </flux:breadcrumbs.item>
                    @else
                        <flux:breadcrumbs.item :href="$breadcrumb['url']" wire:navigate
                            class="transition-colors hover:text-zinc-800 dark:hover:text-zinc-200">
                            {{ $breadcrumb['label'] }}
                        </flux:breadcrumbs.item>
                    @endif
                @endforeach
            </flux:breadcrumbs>
        </div>

        <flux:heading level="1" size="xl" class="sr-only"
            :data-test="$prefix.
            '-heading'">
            {{ collect($list['breadcrumbs'])->pluck('label')->implode(' / ') }}
        </flux:heading>
    </div>

    <x-list.tabs :tabs="$list['tabs']" :prefix="$prefix">
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
    </x-list.tabs>
</div>
