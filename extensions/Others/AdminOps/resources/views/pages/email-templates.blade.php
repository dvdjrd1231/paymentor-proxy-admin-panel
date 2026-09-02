{{--
    Email Templates, to issue #48's reference: the message categories as two columns of
    navy mini-grids — Status, Template Name, the edit icon — over Paymenter's real
    notification templates.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        @if ($newUrl)
            <div class="ao-tx-tabs">
                <a class="ao-mu-tab" href="{{ $newUrl }}">&#10010; Create New Email Template</a>
            </div>
        @endif

        <div class="ao-et-cols">
            @foreach ($sections as $title => $rows)
                <section class="ao-et-section">
                    <h4 class="ao-ano-heading">{{ $title }}</h4>
                    <table class="ao-mu-grid">
                        <thead>
                            <tr><th class="ao-et-status">Status</th><th>Template Name</th><th class="ao-et-icon"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $template)
                                @php $editUrl = $edit($template); @endphp
                                <tr>
                                    <td class="ao-et-status">
                                        <span class="ao-et-dot {{ $template->enabled ? 'ao-on' : 'ao-off' }}"
                                            title="{{ $template->enabled ? 'Enabled' : 'Disabled' }}">{{ $template->enabled ? '✔' : '✖' }}</span>
                                    </td>
                                    <td class="ao-mu-left">
                                        @if ($editUrl)
                                            <a href="{{ $editUrl }}">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::label($template) }}</a>
                                        @else
                                            {{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::label($template) }}
                                        @endif
                                    </td>
                                    <td class="ao-et-icon">
                                        @if ($editUrl)
                                            <a href="{{ $editUrl }}" title="Edit template">
                                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
