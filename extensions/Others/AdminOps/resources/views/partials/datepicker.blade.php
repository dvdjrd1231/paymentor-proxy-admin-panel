{{--
    The reference's calendar, shared by every date field. Entirely client-side Alpine —
    opening, paging months and picking days never leave the browser, which is what makes
    it feel like the reference's; the one server call is the final $wire.set that lands
    the chosen value in the Livewire property.

    Included with:
      @include('adminops::partials.datepicker', [
          'model' => 'dates',          // the Livewire property the value lands in
          'range' => true,             // two months + Apply; false = one month, click picks
          'id' => 'ao-of-dates',
          'placeholder' => 'MM/DD/YYYY - MM/DD/YYYY',
          'class' => 'ao-of-lg',
      ])
--}}
<span class="ao-of-date" x-data="{
        open: false,
        range: {{ !empty($range) ? 'true' : 'false' }},
        base: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        start: null,
        end: null,
        pad(n) { return String(n).padStart(2, '0'); },
        fmt(d) { return this.pad(d.getMonth() + 1) + '/' + this.pad(d.getDate()) + '/' + d.getFullYear(); },
        label() {
            if (!this.start) return '';
            return this.range ? this.fmt(this.start) + ' - ' + this.fmt(this.end ?? this.start) : this.fmt(this.start);
        },
        parse(text) {
            const m = String(text ?? '').match(/(\d{2})\/(\d{2})\/(\d{4})(?:\s*-\s*(\d{2})\/(\d{2})\/(\d{4}))?/);
            if (!m) return;
            this.start = new Date(+m[3], +m[1] - 1, +m[2]);
            this.end = m[4] ? new Date(+m[6], +m[4] - 1, +m[5]) : null;
            this.base = new Date(this.start.getFullYear(), this.start.getMonth(), 1);
        },
        show() { this.parse(this.$refs.input.value); this.open = true; },
        months() {
            const list = [];
            for (let i = 0; i < (this.range ? 2 : 1); i++) {
                list.push(new Date(this.base.getFullYear(), this.base.getMonth() + i, 1));
            }
            return list;
        },
        weeks(month) {
            const cursor = new Date(month.getFullYear(), month.getMonth(), 1);
            cursor.setDate(1 - cursor.getDay());
            const rows = [];
            for (let w = 0; w < 6; w++) {
                const row = [];
                for (let d = 0; d < 7; d++) { row.push(new Date(cursor)); cursor.setDate(cursor.getDate() + 1); }
                rows.push(row);
            }
            return rows;
        },
        nav(step) { this.base = new Date(this.base.getFullYear(), this.base.getMonth() + step, 1); },
        setMonth(side, value) { this.base = new Date(this.base.getFullYear(), Number(value) - side, 1); },
        setYear(side, value) { const m = this.base.getMonth() - side; this.base = new Date(Number(value) + Math.floor(m / 12), ((m % 12) + 12) % 12, 1); },
        same(a, b) { return a && b && a.toDateString() === b.toDateString(); },
        cls(day, month) {
            const out = day.getMonth() !== month.getMonth() ? 'ao-dr-out ' : '';
            if (this.same(day, this.start) || this.same(day, this.end)) return out + 'ao-dr-edge';
            if (this.start && this.end && day > this.start && day < this.end) return out + 'ao-dr-in';
            return out;
        },
        pick(day) {
            if (!this.range) { this.start = day; this.apply(); return; }
            if (!this.start || this.end) { this.start = day; this.end = null; }
            else if (day < this.start) { this.end = this.start; this.start = day; }
            else { this.end = day; }
        },
        preset(key) {
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const ago = (days) => { const d = new Date(today); d.setDate(d.getDate() - days); return d; };
            const pairs = {
                today: [today, today],
                yesterday: [ago(1), ago(1)],
                ago7: [ago(7), ago(7)],
                last7: [ago(6), today],
                last30: [ago(29), today],
                this_month: [new Date(today.getFullYear(), today.getMonth(), 1), today],
                month_ago: this.range
                    ? [new Date(today.getFullYear(), today.getMonth() - 1, 1), new Date(today.getFullYear(), today.getMonth(), 0)]
                    : [new Date(today.getFullYear(), today.getMonth() - 1, today.getDate()), null],
                this_year: [new Date(today.getFullYear(), 0, 1), today],
                year_ago: this.range
                    ? [new Date(today.getFullYear() - 1, 0, 1), new Date(today.getFullYear() - 1, 11, 31)]
                    : [new Date(today.getFullYear() - 1, today.getMonth(), today.getDate()), null],
                custom: [null, null],
            };
            const [from, to] = pairs[key] ?? [null, null];
            if (!from) return; // Custom: leave the calendar open for hand-picking.
            this.start = from;
            this.end = this.range ? to : null;
            this.base = new Date(from.getFullYear(), from.getMonth(), 1);
            if (!this.range) this.apply();
        },
        apply() {
            if (this.start) this.$wire.set('{{ $model }}', this.label());
            this.open = false;
        },
        clearAll() { this.start = this.end = null; this.$wire.set('{{ $model }}', ''); this.open = false; },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"
        width="13" height="13" aria-hidden="true">
        <rect x="1.8" y="2.8" width="12.4" height="11.4" rx="1.5" />
        <path d="M1.8 6.2h12.4M5 1.2v3.2M11 1.2v3.2" />
    </svg>
    <input @nofill x-ref="input" id="{{ $id }}" class="{{ $class ?? 'ao-of-lg' }}" type="text"
        wire:model="{{ $model }}" x-on:click="show()" x-on:focus="show()"
        placeholder="{{ $placeholder ?? 'MM/DD/YYYY' }}">

    <div class="ao-dr" x-show="open" x-cloak>
        <ul class="ao-dr-presets">
            @foreach (!empty($range)
                ? ['today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 Days', 'last30' => 'Last 30 Days', 'this_month' => 'This Month', 'month_ago' => '1 Month Ago', 'this_year' => 'This Year', 'year_ago' => '1 Year Ago', 'custom' => 'Custom']
                : ['today' => 'Today', 'yesterday' => 'Yesterday', 'ago7' => '7 Days Ago', 'month_ago' => '1 Month Ago', 'year_ago' => '1 Year Ago', 'custom' => 'Custom'] as $key => $labelText)
                <li><button type="button" x-on:click="preset('{{ $key }}')">{{ $labelText }}</button></li>
            @endforeach
        </ul>
        <div class="ao-dr-main">
            <div class="ao-dr-months">
                <template x-for="(month, side) in months()" :key="month.getTime()">
                    <div class="ao-dr-month">
                        <div class="ao-dr-head">
                            <button type="button" class="ao-dr-nav" x-show="side === 0" x-on:click="nav(-1)" aria-label="Previous month">&lsaquo;</button>
                            <select x-on:change="setMonth(side, $event.target.value)">
                                <template x-for="m in 12" :key="m">
                                    <option :value="m - 1" :selected="m - 1 === month.getMonth()"
                                        x-text="new Date(2000, m - 1, 1).toLocaleString('en', { month: 'long' })"></option>
                                </template>
                            </select>
                            <select x-on:change="setYear(side, $event.target.value)">
                                <template x-for="y in 8" :key="y">
                                    <option :value="new Date().getFullYear() - 6 + y" :selected="new Date().getFullYear() - 6 + y === month.getFullYear()"
                                        x-text="new Date().getFullYear() - 6 + y"></option>
                                </template>
                            </select>
                            <button type="button" class="ao-dr-nav" x-show="side === (range ? 1 : 0)" x-on:click="nav(1)" aria-label="Next month">&rsaquo;</button>
                        </div>
                        <table class="ao-dr-grid">
                            <thead>
                                <tr>
                                    @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $dow)
                                        <th>{{ $dow }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(week, wi) in weeks(month)" :key="wi">
                                    <tr>
                                        <template x-for="day in week" :key="day.getTime()">
                                            <td><button type="button" :class="cls(day, month)"
                                                x-on:click="pick(day)" x-text="day.getDate()"></button></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
            <div class="ao-dr-foot">
                <span class="ao-dr-chosen" x-text="label()"></span>
                <button type="button" class="ao-dr-clear" x-on:click="clearAll()">Clear</button>
                <template x-if="range">
                    <button type="button" class="ao-dr-apply" x-on:click="apply()">Apply</button>
                </template>
            </div>
        </div>
    </div>
</span>
