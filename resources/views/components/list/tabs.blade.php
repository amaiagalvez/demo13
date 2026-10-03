{{--
    State tabs (resource / inactive / trash) built by the *ListTransformer classes, each one with
    the number of records it holds. The current tab links to the full URL, search included, so it
    always describes the view being shown.
--}}
@props(['tabs', 'prefix'])

<nav aria-label="{{ __('List views') }}" data-test="{{ $prefix }}-tabs"
    class="flex flex-wrap items-center gap-x-1">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['current'] ? request()->fullUrl() : $tab['url'] }}" wire:navigate
            data-test="{{ $tab['test'] }}"
            @if ($tab['current']) aria-current="page" @endif
            class="inline-flex h-10 items-center gap-2 rounded-t-md border-b-2 px-3 text-sm font-medium transition-colors {{ $tab['current'] ? 'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' : 'border-transparent text-zinc-600 hover:border-zinc-300 hover:text-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-600 dark:hover:text-white' }}">
            <span>{{ $tab['label'] }}</span>
            @if ($tab['count'] !== null)
                <span
                    class="rounded-full bg-zinc-200 px-1.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach

    @isset($actions)
        <div class="ms-auto self-center">
            {{ $actions }}
        </div>
    @endisset
</nav>
