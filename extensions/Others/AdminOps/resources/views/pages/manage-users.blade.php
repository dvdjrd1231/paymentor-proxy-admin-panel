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
                    <input type="text" wire:model="q">
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
            <span>{{ number_format($users->total()) }} Records Found</span>
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
                        <td><a href="{{ $summary }}">{{ $user->email }}</a></td>
                        <td>
                            <span class="ao-mu-status {{ $user->tfa_secret ? 'ao-mu-active' : 'ao-mu-inactive' }}">
                                {{ $user->tfa_secret ? 'Enabled' : 'Disabled' }}
                            </span>
                        </td>
                        <td>
                            @if ($seen)
                                <time title="{{ $seen->format('Y-m-d H:i:s') }}">{{ $seen->format('d/m/Y H:i') }}</time>
                            @else
                                Never
                            @endif
                        </td>
                        <td class="ao-mu-actions">
                            <a href="{{ $summary }}">Summary</a>
                            <a href="{{ $edit }}">Edit</a>
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
