{{--
    Update Paymenter, to the reference's Update WHMCS screen (issue #27): verdict line,
    the grey/blue version tiles, Update Now with the release links under it, and the
    last-checked line. The warning band carries the one truth specific to this install —
    it updates from source control, not from a web updater.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-up">
        <div class="ao-up-verdict {{ $upToDate ? 'ao-up-ok' : 'ao-up-new' }}">
            <span class="ao-up-verdict-ic">
                <x-filament::icon :icon="$upToDate ? 'ri-checkbox-circle-fill' : 'ri-information-fill'" />
            </span>
            @if ($latest === null)
                Could not reach the update service
            @elseif ($upToDate)
                You are up to date
            @else
                An update is available
            @endif
        </div>

        <div class="ao-up-tiles">
            <div class="ao-up-tile ao-up-yours">
                <div class="ao-up-tile-head">Your Version</div>
                <div class="ao-up-tile-body">
                    <span class="ao-up-figure">{{ $current }}</span>
                    <span class="ao-up-line">General Release</span>
                    {{-- The tagged production release, as the reference shows its own —
                         not the git build hash, which read as running a development
                         version (Leandro, 2026-09-04). --}}
                    <span class="ao-up-sub">{{ $current }}-release</span>
                </div>
            </div>
            <div class="ao-up-tile ao-up-latest">
                <div class="ao-up-tile-head">Latest Version</div>
                <div class="ao-up-tile-body">
                    <span class="ao-up-figure">{{ $latest ?? '—' }}</span>
                    <span class="ao-up-line">General Release</span>
                    <span class="ao-up-sub">{{ $latest ? $latest . '-release' : 'unavailable' }}</span>
                </div>
            </div>
        </div>

        <div class="ao-up-actions">
            <span class="ao-up-update ao-tx-tab-dead"
                title="This install is deployed from source control — updates are vendored into the repository and shipped through the deployment pipeline, not applied by a web updater">
                Update Now
            </span>
            <div class="ao-up-links">
                <a href="{{ $releaseNotesUrl }}" target="_blank" rel="noopener">Release Notes</a>
                <a href="{{ $changelogUrl }}" target="_blank" rel="noopener">Changelog</a>
            </div>
        </div>

        <div class="ao-up-warning">
            <x-filament::icon icon="ri-error-warning-fill" class="ao-up-warning-ic" />
            <span>
                <strong>Note</strong>
                This installation always runs tagged production releases — development builds
                are never deployed to production. New releases are vendored into the
                repository, reviewed, and shipped through the deployment pipeline, so the
                web updater is deliberately not used here and nothing on this page changes files.
            </span>
        </div>

        @if ($checkedAt)
            <p class="ao-up-checked">Last Checked for Updates: {{ $checkedAt->diffForHumans() }}</p>
        @endif
    </div>
</x-filament-panels::page>
