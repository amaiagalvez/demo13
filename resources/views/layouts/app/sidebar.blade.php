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
                <flux:sidebar.item icon="squares-2x2" :href="route('work.dashboard')"
                    :current="request()->routeIs('work.dashboard')" wire:navigate data-test="nav-work-dashboard">
                    {{ __('Work dashboard') }}
                </flux:sidebar.item>
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
                $seesUserTypes = auth()->user()->can('viewAny', \Basics13\Models\UserType::class);
                $seesSessions = auth()->user()->can('viewAny', \Basics13\Models\SessionLog::class);
                $seesConfiguration = auth()->user()->can('viewAny', \Basics13\Models\Setting::class);
            @endphp
            @if ($seesUsers || $seesUserTypes || $seesRoles || $seesSessions || $seesConfiguration)
                <flux:sidebar.group :heading="__('Administration')" class="grid">
                    @if ($seesSessions)
                        <flux:sidebar.item icon="chart-bar" :href="route('sessions.dashboard')"
                            :current="request()->routeIs('sessions.dashboard')" wire:navigate data-test="nav-admin-dashboard">
                            {{ __('Administration dashboard') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($seesUsers)
                        <flux:sidebar.item icon="user-circle" :href="route('users.index')"
                            :current="request()->routeIs('users.*') && ! request()->routeIs('users.permissions-summary.*')"
                            wire:navigate data-test="nav-users">
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($seesUserTypes)
                        <flux:sidebar.item icon="tag" :href="route('user-types.index')"
                            :current="request()->routeIs('user-types.*')" wire:navigate data-test="nav-user-types">
                            {{ __('User types') }}
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
                    @if ($seesSessions)
                        <flux:sidebar.item icon="finger-print" :href="route('sessions.index')"
                            :current="request()->routeIs('sessions.*') && ! request()->routeIs('sessions.dashboard')" wire:navigate
                            data-test="nav-sessions">
                            {{ __('Sessions') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($seesConfiguration)
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('configuration.index')"
                            :current="request()->routeIs('configuration.*')" wire:navigate data-test="nav-configuration">
                            {{ __('Application configuration') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif
        </flux:sidebar.nav>

    </flux:sidebar>

    {{-- Top bar: sidebar toggle on mobile, user menu on every size. --}}
    <flux:header
        sticky class="border-b border-zinc-200 bg-zinc-50/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <x-desktop-user-menu />
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
