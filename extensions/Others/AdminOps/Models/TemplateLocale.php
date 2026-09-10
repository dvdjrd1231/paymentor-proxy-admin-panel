<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A template's translation into one active language, and the list of which languages are
 * active at all.
 *
 * See the `ext_notification_template_locales` migration for why the translations live
 * beside core's table rather than in it.
 */
class TemplateLocale extends Model
{
    protected $table = 'ext_notification_template_locales';

    protected $fillable = ['notification_template_id', 'locale', 'subject', 'body'];

    /** The global setting holding the active languages, comma separated. */
    public const SETTING = 'adminops_email_locales';

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /**
     * The active language codes, in the order they were activated.
     *
     * @return array<int, string>
     */
    public static function active(): array
    {
        $row = Setting::whereNull('settingable_type')->where('key', self::SETTING)->first();

        return array_values(array_filter(array_map('trim', explode(',', (string) ($row->value ?? '')))));
    }

    /** Replace the active list. Writing the empty list removes the setting entirely. */
    public static function setActive(array $locales): void
    {
        $value = implode(',', array_values(array_unique(array_filter($locales))));
        $row = Setting::whereNull('settingable_type')->where('key', self::SETTING)->first();

        if ($value === '') {
            $row?->delete();
        } elseif ($row) {
            $row->update(['value' => $value]);
        } else {
            Setting::create([
                'key' => self::SETTING,
                'value' => $value,
                'settingable_type' => null,
                'type' => 'string',
                'encrypted' => false,
            ]);
        }

        \App\Classes\Settings::flushCache();
    }

    /**
     * The language a user reads in, or null for the default.
     *
     * Clients carry it as a profile property (Add New Client writes it); anyone without
     * one reads the default version, which is what the reference does for English.
     */
    public static function localeFor(User $user): ?string
    {
        $locale = (string) ($user->properties->firstWhere('key', 'language')?->value ?? '');

        return $locale !== '' ? $locale : null;
    }

    /**
     * The translated subject and body for a template in one language, or null.
     *
     * Returns null unless *both* the language is still active and a translation exists
     * with something in it — a half-filled translation must not send a blank email, so a
     * missing piece falls back to the default rather than overriding it with nothing.
     *
     * @return array{subject: string, body: string}|null
     */
    public static function resolve(NotificationTemplate $template, ?string $locale): ?array
    {
        if ($locale === null || ! in_array($locale, self::active(), true)) {
            return null;
        }

        $row = self::where('notification_template_id', $template->id)->where('locale', $locale)->first();

        if (! $row || trim((string) $row->subject) === '' || trim((string) $row->body) === '') {
            return null;
        }

        return ['subject' => (string) $row->subject, 'body' => (string) $row->body];
    }
}
