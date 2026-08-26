<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

/**
 * A panel call that came back with a non-2xx status, carrying the status itself.
 *
 * Exists so a caller can react to *which* failure it was without reading the message text.
 * The concrete need: two tunnel routes answer 404 because the panel changed their path, so
 * {@see PanelApi::tunnel()} and {@see PanelApi::tunnelInfo()} try a second shape when the
 * first is missing — but only on a 404. A 500, a timeout or a rejected token must still
 * surface immediately, because retrying a different URL would turn "the panel is down" into
 * "the record does not exist", which is a worse lie than the original error.
 *
 * Extends `RuntimeException` so every existing `catch (\RuntimeException)` and
 * `catch (\Throwable)` around this client keeps behaving exactly as before.
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
