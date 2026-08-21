{{--
    WHMCS-style design system for the Proxy theme.

    Hand-written CSS (not Tailwind) on purpose: this child theme reuses the default
    theme's *pre-compiled* Vite bundle, so any new Tailwind class we invented would
    not exist in that build. Plain CSS always works and needs no build step.

    BRANDING: change --brand (and --brand-dark) below to your brand colour.
--}}
<style>
    /*
       Country flags on region labels ("🇬🇧  United Kingdom - Birmingham").

       Windows ships no flag glyphs, so every browser there falls back to rendering the two
       regional-indicator letters — "GB" instead of the flag. This loads a Twemoji subset
       that contains only that range and, thanks to unicode-range, the browser downloads and
       applies it *only* for those characters: all other text keeps the normal system font,
       and there is no download at all on platforms that already have flags.

       `local()` first so macOS/Android/Linux use their built-in flags and skip the request.
    */
    @if (Route::has('extensions.servers.proxypanel.flagfont'))
    @font-face {
        font-family: 'TwemojiCountryFlags';
        src: local('Twemoji Country Flags'),
             url('{{ route('extensions.servers.proxypanel.flagfont') }}') format('woff2');
        unicode-range: U+1F1E6-1F1FF;   /* REGIONAL INDICATOR SYMBOL LETTER A–Z */
        font-display: swap;
    }
    @endif

    /*
       Open Sans is the reference portal's typeface. Shipped with the theme rather than
       fetched from a font CDN: no third-party request per page load, and it keeps working
       offline and under a strict CSP. One variable file covers 300-800, so the whole
       family is a single 48KB request.
    */
    @if (Route::has('extensions.others.portal.font'))
    @font-face {
        font-family: 'Open Sans';
        src: url('{{ route('extensions.others.portal.font') }}') format('woff2');
        font-weight: 300 800;          /* variable: one file, every weight */
        font-style: normal;
        font-display: swap;
    }
    @endif

    /* Raleway carries the menu bar and every page heading on the reference portal. */
    @if (Route::has('extensions.others.portal.font.heading'))
    @font-face {
        font-family: 'Raleway';
        src: url('{{ route('extensions.others.portal.font.heading') }}') format('woff2');
        font-weight: 100 900;
        font-style: normal;
        font-display: swap;
    }
    @endif
</style>

{{--
    The other ~60KB of this design system is a static stylesheet served from
    themes/proxy/assets/whmcs.css, not inlined here.

    It used to sit in the <style> block above, which meant every single page carried it:
    63KB of the 83KB login page — 76% of the response — re-sent on every request and
    impossible for the browser to cache. As a linked file it is fetched once and then
    served from cache, and each page drops to roughly a quarter of its previous size.

    Only the @font-face rules stay inline, because they interpolate route() URLs and so
    cannot live in a static file.

    The `v` parameter is the file's own content hash, so a CSS edit busts the cache
    immediately while the response itself is marked immutable for a year.
--}}
@if (Route::has('extensions.others.portal.css'))
    <link rel="stylesheet" href="{{ route('extensions.others.portal.css', ['v' => \Paymenter\Extensions\Others\PortalBehavior\PortalBehavior::styleVersion()]) }}">
@else
    {{-- Portal Behavior owns that route, so disabling it would otherwise leave the whole
         site unstyled. Falling back to inlining the same file keeps every page looking
         right — it just gives up the caching until the extension is enabled again. --}}
    @php $whmcsCss = base_path('themes/proxy/assets/whmcs.css'); @endphp
    @if (is_file($whmcsCss))
        <style>{!! file_get_contents($whmcsCss) !!}</style>
    @endif
@endif
