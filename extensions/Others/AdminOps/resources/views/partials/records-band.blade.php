{{-- The reference's pagination band, above every client-profile list: "N Records Found,
     Page 1 of 1" on the left and "Jump to Page: [1] Go" on the right. --}}
@php
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
@endphp

<div class="ao-rb">
    <span class="ao-rb-count">
        {{ number_format($total) }} {{ \Illuminate\Support\Str::plural('Record', $total) }} Found,
        Page {{ $page }} of {{ $pages }}
    </span>

    {{-- Always drawn, as the reference draws it — its Emails tab shows "Jump to Page: 1"
         over a single record. Hiding it on one page makes the band jump about as you
         move between tabs. --}}
    <span class="ao-rb-jump">
        Jump to Page:
        <select wire:model.live="page">
            @for ($p = 1; $p <= $pages; $p++)
                <option value="{{ $p }}">{{ $p }}</option>
            @endfor
        </select>
    </span>
</div>
