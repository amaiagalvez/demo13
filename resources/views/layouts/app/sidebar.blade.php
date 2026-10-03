<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100"
    data-unsaved-warning="{{ __('You have unsaved changes. Leave without saving?') }}">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="users" :href="route('customers.index')"
                    :current="request()->routeIs('customers.*')" wire:navigate>
                    {{ __('Customers') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="briefcase" :href="route('projects.index')"
                    :current="request()->routeIs('projects.*')" wire:navigate>
                    {{ __('Projects') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="flag" :href="route('epics.index')"
                    :current="request()->routeIs('epics.*')" wire:navigate>
                    {{ __('Epics') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header
        class="border-b border-zinc-200 bg-zinc-50/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name"
                                :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}
                                </flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit"
                        icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer"
                        data-test="mobile-logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
