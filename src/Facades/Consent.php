<?php

namespace Vipertecpro\Consent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string trackingStatus()
 * @method static void requestTracking()
 * @method static bool trackingAllowed()
 * @method static array purposes()
 * @method static array choices()
 * @method static bool granted(string $purpose)
 * @method static bool denied(string $purpose)
 * @method static bool decided(string $purpose)
 * @method static bool needsPrompt()
 * @method static \Carbon\CarbonImmutable|null updatedAt()
 * @method static string|null answeredVersion()
 * @method static void set(array $choices)
 * @method static void grant(string ...$purposes)
 * @method static void deny(string ...$purposes)
 * @method static void grantAll()
 * @method static void denyAll()
 * @method static void reset()
 * @method static array record()
 * @method static void refresh()
 *
 * @see \Vipertecpro\Consent\Consent
 */
class Consent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vipertecpro\Consent\Consent::class;
    }
}
