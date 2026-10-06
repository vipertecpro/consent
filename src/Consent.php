<?php

namespace Vipertecpro\Consent;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;
use Vipertecpro\Consent\Events\ConsentUpdated;

/**
 * App Tracking Transparency plus a small consent store for NativePHP Mobile.
 *
 * Choices are stored natively (UserDefaults on iOS, SharedPreferences on
 * Android) under the key `vipertecpro.consent`, as JSON:
 *
 *     {"version": "1", "choices": {"analytics": true, "ads": false}, "updatedAt": "2026-10-07T09:41:00+00:00"}
 *
 * so the record survives restarts and native SDKs can read it. Outside a
 * native app (tests, web) an in-memory record is used instead.
 */
class Consent
{
    public const TRACKING_AUTHORIZED = 'authorized';

    public const TRACKING_DENIED = 'denied';

    public const TRACKING_RESTRICTED = 'restricted';

    public const TRACKING_NOT_DETERMINED = 'notDetermined';

    /** Android, and iOS before 14 — there is no tracking prompt. */
    public const TRACKING_NOT_REQUIRED = 'notRequired';

    public const TRACKING_UNKNOWN = 'unknown';

    /** @var array{version: string|null, choices: array<string, bool>, updatedAt: string|null}|null */
    protected ?array $record = null;

    /** @var array{version: string|null, choices: array<string, bool>, updatedAt: string|null}|null */
    protected ?array $memory = null;

    /**
     * @param  array<string, array{label?: string, description?: string, required?: bool}>  $purposes
     */
    public function __construct(
        protected array $purposes = [],
        protected string $version = '1',
        protected ?Dispatcher $events = null,
    ) {}

    // --- App Tracking Transparency -----------------------------------------

    /**
     * The ATT status, without prompting: `authorized`, `denied`, `restricted`,
     * `notDetermined` (iOS 14+), `notRequired` (Android) or `unknown`
     * (outside a native app).
     */
    public function trackingStatus(): string
    {
        $status = $this->call('Consent.TrackingStatus')['status'] ?? null;

        return is_string($status) ? $status : self::TRACKING_UNKNOWN;
    }

    /**
     * Show the ATT prompt. The answer arrives as
     * {@see Events\TrackingPermissionResult}; when the user already answered,
     * or on Android, the event fires straight away with the current status.
     * iOS only shows the prompt while the app is active, once per install.
     */
    public function requestTracking(): void
    {
        $this->call('Consent.RequestTracking');
    }

    /** True when the user allowed tracking (iOS) or no prompt applies (Android). */
    public function trackingAllowed(): bool
    {
        return in_array($this->trackingStatus(), [self::TRACKING_AUTHORIZED, self::TRACKING_NOT_REQUIRED], true);
    }

    // --- Consent store ------------------------------------------------------

    /** @return array<string, array{label?: string, description?: string, required?: bool}> */
    public function purposes(): array
    {
        return $this->purposes;
    }

    /**
     * Every configured purpose with the user's choice: true, false, or null
     * when it was never answered. Required purposes are always true.
     *
     * @return array<string, bool|null>
     */
    public function choices(): array
    {
        $stored = $this->record()['choices'];
        $choices = [];

        foreach ($this->purposes as $key => $purpose) {
            $choices[$key] = ($purpose['required'] ?? false) ? true : ($stored[$key] ?? null);
        }

        return $choices;
    }

    public function granted(string $purpose): bool
    {
        $this->assertPurpose($purpose);

        return $this->choices()[$purpose] === true;
    }

    public function denied(string $purpose): bool
    {
        $this->assertPurpose($purpose);

        return $this->choices()[$purpose] === false;
    }

    public function decided(string $purpose): bool
    {
        $this->assertPurpose($purpose);

        return $this->choices()[$purpose] !== null;
    }

    /**
     * True when the consent screen should be shown: never answered, a purpose
     * is still undecided, or the policy version changed since the answer.
     */
    public function needsPrompt(): bool
    {
        $record = $this->record();

        if ($record['version'] !== $this->version) {
            return true;
        }

        return in_array(null, $this->choices(), true);
    }

    /** When the user last changed anything, or null. */
    public function updatedAt(): ?CarbonImmutable
    {
        $at = $this->record()['updatedAt'];

        return $at === null ? null : CarbonImmutable::parse($at);
    }

