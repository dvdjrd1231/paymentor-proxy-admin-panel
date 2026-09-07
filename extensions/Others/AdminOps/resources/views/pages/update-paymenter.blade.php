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
            {{-- Labelled for what it does. It used to say "Update Now" and queue a To-Do
                 row, which read as though an update had been scheduled when nothing had
                 happened but a reminder (Leandro, 2026-09-07). See
                 UpdatePaymenter::updateNow() for why applying cannot happen from here. --}}
            <button type="button" class="ao-up-update ao-find-go" wire:click="updateNow"
                wire:loading.attr="disabled" wire:target="updateNow,buildPlan">
                <span wire:loading.remove wire:target="updateNow,buildPlan">Check What {{ $latest ?? 'the update' }} Changes</span>
                <span wire:loading wire:target="updateNow,buildPlan">Downloading release…</span>
            </button>
            <div class="ao-up-links">
                <a href="{{ $releaseNotesUrl }}" target="_blank" rel="noopener">Release Notes</a>
                <a href="{{ $changelogUrl }}" target="_blank" rel="noopener">Changelog</a>
            </div>
        </div>

        <div class="ao-up-warning">
            <x-filament::icon icon="ri-error-warning-fill" class="ao-up-warning-ic" />
            <span>
                <strong>Why this page does not apply the update</strong>
                The application directory is bind-mounted from the server's git checkout, so a
                file written here would land in that checkout and the next deploy's
                <code>git pull</code> would refuse to fast-forward. It would also bypass the review
                that vendoring a release exists to provide, silently replacing the files listed
                below as <b>ours</b>. Applying is a repository operation: vendor the release, merge
                those files by hand, commit, deploy. This page tells you exactly what that involves.
            </span>
        </div>

        {{-- What the release would actually change, file by file. Update Now downloads it
             and builds this; it is the honest form of "update only possible and necessary
             files" on a deployment whose core is vendored and git-managed. --}}
        @if (($plan['files'] ?? null) !== null && !($plan['error'] ?? null) && $plan['counts'] !== [])
            @php $counts = $plan['counts']; @endphp
            <div class="ao-up-plan">
                <div class="ao-up-plan-head">
                    <h4>What release {{ $plan['version'] }} would change</h4>
                    <button type="button" class="ao-pg-btn" wire:click="clearPlan">Dismiss</button>
                </div>

                <div class="ao-up-plan-counts">
                    <span><b>{{ $counts['same'] ?? 0 }}</b> already identical</span>
                    <span><b>{{ $counts['changed'] ?? 0 }}</b> safe to take</span>
                    <span><b>{{ $counts['new'] ?? 0 }}</b> new</span>
                    <span class="ao-up-plan-warn"><b>{{ $counts['touchpoint'] ?? 0 }}</b> carry our own changes</span>
                </div>

                @if (($counts['touchpoint'] ?? 0) > 0)
                    <p class="ao-up-plan-note">
                        The files marked <b>ours</b> below are recorded in
                        <code>docs/CORE-TOUCHPOINTS.md</code>. Taking upstream's copy of one of them
                        would silently remove a customisation, so they are listed for a person to
                        merge rather than applied.
                    </p>
                @endif

                <table class="ao-mu-grid">
                    <thead><tr><th>File</th><th>State</th></tr></thead>
                    <tbody>
                        @forelse (array_slice($plan['files'], 0, 300) as $file)
                            <tr>
                                <td class="ao-mu-left"><code>{{ $file['path'] }}</code></td>
                                <td>
                                    <span class="ao-mu-status ao-up-st-{{ $file['state'] }}">
                                        {{ ['touchpoint' => 'ours', 'changed' => 'changed', 'new' => 'new'][$file['state']] ?? $file['state'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="ao-mu-none">Nothing differs — this install already matches the release.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if (count($plan['files']) > 300)
                    {{-- Said out loud rather than silently truncated: a cut-off list that
                         looks complete is worse than no list. --}}
                    <p class="ao-up-plan-note">Showing the first 300 of {{ count($plan['files']) }} differing files.</p>
                @endif
            </div>
        @endif


        @if ($checkedAt)
            <p class="ao-up-checked">Last Checked for Updates: {{ $checkedAt->diffForHumans() }}</p>
        @endif
    </div>
</x-filament-panels::page>
