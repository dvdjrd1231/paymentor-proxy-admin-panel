{{--
    The reference's dropdown, over a native <select> Windows/Chrome won't let us style.

    Why this exists: a native <option> popup is drawn by the OS on Windows, not the page —
    no font-family, no font-size, no colour, no flag webfont crosses that boundary. Every
    "the dropdown looks nothing like WHMCS" and "the flag doesn't show" report traced back
    to that one fact once ProxyPanel's own CountryFlag helper spelled it out: "Only an
    image-based custom dropdown could change that." This is that dropdown — plain HTML/CSS
    list items the page fully controls, laid over Alpine, wired to Livewire the same way
    the datepicker is: `$wire.entangle`, not a hidden native <select> plus synthetic events.

    Included with:
      @include('adminops::partials.select', [
          'model' => 'orderStatus',    // the Livewire property this binds to
          'live' => false,             // true = wire:model.live semantics, false = deferred
          'options' => $flatOptions,   // [['value'=>.., 'label'=>.., 'disabled'=>bool, 'group'=>bool], ...]
          'placeholder' => 'None',     // shown when nothing matches the current value
          'id' => 'ao-ano-status',
          // 'key' => 'a wire:key — see below; only needed when $options can change',
      ])

    `$options` is already flattened by the caller — a plain row per item, and a `group: true`
    row (no value) wherever a heading belongs, since the shapes an Eloquent collection, a
    ConfigOption tree and a server's checkout-field array come in are different enough that
    normalising them here would need three separate branches anyway.

    `key`: Alpine's morph (what Livewire v3 diffs DOM with) deliberately *keeps* an
    element's existing x-data state across a re-render — that is what stops every
    Livewire update from resetting every open dropdown, but it also means the
    `options:` array snapshotted at init time survives untouched even when the server
    sends genuinely different options (Billing Cycle after the product changes; Region
    after the product's server changes). Pass a `key` that includes whatever the
    options depend on — Livewire tears down and rebuilds a widget whose `wire:key`
    changed rather than morphing it, which is exactly "please re-read the new
    options". Omit it for a list that cannot change under this component (Client,
    Payment Method, Order Status).
--}}
<span class="ao-xsel" @if (isset($key)) wire:key="{{ $key }}" @endif x-data="{
        open: false,
        value: $wire.entangle('{{ $model }}'){{ !empty($live) ? '.live' : '' }},
        options: @js(collect($options)->values()->all()),
        label() {
            const hit = this.options.find((o) => !o.group && String(o.value) === String(this.value));
            return hit ? hit.label : {{ \Illuminate\Support\Js::from($placeholder ?? '') }};
        },
        pick(o) {
            if (o.group || o.disabled) return;
            this.value = o.value;
            this.open = false;
            this.$refs.btn.focus();
        },
        move(step) {
            const pickable = this.options.filter((o) => !o.group && !o.disabled);
            const at = pickable.findIndex((o) => String(o.value) === String(this.value));
            const next = pickable[Math.min(pickable.length - 1, Math.max(0, at + step))];
            if (next) this.value = next.value;
        },
    }"
    @click.outside="open = false"
>
    <button type="button" class="ao-xsel-btn" x-ref="btn" @click="open = !open"
        @keydown.escape="open = false" @keydown.down.prevent="open ? move(1) : (open = true)"
        @keydown.up.prevent="open ? move(-1) : null" @keydown.enter.prevent="open = false"
        role="combobox" aria-haspopup="listbox" :aria-expanded="open ? 'true' : 'false'"
        @if (isset($id)) id="{{ $id }}" @endif>
        <span class="ao-xsel-label" x-text="label()"></span>
        <svg class="ao-xsel-chev" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true">
            <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
    </button>
    <ul class="ao-xsel-list" x-show="open" x-cloak x-transition.opacity.duration.100ms role="listbox">
        {{-- One loop, original order preserved — a group heading is just a row that
             can't be picked, not a second pass that would scatter headings to the top. --}}
        <template x-for="(o, i) in options" :key="i">
            <li role="option" x-text="o.label"
                :class="o.group ? 'ao-xsel-group' : ('ao-xsel-opt' + (String(o.value) === String(value) ? ' ao-on' : '') + (o.disabled ? ' ao-off' : ''))"
                :aria-selected="!o.group && String(o.value) === String(value) ? 'true' : 'false'"
                @click="pick(o)"></li>
        </template>
    </ul>
</span>
