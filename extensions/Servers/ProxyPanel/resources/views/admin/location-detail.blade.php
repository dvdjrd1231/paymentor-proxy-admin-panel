{{--
    One location in full. The provider blocks are the reason this view exists: the list
    endpoint does not carry them, so they can only be seen per-location.

    Styled inline rather than with the `ao-*` classes from Others/AdminOps. Those live in a
    different extension, and a modal that renders unstyled whenever AdminOps happens to be
    disabled is not a dependency worth taking for one small table.
--}}
@php
    // The panel's real provider set — `linode` appears in the client's API doc but not on
    // the panel, which rejects it. Verified live; see PanelLocations::PROVIDERS.
    $providers = ['do' => 'DigitalOcean', 'vultr' => 'Vultr', 'sevencloud' => 'SevenCloud'];
    $fields = [
        'Tag' => $detail['tag'] ?? $row['tag'] ?? null,
        'Panel ID' => $detail['id'] ?? null,
        'Continent' => $detail['continent'] ?? null,
        'Country' => trim(($detail['country_name'] ?? '') . ' (' . ($detail['country'] ?? '') . ')', ' ()') ?: null,
        'State' => $detail['state'] ?? null,
        'City' => $detail['city'] ?? null,
        'Tunnels' => isset($detail['total'])
            ? $detail['total'] . ' total · ' . ($detail['used'] ?? 0) . ' used · ' . ($detail['free'] ?? 0) . ' free'
            : null,
    ];

    $label = 'font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;opacity:.65;';
    $value = 'font-weight:500;overflow-wrap:anywhere;';
    $cell = 'padding:.45rem .6rem;text-align:left;';
    $head = $cell . 'font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;opacity:.65;border-bottom:1px solid currentColor;';
@endphp

<div style="display:flex;flex-direction:column;gap:1.25rem;font-size:.875rem;">
    @if (!empty($detail['error']))
        <p style="color:hsl(var(--color-error));">{{ $detail['error'] }}</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.75rem 1.5rem;">
            @foreach ($fields as $name => $text)
                @if (filled($text))
                    <div>
                        <div style="{{ $label }}">{{ $name }}</div>
                        <div style="{{ $value }}">{{ $text }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="{{ $head }}">Provider</th>
                        <th style="{{ $head }}">Priority 1</th>
                        <th style="{{ $head }}">Priority 2</th>
                        <th style="{{ $head }}">Priority 3</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($providers as $key => $name)
                        <tr style="border-top:1px solid hsl(var(--color-neutral) / .5);">
                            <td style="{{ $cell }}font-weight:500;">{{ $name }}</td>
                            @foreach (['prio1', 'prio2', 'prio3'] as $prio)
                                <td style="{{ $cell }}">{{ $detail[$key][$prio] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
