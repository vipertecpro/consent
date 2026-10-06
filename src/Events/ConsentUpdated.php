<?php

namespace Vipertecpro\Consent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched (a regular Laravel event, from PHP) whenever the stored choices
 * change — listen to it to switch analytics or ad SDKs on and off.
 *
 *     Event::listen(ConsentUpdated::class, function (ConsentUpdated $event) {
 *         FirebaseAnalytics::setCollectionEnabled($event->choices['analytics'] === true);
 *     });
 */
class ConsentUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, bool|null>  $choices  Every purpose after the change.
     * @param  list<string>  $changed  The purposes whose value changed.
     */
    public function __construct(
        public array $choices,
        public array $changed = [],
        public string $version = '1',
    ) {}
}
