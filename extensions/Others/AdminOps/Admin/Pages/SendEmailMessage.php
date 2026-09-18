<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Send Email Message (sendmessage.php).
 *
 * Its Emails tab resends through this composer rather than sending straight away — staff
 * amend the subject, add a CC, attach something, and see what is going out before it goes
 * (Leandro, 2026-09-18). Ours sent the stored message immediately, with no way to change
 * anything and no way to stop.
 */
class SendEmailMessage extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.send-email-message';

    protected static ?string $slug = 'send-message';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The client being written to. */
    #[Url]
    public ?int $client = null;

    /** The logged message this is a resend of, when it is one. */
    #[Url(as: 'resend')]
    public ?int $resendId = null;

    public string $subject = '';

    public string $body = '';

    public string $cc = '';

    public string $bcc = '';

    /**
     * One file per row, as the reference stacks them: a Choose File with an Add More beneath
     * that opens the next (Leandro, 2026-09-18). A single multi-select box asked for all of
     * them in one go, which is not how its screen works.
     *
     * @var array<int, mixed>
     */
    public array $attachments = [];

    /** How many Choose File rows are showing. */
    public int $attachmentRows = 1;

    public function addAttachmentRow(): void
    {
        // Capped so a stuck key cannot grow the form without end.
        $this->attachmentRows = min($this->attachmentRows + 1, 10);
    }

    public bool $preview = false;

    /** The reference's Enable/Disable Rich-Text Editor. */
    public bool $richText = true;

    /** Its Save Message pair: the tick and the name to keep it under. */
    public bool $saveMessage = false;

    public string $saveName = '';

    /** Its Load Saved Message picker. */
    public string $loadName = '';

    /**
     * The reference's Available Merge Fields, less the ones this platform has nothing to
     * fill: every token here resolves against a real column, so a message written with them
     * goes out with values rather than the token text. {@see self::merge()}
     *
     * @var array<string, string>
     */
    public const MERGE_FIELDS = [
        'client_id' => 'ID',
        'client_name' => 'Client Name',
        'client_first_name' => 'First Name',
        'client_last_name' => 'Last Name',
        'client_company_name' => 'Company Name',
        'client_email' => 'Email Address',
        'client_address1' => 'Address 1',
        'client_address2' => 'Address 2',
        'client_city' => 'City',
        'client_state' => 'State/Region',
        'client_postcode' => 'Postcode',
        'client_country' => 'Country',
        'client_phonenumber' => 'Phone Number',
        'company_name' => 'Your Company Name',
        'client_area_url' => 'Client Area URL',
    ];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.users.viewAny');
    }

    public function getTitle(): string
    {
        return 'Send Email Message';
    }

    public function mount(): void
    {
        if (!$this->resendId) {
            return;
        }

        $row = DB::table('notifications')->where('id', $this->resendId)->first();

        if (!$row) {
            return;
        }

        // The client comes from the message itself, so a resend cannot be pointed at
        // somebody else by editing the query string.
        $this->client = (int) $row->user_id;
        $this->subject = (string) $row->title;
        $this->body = (string) $row->body;
    }

    public function recipient(): ?User
    {
        return $this->client ? User::find($this->client) : null;
    }

    /** The reference shows who it is from above the recipients. */
    public function fromName(): string
    {
        return (string) config('settings.mail_from_name', config('settings.company_name', config('app.name')));
    }

    public function fromAddress(): string
    {
        return (string) config('settings.mail_from_address', config('mail.from.address'));
    }

    public function togglePreview(): void
    {
        $this->preview = !$this->preview;
    }

    public function toggleRichText(): void
    {
        $this->richText = !$this->richText;
    }

    /** Saved messages, newest first, for the reference's Load Saved Message picker. */
    public function savedMessages()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ext_saved_messages')) {
            return collect();
        }

        return DB::table('ext_saved_messages')->orderBy('name')->get();
    }

    /** Its Load Message button: replace what is in the boxes with the saved text. */
    public function loadSavedMessage(): void
    {
        if ($this->loadName === '') {
            Notification::make()->title('Pick a saved message first')->warning()->send();

            return;
        }

        $row = DB::table('ext_saved_messages')->where('name', $this->loadName)->first();

        if (!$row) {
            Notification::make()->title('That saved message is gone')->danger()->send();

            return;
        }

        $this->subject = $row->subject;
        $this->body = $row->body;

        Notification::make()->title('Loaded “' . $row->name . '”')->success()->send();
    }

    /**
     * Fill the reference's merge fields from the client this is going to.
     *
     * A token with nothing behind it becomes an empty string rather than being left in the
     * message: a client with no company would otherwise be emailed the literal
     * "{$client_company_name}".
     */
    public function merge(string $text, ?User $user): string
    {
        $property = fn (string $key): string => (string) ($user?->properties
            ->firstWhere('key', $key)?->value ?? '');

        $values = [
            'client_id' => (string) ($user?->id ?? ''),
            'client_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'client_first_name' => (string) ($user->first_name ?? ''),
            'client_last_name' => (string) ($user->last_name ?? ''),
            'client_company_name' => (string) ($user->company_name ?? ''),
            'client_email' => (string) ($user->email ?? ''),
            'client_address1' => $property('address'),
            'client_address2' => $property('address2'),
            'client_city' => $property('city'),
            'client_state' => $property('state'),
            'client_postcode' => $property('zip'),
            'client_country' => $property('country'),
            'client_phonenumber' => $property('phone'),
            'company_name' => (string) config('settings.company_name', config('app.name')),
            'client_area_url' => (string) config('app.url'),
        ];

        foreach ($values as $token => $value) {
            $text = str_replace(['{$' . $token . '}', '{' . $token . '}'], $value, $text);
        }

        return $text;
    }

    /** What the client will actually receive, tokens filled in. */
    public function rendered(): string
    {
        return $this->merge($this->body, $this->recipient());
    }

    public function send(): void
    {
        $this->validate([
            'client' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'attachments.*' => 'nullable|file|max:102400',
            'saveName' => 'required_if:saveMessage,true|nullable|string|max:255',
        ], attributes: ['client' => 'recipient', 'saveName' => 'save name']);

        $user = $this->recipient();

        if (!$user?->email) {
            Notification::make()->title('This client has no email address')->danger()->send();

            return;
        }

        // Comma separated, as the reference labels the boxes. Anything that is not an
        // address is dropped rather than handed to the mailer, which would throw.
        $split = fn (string $value): array => array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL),
        ));

        // Saved before the merge, so the tokens are kept for next time rather than the
        // values of whoever this copy happened to go to.
        if ($this->saveMessage && trim($this->saveName) !== ''
            && \Illuminate\Support\Facades\Schema::hasTable('ext_saved_messages')) {
            DB::table('ext_saved_messages')->updateOrInsert(
                ['name' => trim($this->saveName)],
                [
                    'subject' => $this->subject,
                    'body' => $this->body,
                    'admin_id' => Auth::id(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $body = $this->merge($this->body, $user);

        // Empty rows are skipped: the reference leaves a spare Choose File sitting there.
        $files = array_values(array_filter($this->attachments));

        try {
            // Rich text is already HTML; plain text needs its line breaks turned into it.
            $html = $this->richText ? $body : nl2br(e($body));

            Mail::html($html, function ($mail) use ($user, $split, $files): void {
                $mail->to($user->email)->subject($this->merge($this->subject, $user));

                foreach ($split($this->cc) as $address) {
                    $mail->cc($address);
                }

                foreach ($split($this->bcc) as $address) {
                    $mail->bcc($address);
                }

                foreach ($files as $file) {
                    $mail->attach($file->getRealPath(), ['as' => $file->getClientOriginalName()]);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            Notification::make()->title('Not sent')->body($e->getMessage())->danger()->send();

            return;
        }

        // Logged like any other message to this client, so the Emails tab shows it went.
        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'title' => $this->merge($this->subject, $user),
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::make()->title('Sent to ' . $user->email)->success()->send();

        $this->redirect(ClientSummary::getUrl(['record' => $user->id, 'tab' => 'emails']));
    }
}
