{{--
    Tailwind palette for this theme.

    The default theme derives these from the admin's colour settings, which default to a
    blue that has nothing to do with the reference portal — and any page still rendered by
    the default theme (account security, credits, payment methods, 2FA, password reset,
    error pages, the invoice payment modal…) paints its buttons, focus rings and links
    with them. Pinning them to the WHMCS palette here keeps those pages on-brand without
    depending on someone setting the colours in the admin, and without editing core.

    Values are the reference portal's own colours, as `H S% L%` triples (the format
    Tailwind's `hsl(var(--color-…))` expects). Light only: the WHMCS design has no dark
    mode, so `.dark` repeats the light values rather than inverting to a dark surface.
--}}
@php
    $palette = [
        'primary' => '347 84% 56%',            // #ED2F59 brand
        'secondary' => '347 69% 64%',          // #e2627e brand hover/border
        'neutral' => '0 0% 87%',               // #dddddd borders
        'base' => '0 0% 33%',                  // #555555 body text
        'muted' => '0 0% 47%',                 // #777777 secondary text
        'inverted' => '0 0% 100%',
        'background' => '0 0% 100%',
        'background-secondary' => '0 0% 97%',  // #f8f8f8 page frame
    ];
@endphp
<style>
    :root,
    .dark {
        @foreach ($palette as $name => $value)
        --color-{{ $name }}: {{ $value }};
        @endforeach

        /* State colours stay conventional — success/error have to read as such. */
        --color-success: 142 71% 45%;
        --color-error: 0 75% 60%;
        --color-warning: 25 95% 53%;
        --color-inactive: 0 0% 63%;
        --color-info: 210 100% 60%;
    }
</style>
