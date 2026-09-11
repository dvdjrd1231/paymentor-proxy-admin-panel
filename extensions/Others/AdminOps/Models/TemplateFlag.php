<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use App\Models\NotificationTemplate;

/**
 * Per-template switches core has no column for. Only one so far — the reference's
 * Plain-Text box — kept on AdminOps' own meta rather than on the core row.
 */
class TemplateFlag
{
    public const PLAIN_TEXT = 'plain_text';

    public static function isPlainText(int $templateId): bool
    {
        $template = NotificationTemplate::find($templateId);

        return $template ? (Meta::for($template)[self::PLAIN_TEXT] ?? '') === '1' : false;
    }

    public static function setPlainText(NotificationTemplate $template, bool $on): void
    {
        Meta::put($template, self::PLAIN_TEXT, $on ? '1' : '');
    }
}
