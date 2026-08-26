<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

/**
 * A non-2xx panel response, carrying the status so a caller can react to *which* failure it
 * was without parsing the message.
 *
 * Needed because {@see PanelApi::tunnel()} and {@see PanelApi::tunnelInfo()} fall back to a
 * second path on 404 — and only on 404. A 500 or a rejected token must surface as itself;
 * retrying a different URL would report "the panel is down" as "the record does not exist".
 *
 * Extends `RuntimeException`, so existing catch sites are unaffected.
 */
class PanelHttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }
}
