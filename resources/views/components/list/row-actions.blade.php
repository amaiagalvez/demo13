{{--
    Row action buttons built by the *ListTransformer classes.
    "form-modal" actions open the "{prefix}-form" drawer and pass $action[$payloadKey] to the
    Alpine $editHandler; any other action opens the "{prefix}-confirm" modal.
    $blockedHint, when given, replaces the tooltip of the blocking action so the reason why the
    record cannot be deleted is readable before clicking it.
--}}
@props(['actions', 'prefix', 'payloadKey', 'editHandler', 'blockedHint' => null])

<div class="flex justify-end gap-0.5">
    @foreach ($actions as $action)
        @if ($action['type'] === 'form-modal')
            <flux:modal.trigger :name="$prefix.'-form'">
                <flux:tooltip :content="$action['label']">
                    <flux:button size="xs" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"
                        data-payload="{{ json_encode($action[$payloadKey]) }}"
                        :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @elseif ($action['type'] === 'blocked')
            <flux:tooltip :content="$action['hint']">
                <span tabindex="0" class="inline-flex" :aria-label="$action['hint']">
                    <flux:button size="xs" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']" disabled :data-test="$action['test']" />
                </span>
            </flux:tooltip>
        @else
            <flux:modal.trigger :name="$prefix.'-confirm'">
                <flux:tooltip :content="$blockedHint ?? $action['label']">
                    <flux:button size="xs" square variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        :class="($action['danger'] ?? false) ? 'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300' : ''"
                        x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                        data-action="{{ json_encode($action) }}" :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @endif
    @endforeach
</div>
