{{--
    Manage Users, to the reference screenshot: one search field, the records line with Jump
    to Page, and the navy grid — ID, names, email, Two Factor, Last Login Time, Actions.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <form class="ao-find" wire:submit.prevent="search">
            <span class="ao-find-glass" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" width="18" height="18">
                    <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                </svg>
            </span>

            <div class="ao-find-fields">
                <label class="ao-find-field ao-find-grow">
                    <span>User Name/Email Address</span>
                    <input type="text" wire:model="q" placeholder="Name or email address">
                </label>
            </div>

            <button type="submit" class="ao-find-go">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" width="13" height="13" aria-hidden="true">
                    <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                </svg>
                Search
            </button>
        </form>

        <div class="ao-mu-line">
            <span>
                {{ number_format($users->total()) }} Records Found{{ $users->total() > 0 ? ', Showing ' . number_format($users->firstItem()) . ' to ' . number_format($users->lastItem()) : '' }}
            </span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $users->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $users->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>ID &#9662;</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email Address</th>
                    <th>Two Factor</th>
                    <th>Last Login Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php
                        $summary = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $user->id]);
                        $edit = \App\Admin\Resources\UserResource::getUrl('edit', ['record' => $user->id]);
                        $seen = $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at) : null;
                    @endphp
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td><a href="{{ $summary }}">{{ $user->first_name ?: '—' }}</a></td>
                        <td><a href="{{ $summary }}">{{ $user->last_name ?: '—' }}</a></td>
                        <td>
                            <a href="{{ $summary }}">{{ $user->email }}</a>
                            <i class="ao-mu-mail {{ $user->email_verified_at ? 'ao-mu-mail-ok' : 'ao-mu-mail-no' }}">
                                {{ $user->email_verified_at ? 'Email Verified' : 'Email Unverified' }}
                            </i>
                        </td>
                        <td>
                            {{-- The reference's shield-and-word, not a pill: grey when off, green when on. --}}
                            <span class="ao-mu-2fa {{ $user->tfa_secret ? 'ao-mu-2fa-on' : '' }}">
                                <svg viewBox="0 0 16 16" fill="currentColor" width="12" height="12" aria-hidden="true">
                                    <path d="M8 1 2.5 3v4.1c0 3.4 2.3 6.4 5.5 7.4 3.2-1 5.5-4 5.5-7.4V3L8 1Z"/>
                                </svg>
                                {{ $user->tfa_secret ? 'Enabled' : 'Disabled' }}
                            </span>
                        </td>
                        <td>
                            @if ($seen)
                                <time title="{{ $seen->format('Y-m-d H:i:s') }}">{{ $seen->format('m/d/Y H:i') }}</time>
                            @else
                                Never
                            @endif
                        </td>
                        <td class="ao-mu-actions">
                            <details class="ao-mu-manage">
                                <summary>Manage User <span aria-hidden="true">&#9662;</span></summary>
                                <div class="ao-mu-manage-menu">
                                    <a href="{{ $summary }}">Summary</a>
                                    <a href="{{ $edit }}">Edit</a>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $users->currentPage() - 1 }})"
                @disabled($users->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $users->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $users->currentPage() + 1 }})"
                @disabled(!$users->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
