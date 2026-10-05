<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component { // @phpstan-ignore expr.resultUnused (Volt requires the anonymous class expression statement)
    /** @var array<int, string> */
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user !== null && $user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $decryptedRecoveryCodes = decrypt($user->two_factor_recovery_codes);

                if (! is_string($decryptedRecoveryCodes)) {
                    throw new UnexpectedValueException('Decrypted recovery codes are not a string.');
                }

                $decodedRecoveryCodes = json_decode($decryptedRecoveryCodes, true);

                if (! is_array($decodedRecoveryCodes) || ! array_is_list($decodedRecoveryCodes)) {
                    throw new UnexpectedValueException('Decrypted recovery codes are not a list.');
                }

                $this->recoveryCodes = array_map(static function (mixed $recoveryCode): string {
                    if (! is_string($recoveryCode)) {
                        throw new UnexpectedValueException('A decrypted recovery code is not a string.');
                    }

                    return $recoveryCode;
                }, $decodedRecoveryCodes);
            } catch (Exception) {
                $this->addError('recoveryCodes', __('Failed to load recovery codes'));

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div class="py-6 space-y-6 border shadow-sm rounded-xl border-zinc-200 dark:border-white/10"
    wire:cloak x-data="{ showRecoveryCodes: false }">
    <div class="px-6 space-y-2">
        <div class="flex items-center gap-2">
            <flux:icon.lock-closed variant="outline" class="size-4" />
            <flux:heading size="lg" level="3">{{ __('2FA recovery codes') }}</flux:heading>
        </div>
        <flux:text variant="subtle">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </flux:text>
    </div>

    <div class="px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button x-show="!showRecoveryCodes" icon="eye" icon:variant="outline"
                variant="primary" @click="showRecoveryCodes = true;" aria-expanded="false"
                aria-controls="recovery-codes-section">
                {{ __('View recovery codes') }}
            </flux:button>

            <flux:button x-show="showRecoveryCodes" icon="eye-slash" icon:variant="outline"
                variant="primary" @click="showRecoveryCodes = false" aria-expanded="true"
                aria-controls="recovery-codes-section">
                {{ __('Hide recovery codes') }}
            </flux:button>

            @if (filled($recoveryCodes))
                <flux:button x-show="showRecoveryCodes" icon="arrow-path" variant="filled"
                    wire:click="regenerateRecoveryCodes">
                    {{ __('Regenerate codes') }}
                </flux:button>
            @endif
        </div>

        <div x-show="showRecoveryCodes" x-transition id="recovery-codes-section"
            class="relative overflow-hidden" x-bind:aria-hidden="!showRecoveryCodes">
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
                @enderror

                @if (filled($recoveryCodes))
                    <div class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-zinc-100 dark:bg-white/5"
                        role="list" aria-label="{{ __('Recovery codes') }}">
                        @foreach ($recoveryCodes as $code)
                            <div role="listitem" class="select-text"
                                wire:loading.class="opacity-50 animate-pulse">
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <flux:text variant="subtle" class="text-xs">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                    </flux:text>
                @endif
            </div>
        </div>
    </div>
</div>
