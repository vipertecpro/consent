<?php

namespace Vipertecpro\Consent\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by the native side after Consent::requestTracking().
 *
 * @property string $status `authorized`, `denied`, `restricted`, `notDetermined` or `notRequired`.
 * @property bool $prompted Whether the system prompt was shown this time.
 */
class TrackingPermissionResult
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $status = 'notDetermined',
        public bool $prompted = false,
    ) {}

    public function allowed(): bool
    {
        return in_array($this->status, ['authorized', 'notRequired'], true);
    }
}
