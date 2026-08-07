<?php

namespace App\Actions\WhatsApp;

use App\Actions\Gowa\GetDeviceStatus;
use App\Enums\WaNumberStatus;
use App\Models\WaNumber;

/**
 * Number status display (specs/whatsapp-number-management/spec.md): queries
 * the number's device on its workspace's GoWA instance and persists the
 * reported state.
 */
class RefreshNumberStatus
{
    public function handle(WaNumber $number): WaNumber
    {
        $status = app(GetDeviceStatus::class)->handle($number->instance, $number->device_id);

        $number->update([
            'status' => $this->mapStatus($status),
            'jid' => $status['jid'] ?? $number->jid,
        ]);

        return $number;
    }

    private function mapStatus(array $status): WaNumberStatus
    {
        if ($status['is_logged_in'] ?? false) {
            return WaNumberStatus::LoggedIn;
        }

        if ($status['is_connected'] ?? false) {
            return WaNumberStatus::Connected;
        }

        return WaNumberStatus::Disconnected;
    }
}
