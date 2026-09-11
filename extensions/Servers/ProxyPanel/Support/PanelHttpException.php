<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

/**
 * A non-2xx panel response, carrying the status so a caller can react to *which* failure it
 * was without parsing the message.
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
