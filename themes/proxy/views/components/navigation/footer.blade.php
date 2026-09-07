{{-- WHMCS-style footer (overrides <x-navigation.footer /> from the default theme). --}}
@php
    // The Social tab of General Settings. Only networks that have been filled in appear —
    // an empty row of dead icons is worse than no row at all. Each entry maps a stored
    // value to the URL that value belongs in, because the reference stores handles and
    // workspace ids rather than full links.
    $socialLinks = collect([
        'facebook' => ['Facebook', 'https://facebook.com/'],
        'twitter' => ['Twitter', 'https://twitter.com/'],
        'instagram' => ['Instagram', 'https://instagram.com/'],
        'linkedin' => ['LinkedIn', 'https://www.linkedin.com/company/'],
        'youtube' => ['YouTube', 'https://youtube.com/'],
        'github' => ['GitHub', 'https://github.com/'],
        'bitbucket' => ['BitBucket', 'https://bitbucket.org/'],
        'gitter' => ['Gitter', 'https://gitter.im/'],
        'flickr' => ['Flickr', 'https://flickr.com/photos/'],
        'reddit' => ['Reddit', 'https://reddit.com/user/'],
        'vimeo' => ['Vimeo', 'https://vimeo.com/'],
        'discord' => ['Discord', 'https://discord.gg/'],
        'slack' => ['Slack', 'https://slack.com/app_redirect?team='],
        'skype' => ['Skype', 'skype:'],
        'viber' => ['Viber', 'viber://chat?number='],
        'whatsapp' => ['WhatsApp', 'https://wa.me/'],
    ])->map(function (array $network, string $key) {
        $value = trim((string) config('settings.social_' . $key, ''));

        if ($value === '') {
            return null;
        }

        [$label, $prefix] = $network;

        // A full URL is used as given; anything else is a handle and gets its prefix.
        $url = str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : $prefix . ltrim($value, '@/');

        return ['label' => $label, 'url' => $url];
    })->filter();
@endphp

<footer class="wf-footer">
    <div class="wf-shell wf-footer-inner">
        <p class="wf-footer-copy">
            Copyright &copy; {{ date('Y') }} {{ config('app.name', 'Paymenter') }}. All Rights Reserved.
        </p>

        @if ($socialLinks->isNotEmpty())
            <nav class="wf-footer-social" aria-label="{{ __('Social') }}">
                @foreach ($socialLinks as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <button type="button" class="wf-totop" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
            aria-label="Back to top">▲</button>
    </div>
</footer>
