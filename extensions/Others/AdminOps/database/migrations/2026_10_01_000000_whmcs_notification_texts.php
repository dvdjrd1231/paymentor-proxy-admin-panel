<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The notification wording, as the reference writes it.
 *
 * Leandro, #48: "Can you configure the texts to be the same as WHMCS in all notifications?"
 * The reference ships its defaults in install/sql/emailtemplates.sql, so this is a port of
 * that text rather than an invention: same salutation, same paragraphs, same order of the
 * detail lines, same closing sentence.
 *
 * Three things do not come across literally, and are deliberate:
 *
 *  - Merge tags. The reference writes [CustomerName] and [InvoiceNo]; the same values here
 *    are Blade against the payload the notification is actually given. Every expression
 *    below was rendered against a live record before it was written down.
 *  - The signature. The reference closes each mail with [Signature]; ours is already in the
 *    shared mail footer, so repeating it per template would sign every mail twice.
 *  - Card wording. The reference's payment-failure and overdue text talks about the card on
 *    record and mails the client their password. There is no card vault here and we do not
 *    put passwords in mail, so those sentences are dropped rather than reworded.
 *
 * Six of ours have no counterpart in the reference at all -- it has no verification, reset,
 * login-alert, cancellation, refund or invoice-changed mail. Those are written in the same
 * house style (salutation, plain paragraphs, no heading) so the set reads as one voice.
 *
 * The previous subject and body of every row is kept in ext_ao_meta first, so this is
 * reversible: down() puts back exactly what was there.
 */
