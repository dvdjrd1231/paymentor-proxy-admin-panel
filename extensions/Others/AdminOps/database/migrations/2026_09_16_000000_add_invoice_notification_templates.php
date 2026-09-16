<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The invoice emails the reference offers and Paymenter ships no template for.
 *
 * Its Send Email picker lists sixteen; ours listed three, because three is all that
 * existed — the rest had no template to send (Leandro, 2026-09-16: "where invoice created
 * dropdown list fetched? if it is static, why did not you add list items from WHMCS?").
 * The list is not static: it names real templates, so the way to lengthen it is to write
 * the templates.
 *
 * Its card and direct-debit entries are deliberately not here. There is no card vault and
 * no direct debit on this platform, so "Credit Card Payment Pending" and its kin would be
 * offers to send a client something that can never be true of their account.
 *
 * Bodies are Markdown with Blade, as core's own templates are, and carry the same merge
 * tags the invoice notification hands them — $invoice, $items, $total.
 */
return new class extends Migration
{
    /** @var array<string, array{subject: string, title: string, pref: string, body: string}> */
    private const TEMPLATES = [
        'invoice_payment_reminder' => [
            'subject' => 'Payment reminder for invoice {{ $invoice->number }}',
            'title' => 'Payment reminder',
            'pref' => 'Remind me before an invoice falls due',
            'body' => <<<'MD'
# Payment reminder

This is a reminder that invoice **{{ $invoice->number }}** for **{{ $total }}** is due on {{ $invoice->due_at?->format('d/m/Y') }}.

<div class="action">
	<a class="button" href="{{ route('invoices.show', $invoice) }}">View invoice</a>
</div>

If you have already paid, please ignore this message.
MD,
        ],
        'invoice_overdue_first' => [
            'subject' => 'Invoice {{ $invoice->number }} is overdue',
            'title' => 'Invoice overdue',
            'pref' => 'Tell me when an invoice falls overdue',
            'body' => <<<'MD'
# Invoice overdue

Invoice **{{ $invoice->number }}** for **{{ $total }}** was due on {{ $invoice->due_at?->format('d/m/Y') }} and is now overdue.

<div class="action">
	<a class="button" href="{{ route('invoices.show', $invoice) }}">Pay now</a>
</div>
MD,
        ],
        'invoice_overdue_second' => [
            'subject' => 'Second notice: invoice {{ $invoice->number }} is overdue',
            'title' => 'Invoice still overdue',
            'pref' => 'Tell me when an invoice falls overdue',
            'body' => <<<'MD'
# Second notice

Invoice **{{ $invoice->number }}** for **{{ $total }}** remains unpaid since {{ $invoice->due_at?->format('d/m/Y') }}.

Please settle it to avoid interruption to your services.

<div class="action">
	<a class="button" href="{{ route('invoices.show', $invoice) }}">Pay now</a>
</div>
MD,
        ],
        'invoice_overdue_third' => [
            'subject' => 'Final notice: invoice {{ $invoice->number }} is overdue',
            'title' => 'Final overdue notice',
            'pref' => 'Tell me when an invoice falls overdue',
            'body' => <<<'MD'
# Final notice

Invoice **{{ $invoice->number }}** for **{{ $total }}** is still unpaid.

Services attached to this invoice may be suspended if it is not settled.

<div class="action">
	<a class="button" href="{{ route('invoices.show', $invoice) }}">Pay now</a>
</div>
MD,
        ],
        'invoice_refund_confirmation' => [
            'subject' => 'Refund issued for invoice {{ $invoice->number }}',
            'title' => 'Refund issued',
            'pref' => 'Tell me when a refund is issued',
            'body' => <<<'MD'
# Refund issued

A refund has been issued against invoice **{{ $invoice->number }}**.

Depending on the payment method, it can take a few days to appear on your statement.

<div class="action">
	<a class="button" href="{{ route('invoices.show', $invoice) }}">View invoice</a>
</div>
MD,
        ],
        'invoice_modified' => [
            'subject' => 'Invoice {{ $invoice->number }} has been updated',
            'title' => 'Invoice updated',
            'pref' => 'Tell me when an invoice changes',
            'body' => <<<'MD'
# Invoice updated

Invoice **{{ $invoice->number }}** has been changed. It now totals **{{ $total }}**.

<div class="table">

|   Item   |  Price   |
| :------: | :------: |
@foreach ($items as $item)
| {{ $item->description }} | {{ $item->price }} |
@endforeach
</div>

<div class="action">
	<a class="button" href="{{ route('invoices.show', $invoice) }}">View invoice</a>
</div>
MD,
        ],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $key => $template) {
            // Left alone if it is already there: this must not overwrite wording an admin
            // has since edited on the Notification Templates screen.
            if (DB::table('notification_templates')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('notification_templates')->insert([
                'key' => $key,
                'subject' => $template['subject'],
                'in_app_title' => $template['title'],
                'enabled' => 1,
                'mail_enabled' => 'choice_on',
                'in_app_enabled' => 'choice_on',
                'body' => $template['body'],
                'in_app_body' => $template['title'],
                'edit_preference_message' => $template['pref'],
                'in_app_url' => '{{ route("invoices.show", $invoice) }}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('key', array_keys(self::TEMPLATES))->delete();
    }
};
