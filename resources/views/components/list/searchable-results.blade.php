@props(['search', 'total' => null])

@php
    $resultsTemplate = __('Search results updated. Results: :count');
    $countTemplate = __(':count results');
@endphp

<div data-list-results
    :data-total-results="$total"
    :data-count-template="@js($countTemplate)"
    x-data="listSearch(@js($search['value']), @js($total ?? 0), @js($resultsTemplate))"
    x-on:input.debounce.400ms="searchInput($event)"
    x-on:submit="submitSearch($event)" x-on:click="handleClick($event)"
    class="flex flex-col gap-y-2 sm:gap-y-3">
    {{-- Visually hidden, so the new result count is announced when the table is replaced. The
         aria-live sits here rather than on the wrapper because the whole subtree is swapped on
         every search. listSearch fills it in after the morph. --}}
    <flux:text class="sr-only" role="status" aria-live="polite"
        data-list-results-announcement></flux:text>

    {{-- Visible result count --}}
    @if ($total !== null)
        <div x-show="totalResults > 0" class="text-sm text-zinc-500 dark:text-zinc-400"
            data-count-display></div>
    @endif

    {{ $slot }}
</div>
