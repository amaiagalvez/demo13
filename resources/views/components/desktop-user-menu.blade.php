<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
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
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="desktop-logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
