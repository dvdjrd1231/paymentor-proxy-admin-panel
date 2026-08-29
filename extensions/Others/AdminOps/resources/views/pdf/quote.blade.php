{{-- The quote as a document: header, parties, proposal text, the priced lines, the
     footer notes. Plain dompdf-safe HTML, mirroring core's invoice PDF in tone. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #2b2b2b; margin: 28px; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .muted { color: #777; }
        .row { width: 100%; margin: 18px 0; }
        .row td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.lines th { background: #1a4d80; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.lines td { border-bottom: 1px solid #ddd; padding: 6px 8px; }
        .num { text-align: right; }
        .sums { margin-top: 8px; width: 40%; margin-left: 60%; border-collapse: collapse; }
        .sums td { padding: 4px 8px; }
        .sums .due td { font-weight: bold; border-top: 1px solid #2b2b2b; }
        .notes { margin-top: 22px; font-size: 11px; color: #555; }
    </style>
</head>
<body>
    <h1>Quotation #{{ $quote->id }}</h1>
    <div class="muted">
        {{ $quote->subject }} ·
        Created {{ $quote->created_at?->format('m/d/Y') }}
        @if ($quote->valid_until) · Valid until {{ $quote->valid_until->format('m/d/Y') }} @endif
    </div>

    <table class="row">
        <tr>
            <td width="50%">
                <strong>{{ config('app.name') }}</strong><br>
                <span class="muted">Quotation for:</span><br>
                {{ trim(($quote->user->first_name ?? '') . ' ' . ($quote->user->last_name ?? '')) ?: $quote->user->email }}<br>
                @php $prop = fn (string $key) => $quote->user->properties->firstWhere('key', $key)?->value; @endphp
                @if ($prop('company_name')) {{ $prop('company_name') }}<br> @endif
                @if ($prop('address')) {{ $prop('address') }}<br> @endif
                @if ($prop('city') || $prop('zip')) {{ trim($prop('city') . ' ' . $prop('zip')) }}<br> @endif
                @if ($prop('country')) {{ $prop('country') }}<br> @endif
                {{ $quote->user->email }}
            </td>
            <td width="50%" class="num">
                <span class="muted">Status:</span> {{ ucfirst($quote->status) }}
            </td>
        </tr>
    </table>

    @if ($quote->proposal_text)
        <p>{!! nl2br(e($quote->proposal_text)) !!}</p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th>Qty</th>
                <th>Description</th>
                <th class="num">Unit Price</th>
                <th class="num">Discount</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td>{{ (int) $item->quantity }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">${{ number_format((float) $item->price, 2) }}</td>
                    <td class="num">{{ (float) ($item->discount ?? 0) > 0 ? number_format((float) $item->discount, 2) . '%' : '—' }}</td>
                    <td class="num">${{ number_format($item->total(), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sums">
        <tr><td>Sub Total</td><td class="num">${{ number_format($quote->total(), 2) }} {{ $quote->currency_code }}</td></tr>
        <tr class="due"><td>Total Due</td><td class="num">${{ number_format($quote->total(), 2) }} {{ $quote->currency_code }}</td></tr>
    </table>

    @if ($quote->customer_notes)
        <div class="notes">{!! nl2br(e($quote->customer_notes)) !!}</div>
    @endif
</body>
</html>
