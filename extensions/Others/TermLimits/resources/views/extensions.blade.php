{{--
    Every grant of extra time on one term, newest first.

    Shown rather than summarised: the reason is the point of the record, and a total number
    of hours with the reasons collapsed behind it would answer the easy question and hide
    the one anybody actually asks later — who gave this customer another day, and why.
--}}
<div class="ao-panel" style="display: flex; flex-direction: column; gap: 0.75rem;">
    @foreach ($extensions as $extension)
        <div style="border-inline-start: 3px solid hsl(var(--color-primary)); padding-inline-start: 0.75rem;">
            <div style="font-weight: 600;">
                +{{ $extension->hours }} {{ Str::plural('hour', $extension->hours) }}
                <span style="font-weight: 400; opacity: 0.7;">
                    · {{ $extension->admin?->name ?? 'account since removed' }}
                    · {{ $extension->created_at->toDayDateTimeString() }}
                </span>
            </div>
            <div style="white-space: pre-wrap;">{{ $extension->reason }}</div>
        </div>
    @endforeach
</div>
