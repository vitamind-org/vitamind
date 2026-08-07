<?php

namespace App\Exceptions\WhatsApp;

use RuntimeException;

/**
 * Thrown when `waini-provisioner` returns a non-2xx response. Per
 * specs/whatsapp-workspace-provisioning/spec.md, create-instance is
 * idempotent and atomic on the provisioner's own side, so callers can
 * safely retry a failed create by simply calling the action again —
 * nothing here needs its own retry logic.
 */
class ProvisionerException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly ?array $responseBody = null)
    {
        parent::__construct($message);
    }
}
