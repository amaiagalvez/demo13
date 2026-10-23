{{-- Top bar user menu; without an avatar image Flux shows the initials in a circle. --}}
<flux:dropdown position="bottom" align="end">
    <flux:profile
        circle
        :avatar="auth()->user()->avatar ?? null"
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        :chevron="false"
        data-test="sidebar-menu-button"
    />

    <flux:menu class="min-w-64">
        <div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
            <flux:avatar
                circle
                size="lg"
                :src="auth()->user()->avatar ?? null"
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate {{ session()->has('impersonator_id') ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                    {{ auth()->user()->name }}
                    @if (session()->has('impersonator_id'))
                        <span class="ml-2 text-xs text-red-500 dark:text-red-300">({{ __('basics13::messages.impersonating_as') }})</span>
                    @endif
                </flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>

        @if (session()->has('impersonator_id'))
            <flux:menu.separator />
            <form method="POST" action="{{ route('impersonation.leave') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-left"
                    class="w-full cursor-pointer bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30"
                    data-test="leave-impersonation-button"
                >
                    {{ __('basics13::messages.leave_impersonation') }}
                </flux:menu.item>
            </form>
        @endif

        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
            {{ __('Settings') }}
        </flux:menu.item>

        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                variant="danger"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer"
                data-test="desktop-logout-button"
            >
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
