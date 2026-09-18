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

    /** @var array<int, mixed> */
    public array $attachments = [];

    public bool $preview = false;

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

    public function send(): void
    {
        $this->validate([
            'client' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'attachments.*' => 'file|max:102400',
        ], attributes: ['client' => 'recipient']);

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

        $body = $this->body;
        $files = $this->attachments;

        try {
            Mail::html(nl2br(e($body)), function ($mail) use ($user, $split, $files): void {
                $mail->to($user->email)->subject($this->subject);

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
            'title' => $this->subject,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::make()->title('Sent to ' . $user->email)->success()->send();

        $this->redirect(ClientSummary::getUrl(['record' => $user->id, 'tab' => 'emails']));
    }
}
