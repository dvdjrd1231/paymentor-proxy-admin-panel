<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(in_array(app()->getLocale(), config('app.rtl_locales'))) dir="rtl" @endif>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        {{ config('app.name', 'Paymenter') }}
        @isset($title)
        - {{ $title }}
        @endisset
    </title>
    @livewireStyles
    {{-- This child theme reuses the `default` theme's compiled Vite assets, so it needs
         no separate asset build. Our WHMCS-style styling is added in whmcs.css below. --}}
    @vite(['themes/default/js/app.js', 'themes/default/css/app.css'], 'default')
    @include('layouts.colors')
    @include('layouts.whmcs-css')

    @if (config('settings.favicon'))
    <link rel="icon" href="{{ Storage::url(config('settings.favicon')) }}">
    @endif
    @isset($title)
    <meta content="{{ isset($title) ? config('app.name', 'Paymenter') . ' - ' . $title : config('app.name', 'Paymenter') }}" property="og:title">
    <meta content="{{ isset($title) ? config('app.name', 'Paymenter') . ' - ' . $title : config('app.name', 'Paymenter') }}" name="title">
    @endisset
    @isset($description)
    <meta content="{{ $description }}" property="og:description">
    <meta content="{{ $description }}" name="description">
    @endisset
    @isset($image)
    <meta content="{{ $image }}" property="og:image">
    <meta content="{{ $image }}" name="image">
    @endisset

    <meta name="theme-color" content="{{ theme('primary') }}">

    {!! hook('head') !!}
</head>

<body class="w-full bg-background text-base min-h-screen flex flex-col antialiased"
    x-cloak
    x-data="{
        theme: $persist('system').as('theme_mode'),
        systemDark: window.matchMedia('(prefers-color-scheme: dark)').matches,
        init() {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                this.systemDark = e.matches;
            });
        },
        get isDark() {
            return this.theme === 'dark' || (this.theme === 'system' && this.systemDark);
        }
    }"
    {{-- The WHMCS design is light-only, so this theme never applies the `dark` class.
         (x-data above is kept so the base theme's toggle JS still has its state.) --}}
>
    {!! hook('body') !!}
    <x-navigation />
    <div class="w-full flex flex-grow">
        {{-- Paymenter's default client sidebar is deliberately NOT rendered: the
             WHMCS-style menu bar already carries Dashboard / Services / Invoices /
             Tickets / Account, so the sidebar was duplicate navigation. The
             md:ml-64 offset it required is dropped with it, otherwise every client
             page would keep a 16rem empty gutter on the left. --}}
        <div class="flex flex-col flex-grow overflow-auto">
            {{-- No top offset: the WHMCS-style header/menu bars are static, not fixed. --}}
            <main class="grow">
                {{ $slot }}
            </main>
            <x-notification />
            <x-confirmation />
            {{-- Not wrapped in a flex container: that made the footer shrink to its
                 content width instead of spanning the full page. --}}
            <x-navigation.footer />
        </div>
        <x-impersonating />
    </div>
    @livewireScriptConfig
    {!! hook('footer') !!}
</body>

</html>