return new class extends Migration
{
    private const BACKUP_KEY = 'whmcs_text_backup';

    private const BACKUP_TYPE = 'App\Models\NotificationTemplate';

    /** @var array<string, array{subject: string, body: string}> */
    private const TEMPLATES = [
        // ---- Invoices ------------------------------------------------------------
        'new_invoice_created' => [
            'subject' => 'Customer Invoice',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a notice that an invoice has been generated on {{ $invoice->created_at->format('d/m/Y') }}.

                Your payment method is: {{ $invoice->transactions->last()?->gateway?->name ?: 'Not selected' }}

                Invoice #{{ $invoice->number }}<br />
                Amount Due: {{ $invoice->formattedRemaining }}<br />
                Due Date: {{ $invoice->due_at?->format('d/m/Y') }}

                **Invoice Items**

                <div class="table">

                |   Item   | Quantity |  Price   |
                | :------: | :------: | :------: |
                @foreach ($items as $item)
                | {{ $item->description }} | {{ $item->quantity }} | {{ $item->price }} |
                @endforeach
                </div>

                You can login to your client area to view and pay the invoice.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		View Invoice
                	</a>
                </div>

                @if($has_subscription)
                You have an active subscription, so this invoice will be paid automatically.
                @endif
                MD,
        ],
        'invoice_paid' => [
            'subject' => 'Invoice Payment Confirmation',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a payment receipt for Invoice {{ $invoice->number }} sent on {{ $invoice->created_at->format('d/m/Y') }}.

                <div class="table">

                |   Item   | Quantity |  Price   |
                | :------: | :------: | :------: |
                @foreach ($items as $item)
                | {{ $item->description }} | {{ $item->quantity }} | {{ $item->price }} |
                @endforeach
                </div>

                Amount: {{ $invoice->transactions->last()?->amount ?? $invoice->formattedTotal }}<br />
                Transaction #: {{ $invoice->transactions->last()?->transaction_id ?: '-' }}<br />
                Total Paid: {{ $invoice->formattedTotal }}<br />
                Remaining Balance: {{ $invoice->formattedRemaining }}<br />
                Status: {{ $invoice->status }}

                You may review your invoice history at any time by logging in to your client area.

                Note: This email will serve as an official receipt for this payment.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		View Invoice
                	</a>
                </div>
                MD,
        ],
        'invoice_payment_failed' => [
            'subject' => 'Payment Failed',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a notice that a recent payment we attempted for your account failed.

                Invoice Date: {{ $invoice->created_at->format('d/m/Y') }}<br />
                Invoice No: {{ $invoice->number }}<br />
                Amount: {{ $invoice->formattedRemaining }}<br />
                Status: {{ $invoice->status }}

                You now need to login to your client area to pay the invoice manually. During the payment process you will be given the opportunity to choose a different payment method.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		Pay Invoice
                	</a>
                </div>
                MD,
        ],
        'invoice_payment_reminder' => [
            'subject' => 'Invoice Payment Reminder',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a billing reminder that your invoice no. {{ $invoice->number }} which was generated on {{ $invoice->created_at->format('d/m/Y') }} is due on {{ $invoice->due_at?->format('d/m/Y') }}.

                Your payment method is: {{ $invoice->transactions->last()?->gateway?->name ?: 'Not selected' }}

                Invoice: {{ $invoice->number }}<br />
                Balance Due: {{ $invoice->formattedRemaining }}<br />
                Due Date: {{ $invoice->due_at?->format('d/m/Y') }}

                You can login to your client area to view and pay the invoice.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		View Invoice
                	</a>
                </div>
                MD,
        ],
        'invoice_overdue_first' => [
            'subject' => 'First Invoice Overdue Notice',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a billing notice that your invoice no. {{ $invoice->number }} which was generated on {{ $invoice->created_at->format('d/m/Y') }} is now overdue.

                Your payment method is: {{ $invoice->transactions->last()?->gateway?->name ?: 'Not selected' }}

                Invoice: {{ $invoice->number }}<br />
                Balance Due: {{ $invoice->formattedRemaining }}<br />
                Due Date: {{ $invoice->due_at?->format('d/m/Y') }}

                You can login to your client area to view and pay the invoice.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		Pay Now
                	</a>
                </div>
                MD,
        ],
        'invoice_overdue_second' => [
            'subject' => 'Second Invoice Overdue Notice',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a second billing notice that your invoice no. {{ $invoice->number }} which was generated on {{ $invoice->created_at->format('d/m/Y') }} is still overdue.

                Your payment method is: {{ $invoice->transactions->last()?->gateway?->name ?: 'Not selected' }}

                Invoice: {{ $invoice->number }}<br />
                Balance Due: {{ $invoice->formattedRemaining }}<br />
                Due Date: {{ $invoice->due_at?->format('d/m/Y') }}

                Please settle this invoice to avoid interruption to your services.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		Pay Now
                	</a>
                </div>
                MD,
        ],
        'invoice_overdue_third' => [
            'subject' => 'Third Invoice Overdue Notice',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a final billing notice that your invoice no. {{ $invoice->number }} which was generated on {{ $invoice->created_at->format('d/m/Y') }} remains unpaid.

                Your payment method is: {{ $invoice->transactions->last()?->gateway?->name ?: 'Not selected' }}

                Invoice: {{ $invoice->number }}<br />
                Balance Due: {{ $invoice->formattedRemaining }}<br />
                Due Date: {{ $invoice->due_at?->format('d/m/Y') }}

                Services attached to this invoice may be suspended if it is not settled.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		Pay Now
                	</a>
                </div>
                MD,
        ],
        'invoice_refund_confirmation' => [
            'subject' => 'Refund Confirmation',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a notice that a refund has been issued against your invoice no. {{ $invoice->number }}.

                Invoice: {{ $invoice->number }}<br />
                Invoice Date: {{ $invoice->created_at->format('d/m/Y') }}<br />
                Remaining Balance: {{ $invoice->formattedRemaining }}<br />
                Status: {{ $invoice->status }}

                Depending on the payment method used, the refund can take a few days to appear on your statement.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		View Invoice
                	</a>
                </div>
                MD,
        ],
        'invoice_modified' => [
            'subject' => 'Invoice Updated',
            'body' => <<<'MD'
                Dear {{ $invoice->user->name }},

                This is a notice that your invoice no. {{ $invoice->number }} has been updated.

                Invoice: {{ $invoice->number }}<br />
                Amount Due: {{ $invoice->formattedRemaining }}<br />
                Due Date: {{ $invoice->due_at?->format('d/m/Y') }}

                **Invoice Items**

                <div class="table">

                |   Item   | Quantity |  Price   |
                | :------: | :------: | :------: |
                @foreach ($items as $item)
                | {{ $item->description }} | {{ $item->quantity }} | {{ $item->price }} |
                @endforeach
                </div>

                You can login to your client area to view and pay the invoice.

                <div class="action">
                	<a class="button button-blue" href="{{ route('invoices.show', $invoice) }}">
                		View Invoice
                	</a>
                </div>
                MD,
        ],

        // ---- Orders and services -------------------------------------------------
        'new_order_created' => [
            'subject' => 'Order Confirmation',
            'body' => <<<'MD'
                Dear {{ $order->user->name }},

                We have received your order and will be processing it shortly. The details of the order are below:

                Order Number: **{{ $order->id }}**

                <div class="table">

                |   Item   | Quantity |  Price   |
                | :------: | :------: | :------: |
                @foreach ($items as $item)
                | {{ $item->product->name }} | {{ $item->quantity }} | {{ $item->formattedPrice }} |
                @endforeach
                </div>

                Total: **{{ $total }}**

                You will receive an email from us shortly once your account has been setup. Please quote your order reference number if you wish to contact us about this order.
                MD,
        ],
        'new_server_created' => [
            'subject' => 'New Product Information',
            'body' => <<<'MD'
                Dear {{ $service->user->name }},

                Your order for {{ $service->product->name }} has now been activated. Please keep this message for your records.

                Product/Service: {{ $service->product->name }}<br />
                Amount: {{ $service->formattedPrice }}<br />
                Billing Cycle: {{ $service->plan?->name ?: 'One Time' }}<br />
                Next Due Date: {{ $service->expires_at?->format('d/m/Y') ?: 'Not applicable' }}

                @isset($service->product->email_template)
                {!! Str::markdown(Illuminate\View\Compilers\BladeCompiler::render($service->product->email_template, get_defined_vars()['__data'])) !!}
                @endisset

                Thank you for choosing us.

                <div class="action">
                	<a class="button button-blue" href="{{ route('services.show', $service) }}">
                		View Service
                	</a>
                </div>
                MD,
        ],
        'server_suspended' => [
            'subject' => 'Service Suspended',
            'body' => <<<'MD'
                Dear {{ $service->user->name }},

                Your service has been suspended due to non-payment. Details of the account are below:

                Product/Service: {{ $service->product->name }}<br />
                Amount: {{ $service->formattedPrice }}<br />
                Due Date: {{ $service->expires_at?->format('d/m/Y') ?: 'Not applicable' }}

                Please contact us as soon as possible to get your service back online.

                <div class="action">
                	<a class="button button-blue" href="{{ route('services.show', $service) }}">
                		View Service
                	</a>
                </div>
                MD,
        ],
        'server_terminated' => [
            'subject' => 'Service Terminated',
            'body' => <<<'MD'
                Dear {{ $service->user->name }},

                This is a notice that your service has been terminated. Details of the account are below:

                Product/Service: {{ $service->product->name }}<br />
                Amount: {{ $service->formattedPrice }}

                If you believe this has been done in error, please contact us as soon as possible.

                <div class="action">
                	<a class="button button-blue" href="{{ route('tickets.create') }}">
                		Contact Us
                	</a>
                </div>
                MD,
        ],
        'service_cancellation_received' => [
            'subject' => 'Cancellation Request Confirmation',
            'body' => <<<'MD'
                Dear {{ $service->user->name }},

                This is a notice that we have received your cancellation request. The details of the request are below:

                Product/Service: {{ $service->product->name }}<br />
                @if($cancellation->reason)
                Reason: {{ $cancellation->reason }}<br />
                @endif
                Requested On: {{ $cancellation->created_at->format('d/m/Y') }}

                @if($cancellation->type === 'end_of_period')
                Your service will remain active until {{ $service->expires_at?->format('d/m/Y') }}, the end of your current billing period.
                @else
                Your service has been terminated immediately.
                @endif

                If you did not make this request, please contact us as soon as possible.
                MD,
        ],

        // ---- Support -------------------------------------------------------------
        'new_ticket_message' => [
            'subject' => 'Support Ticket Response',
            'body' => <<<'MD'
                {!! Str::markdown($ticketMessage->message, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]) !!}

                <p>
                ----------------------------------------------<br />
                Ticket ID: #{{ $ticketMessage->ticket_id }}<br />
                Subject: {{ $ticketMessage->ticket->subject }}<br />
                Status: {{ $ticketMessage->ticket->status }}<br />
                ----------------------------------------------
                </p>

                <div class="action">
                	<a class="button button-blue" href="{{ route('tickets.show', $ticketMessage->ticket_id) }}">
                		View Ticket
                	</a>
                </div>
                MD,
        ],

        // ---- Account -------------------------------------------------------------
        'email_verification' => [
            'subject' => 'Email Verification',
            'body' => <<<'MD'
                Dear {{ $user->name }},

                Thank you for signing up with us. Before you can begin using your account, we need to confirm that this email address belongs to you.

                Please click the button below to verify your email address.

                <div class="action">
                    <a class="button button-blue" href="{{ $url }}">
                        Verify Email
                    </a>
                </div>

                This link will expire in 60 minutes.

                If you did not create an account with us, no further action is required.
                MD,
        ],
        'password_reset' => [
            'subject' => 'Password Reset Confirmation',
            'body' => <<<'MD'
                Dear {{ $user->name }},

                This is a notice that we have received a request to reset the password on your account.

                Please click the button below to choose a new password.

                <div class="action">
                	<a class="button button-blue" href="{{ $url }}">
                		Reset Password
                	</a>
                </div>

                This link will expire in 60 minutes.

                If you did not request a password reset, no further action is required.
                MD,
        ],
        'new_login_detected' => [
            'subject' => 'New Login Notification',
            'body' => <<<'MD'
                Dear {{ $user?->name ?? 'Customer' }},

                This is a notice that a new login to your account has been detected. The details of the login are below:

                IP Address: {{ $ip }}<br />
                Device: {{ $device }}<br />
                Date: {{ $time }}

                If this was you, no further action is required.

                If this was not you, please reset your password immediately.

                <div class="action">
                	<a class="button button-blue" href="{{ route('password.request') }}">
                		Reset Password
                	</a>
                </div>
                MD,
        ],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $key => $template) {
            $row = DB::table('notification_templates')->where('key', $key)->first();

            if (!$row) {
                continue;
            }

            $this->backup($row);

            DB::table('notification_templates')->where('id', $row->id)->update([
                'subject' => $template['subject'],
                'body' => $template['body'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!$this->canBackup()) {
            return;
        }

        $saved = DB::table('ext_ao_meta')
            ->where('model_type', self::BACKUP_TYPE)
            ->where('key', self::BACKUP_KEY)
            ->get();

        foreach ($saved as $entry) {
            $was = json_decode((string) $entry->value, true);

            if (!is_array($was) || !array_key_exists('body', $was)) {
                continue;
            }

            DB::table('notification_templates')->where('id', $entry->model_id)->update([
                'subject' => $was['subject'],
                'body' => $was['body'],
                'updated_at' => now(),
            ]);
        }

        DB::table('ext_ao_meta')
            ->where('model_type', self::BACKUP_TYPE)
            ->where('key', self::BACKUP_KEY)
            ->delete();
    }

    /**
     * Keep what the row said before this migration touched it, once. Running twice must not
     * overwrite the first copy with the text this migration itself wrote.
     */
    private function backup(object $row): void
    {
        if (!$this->canBackup()) {
            return;
        }

        $already = DB::table('ext_ao_meta')
            ->where('model_type', self::BACKUP_TYPE)
            ->where('model_id', $row->id)
            ->where('key', self::BACKUP_KEY)
            ->exists();

        if ($already) {
            return;
        }

        DB::table('ext_ao_meta')->insert([
            'model_type' => self::BACKUP_TYPE,
            'model_id' => $row->id,
            'key' => self::BACKUP_KEY,
            'value' => json_encode(['subject' => $row->subject, 'body' => $row->body]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function canBackup(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('ext_ao_meta');
    }
};
