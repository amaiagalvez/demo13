{{--
    Confirmation modal driven by the parent Alpine "confirmation" state
    (set by confirmAction() from x-list.row-actions).
--}}
@props(['prefix'])

<flux:modal :name="$prefix.'-confirm'" class="max-w-md">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="lg" x-text="confirmation.title"></flux:heading>
            <flux:text class="mt-2" x-text="confirmation.text"></flux:text>
        </div>

        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <form method="POST" x-bind:action="confirmation.action" x-data="{ isSubmitting: false }"
                x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true">
                @csrf
                <input type="hidden" name="_method" x-bind:value="confirmation.method">
                <template x-if="confirmation.danger">
                    <flux:button variant="danger" type="submit" x-bind:disabled="isSubmitting"
                        :data-test="$prefix.'-confirm-submit'">
                        <span x-text="confirmation.label"></span>
                    </flux:button>
                </template>
                <template x-if="!confirmation.danger">
                    <flux:button variant="primary" type="submit" x-bind:disabled="isSubmitting"
                        :data-test="$prefix.'-confirm-submit'">
                        <span x-text="confirmation.label"></span>
                    </flux:button>
                </template>
            </form>
        </div>
    </div>
</flux:modal>