    /** The policy version the stored answer was given for. */
    public function answeredVersion(): ?string
    {
        return $this->record()['version'];
    }

    /**
     * Store several choices at once, e.g. from a consent screen with toggles.
     * Purposes you leave out keep their current value.
     *
     * @param  array<string, bool>  $choices
     */
    public function set(array $choices): void
    {
        foreach ($choices as $purpose => $value) {
            $this->assertPurpose((string) $purpose);

            if (! is_bool($value)) {
                throw new InvalidArgumentException("Consent: the choice for \"{$purpose}\" must be true or false.");
            }
        }

        $before = $this->choices();
        $stored = $this->record()['choices'];

        foreach ($choices as $purpose => $value) {
            if (! ($this->purposes[$purpose]['required'] ?? false)) {
                $stored[$purpose] = $value;
            }
        }

        $this->write([
            'version' => $this->version,
            'choices' => $stored,
            'updatedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);

        $after = $this->choices();
        $changed = array_keys(array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

        $this->events?->dispatch(new ConsentUpdated($after, $changed, $this->version));
    }

    public function grant(string ...$purposes): void
    {
        $this->set(array_fill_keys($purposes, true));
    }

    public function deny(string ...$purposes): void
    {
        $this->set(array_fill_keys($purposes, false));
    }

    /** "Accept all". */
    public function grantAll(): void
    {
        $this->set(array_fill_keys(array_keys($this->purposes), true));
    }

    /** "Reject all" — required purposes stay granted. */
    public function denyAll(): void
    {
        $this->set(array_fill_keys(array_keys($this->purposes), false));
    }

    /** Forget every answer, e.g. for a "reset privacy choices" setting. */
    public function reset(): void
    {
        $before = $this->choices();

        $this->write(['version' => null, 'choices' => [], 'updatedAt' => null]);

        $after = $this->choices();
        $changed = array_keys(array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

        $this->events?->dispatch(new ConsentUpdated($after, $changed, $this->version));
    }

    // --- Storage -------------------------------------------------------------

    /**
     * @return array{version: string|null, choices: array<string, bool>, updatedAt: string|null}
     */
    public function record(): array
    {
        if ($this->record !== null) {
            return $this->record;
        }

        if (! function_exists('nativephp_call')) {
            return $this->record = $this->memory ?? self::emptyRecord();
        }

        $raw = $this->call('Consent.Read')['record'] ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return $this->record = self::normalise(is_array($decoded) ? $decoded : []);
    }

    /** Drop the cached record so the next read comes from the device. */
    public function refresh(): void
    {
        $this->record = null;
    }

    /** @param  array{version: string|null, choices: array<string, bool>, updatedAt: string|null}  $record */
    protected function write(array $record): void
    {
        $record = self::normalise($record);
        $this->record = $record;

        if (! function_exists('nativephp_call')) {
            $this->memory = $record;

            return;
        }

        $this->call('Consent.Write', ['record' => json_encode($record, JSON_UNESCAPED_SLASHES)]);
    }

    /**
     * @return array{version: string|null, choices: array<string, bool>, updatedAt: string|null}
     */
    protected static function normalise(array $record): array
    {
        $choices = [];

        foreach ((array) ($record['choices'] ?? []) as $key => $value) {
            if (is_string($key) && is_bool($value)) {
                $choices[$key] = $value;
            }
        }

        return [
            'version' => isset($record['version']) ? (string) $record['version'] : null,
            'choices' => $choices,
            'updatedAt' => isset($record['updatedAt']) ? (string) $record['updatedAt'] : null,
        ];
    }

    /** @return array{version: null, choices: array<string, bool>, updatedAt: null} */
    protected static function emptyRecord(): array
    {
        return ['version' => null, 'choices' => [], 'updatedAt' => null];
    }

    protected function assertPurpose(string $purpose): void
    {
        if (! array_key_exists($purpose, $this->purposes)) {
            throw new InvalidArgumentException(
                "Consent: unknown purpose \"{$purpose}\" — add it to config/consent.php (known: ".implode(', ', array_keys($this->purposes)).').'
            );
        }
    }

    /**
     * Call a synchronous bridge function; a missing bridge or an error
     * response yields an empty array.
     *
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }

        $raw = nativephp_call($method, json_encode((object) $params));
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) && ($decoded['status'] ?? null) !== 'error' ? $decoded : [];
    }
}
