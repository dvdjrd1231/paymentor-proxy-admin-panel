{{-- The reference's "« Previous Page" / "Next Page »" pair under every client-profile list. --}}
@php
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
@endphp

<div class="ao-rb-pager">
    <button type="button" class="ao-pg-btn" @disabled($page <= 1)
        wire:click="$set('page', {{ max(1, $page - 1) }})">&laquo; Previous Page</button>

    <button type="button" class="ao-pg-btn" @disabled($page >= $pages)
        wire:click="$set('page', {{ min($pages, $page + 1) }})">Next Page &raquo;</button>
</div>
