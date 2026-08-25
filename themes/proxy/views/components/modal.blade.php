{{--
    Overrides the default theme's <x-modal>.

    The default's modal is pure Tailwind — `fixed inset-0 z-30 … bg-black/50` on the
    backdrop and `px-6 py-4 … rounded shadow-lg` on the dialog — and it teleports itself to
    the end of <body>. This theme deliberately does not load the Tailwind bundle
    (layouts/app.blade.php), so none of those class names carried any styling: every modal
    in the client area lost its positioning and rendered as a run of bare text *below the
    footer*, which is what customers were seeing under the invoice page.

    Same props and the same `open` Alpine state as the default, so every caller keeps
    working unchanged — invoices (payment), services (cancellation), account security (2FA)
    and payment methods. Only the chrome is this theme's.

    Escape-to-close is deliberately not bound: callers close through Livewire
    (`wire:click="exitPay"` and friends) as well as `open = false`, and a purely visual
    close would leave the server-side flag set, so the modal would reappear on the next
    Livewire render.
--}}
@props([
    'title' => '',
    'closable' => true,
    'closeTrigger' => '',
    'open' => false,
    'width' => 'max-w-3xl',
    // Teleporting to <body> escapes any ancestor that would clip or re-stack the dialog,
    // which is why the default theme does it and why it is still the default here.
    //
    // It must be turned OFF for anything that mounts a third-party widget, though.
    // `<template>` content is inert — it is a document fragment, not the page — and Alpine
    // only clones it into <body> when it initialises. A gateway's `@script` runs on Livewire
    // component boot and calls `mount('#payment-element')`, which at that moment matches
    // nothing, so Stripe's form silently never appears and its Pay button stays dead.
    'teleport' => true,
])

@php
    // Callers pass Tailwind's `max-w-*` token because that is the default theme's API and
    // the pages are shared between both themes. Translate it into the width it stands for.
    $maxWidth = [
        'max-w-sm' => '24rem',
        'max-w-md' => '28rem',
        'max-w-lg' => '32rem',
        'max-w-xl' => '36rem',
        'max-w-2xl' => '42rem',
        'max-w-3xl' => '48rem',
        'max-w-4xl' => '56rem',
        'max-w-5xl' => '64rem',
    ][$width] ?? '48rem';
@endphp

{{-- The wrapper is opened and closed conditionally so the dialog markup exists once. When
     not teleported it simply stays in the document; `.wf-modal` is `position: fixed`, so it
     still covers the page. --}}
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    @if ($teleport)
        <template x-teleport="body">
    @endif

    <div class="wf-modal" x-show="open" x-cloak x-transition.opacity role="dialog" aria-modal="true">
        <div class="wf-modal-dialog" style="max-width: {{ $maxWidth }}">
            <div class="wf-modal-head">
                <h2 class="wf-modal-title">{{ $title }}</h2>
                @if ($closable && !$closeTrigger)
                    <button type="button" class="wf-modal-close" @click="open = false"
                        aria-label="{{ __('Close') }}">&times;</button>
                @elseif ($closable && $closeTrigger)
                    {{ $closeTrigger }}
                @endif
            </div>
            <div class="wf-modal-body">
                {{ $slot }}
            </div>
        </div>
    </div>

    @if ($teleport)
        </template>
    @endif
</div>
