{{--
    Related records count of a list row: the icon of the resource plus how many of them the row has.
    $url, when given, turns the count into a link to that list. Without it the count renders as a
    div carrying the very same button classes, so every count column looks alike but only the ones
    that navigate end up focusable. $label is the hover text and, on the link, the accessible name,
    so a bare number is never all the user gets. A zero count renders an empty cell: nothing to hover.
--}}
@props(['icon', 'count', 'label', 'url' => null])

@if ($count > 0)
    <flux:tooltip :content="$label">
        @if ($url)
            <flux:button :href="$url" wire:navigate size="xs" variant="ghost" :icon="$icon"
                :aria-label="$label" {{ $attributes }}
                class="hover:bg-zinc-100 hover:text-brand-700 dark:hover:bg-zinc-800 dark:hover:text-brand-400">
                {{ $count }}
            </flux:button>
        @else
            <flux:button as="div" size="xs" variant="ghost" :icon="$icon" {{ $attributes }}
                class="hover:bg-zinc-100 hover:text-brand-700 dark:hover:bg-zinc-800 dark:hover:text-brand-400">
                {{ $count }}
            </flux:button>
        @endif
    </flux:tooltip>
@else
    <span {{ $attributes }}></span>
@endif
