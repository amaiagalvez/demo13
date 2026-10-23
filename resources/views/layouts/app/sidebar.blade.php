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

        {{-- Grouped by task: day-to-day work first, reference data next, administration last. --}}
        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')"
                :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:sidebar.item>

            <flux:sidebar.group :heading="__('Work')" class="grid">
                <flux:sidebar.item icon="briefcase" :href="route('projects.index')"
                    :current="request()->routeIs('projects.*')" wire:navigate>
                    {{ __('Projects') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="flag" :href="route('epics.index')"
                    :current="request()->routeIs('epics.*')" wire:navigate>
                    {{ __('Epics') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="calendar-days" :href="route('planning')"
                    :current="request()->routeIs('planning')" wire:navigate>
                    {{ __('Planning') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="bars-3-bottom-left" :href="route('timeline')"
                    :current="request()->routeIs('timeline')" wire:navigate>
                    {{ __('Timeline') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Data')" class="grid">
                <flux:sidebar.item icon="users" :href="route('entities.index')"
                    :current="request()->routeIs('entities.*')" wire:navigate>
                    {{ __('Entities') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="tag" :href="route('entity-types.index')"
                    :current="request()->routeIs('entity-types.*')" wire:navigate>
                    {{ __('Entity types') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            {{-- Each entry shows only to whoever holds the permission its page asks for. --}}
            @php
                $seesUsers = auth()->user()->can('viewAny', config('auth.providers.users.model'));
                $seesRoles = auth()->user()->can('viewAny', \Basics13\Models\Role::class);
            @endphp
            @if ($seesUsers || $seesRoles)
                <flux:sidebar.group :heading="__('Administration')" class="grid">
                    @if ($seesUsers)
                        <flux:sidebar.item icon="user-circle" :href="route('users.index')"
                            :current="request()->routeIs('users.*') && ! request()->routeIs('users.permissions-summary.*')"
                            wire:navigate data-test="nav-users">
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($seesRoles)
                        <flux:sidebar.item icon="shield-check" :href="route('roles.index')"
                            :current="request()->routeIs('roles.*')" wire:navigate data-test="nav-roles">
                            {{ __('Roles') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="key" :href="route('permissions.index')"
                            :current="request()->routeIs('permissions.*')" wire:navigate data-test="nav-permissions">
                            {{ __('Permissions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="table-cells" :href="route('users.permissions-summary.index')"
                            :current="request()->routeIs('users.permissions-summary.*')" wire:navigate
                            data-test="nav-permissions-summary">
                            {{ __('Permissions summary') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif
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
                                <flux:heading class="truncate {{ session()->has('impersonator_id') ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                    {{ auth()->user()->name }}
                                    @if (session()->has('impersonator_id'))
                                        <span class="ml-2 text-xs text-red-500 dark:text-red-300">({{ __('basics13::messages.impersonating_as') }})</span>
                                    @endif
                                </flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

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
