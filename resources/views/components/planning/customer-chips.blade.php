{{--
    Active customers of the planning screen, as chips carrying the live part of their portfolio.
    A chip opens the customer list already filtered to that customer, which is the drill-down the
    screen has no detail page for.
--}}
@props(['customers'])

@if ($customers !== [])
    <section aria-labelledby="planning-customers-heading" data-test="planning-customers">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <flux:heading id="planning-customers-heading" size="sm" level="2" class="font-semibold">
                {{ __('Customers') }}
            </flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total: :count', [
                    'count' => count($customers),
                ]) }}</flux:text>
        </div>

        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach ($customers as $customer)
                <li>
                    <a href="{{ $customer['url'] }}" wire:navigate data-test="{{ $customer['test'] }}"
                        class="flex items-baseline gap-2 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm transition-colors hover:border-zinc-300 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60">
                        <span class="max-w-[16rem] truncate font-medium text-zinc-900 dark:text-zinc-100"
                            title="{{ $customer['name'] }}">{{ $customer['name'] }}</span>
                        <span class="text-xs whitespace-nowrap text-zinc-500 tabular-nums dark:text-zinc-400">
                            {{ __('Projects: :count', ['count' => $customer['projectsCount']]) }} ·
                            {{ __('Epics: :count', ['count' => $customer['epicsCount']]) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
