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
            <flux:sidebar.item icon="home" :href="route('dashboard')"
                :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:sidebar.item>

            {{-- The packages seed the rest of the menu, each entry tied to the permissions its page asks for. --}}
            <x-basics13::sidebar-menu />
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
