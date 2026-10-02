{{--
    Row action buttons built by the *ListTransformer classes.
    "form-modal" actions open the "{prefix}-form" drawer and pass $action[$payloadKey] to the
    Alpine $editHandler; any other action opens the "{prefix}-confirm" modal.
--}}
@props(['actions', 'prefix', 'payloadKey', 'editHandler'])

<div class="flex justify-end gap-2">
    @foreach ($actions as $action)
        @if ($action['type'] === 'form-modal')
            <flux:modal.trigger :name="$prefix.'-form'">
                <flux:tooltip :content="$action['label']">
                    <flux:button size="sm" variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        x-on:click="{{ $editHandler }}(JSON.parse($el.dataset.payload))"
                        data-payload="{{ json_encode($action[$payloadKey]) }}"
                        :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @else
            <flux:modal.trigger :name="$prefix.'-confirm'">
                <flux:tooltip :content="$action['label']">
                    <flux:button size="sm" variant="ghost" :icon="$action['icon']"
                        :aria-label="$action['label']"
                        :class="($action['danger'] ?? false) ? 'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300' : ''"
                        x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                        data-action="{{ json_encode($action) }}" :data-test="$action['test']" />
                </flux:tooltip>
            </flux:modal.trigger>
        @endif
    @endforeach
</div>
