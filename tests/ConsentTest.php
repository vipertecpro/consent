<?php

use Illuminate\Events\Dispatcher;
use Vipertecpro\Consent\Consent;
use Vipertecpro\Consent\Events\ConsentUpdated;
use Vipertecpro\Consent\Events\TrackingPermissionResult;

/**
 * The PHP layer. nativephp_call() is absent here, so the in-memory record is
 * used and the tracking status is "unknown".
 */
beforeEach(function () {
    $this->dispatched = [];
    $events = new Dispatcher;
    $events->listen(ConsentUpdated::class, function (ConsentUpdated $event) {
        $this->dispatched[] = $event;
    });

    $this->consent = new Consent(
        purposes: [
            'essential' => ['label' => 'Essential', 'required' => true],
            'analytics' => ['label' => 'Analytics'],
            'ads' => ['label' => 'Advertising'],
        ],
        version: '2',
        events: $events,
    );
});

it('starts undecided and needing a prompt', function () {
    expect($this->consent->choices())->toBe(['essential' => true, 'analytics' => null, 'ads' => null])
        ->and($this->consent->needsPrompt())->toBeTrue()
        ->and($this->consent->updatedAt())->toBeNull()
        ->and($this->consent->decided('analytics'))->toBeFalse()
        ->and($this->consent->granted('essential'))->toBeTrue();
});

it('stores choices and reports what changed', function () {
    $this->consent->grant('analytics');

    expect($this->consent->granted('analytics'))->toBeTrue()
        ->and($this->consent->decided('ads'))->toBeFalse()
        ->and($this->consent->needsPrompt())->toBeTrue();

    $this->consent->deny('ads');

    expect($this->consent->denied('ads'))->toBeTrue()
        ->and($this->consent->needsPrompt())->toBeFalse()
        ->and($this->consent->answeredVersion())->toBe('2')
        ->and($this->consent->updatedAt())->not->toBeNull();

    expect($this->dispatched)->toHaveCount(2)
        ->and($this->dispatched[0])->toBeInstanceOf(ConsentUpdated::class)
        ->and($this->dispatched[0]->changed)->toBe(['analytics'])
        ->and($this->dispatched[1]->changed)->toBe(['ads'])
        ->and($this->dispatched[1]->choices)->toBe(['essential' => true, 'analytics' => true, 'ads' => false]);
});

it('accepts all, rejects all but never turns a required purpose off', function () {
    $this->consent->grantAll();
    expect($this->consent->choices())->toBe(['essential' => true, 'analytics' => true, 'ads' => true]);

    $this->consent->denyAll();
    expect($this->consent->choices())->toBe(['essential' => true, 'analytics' => false, 'ads' => false]);
});

it('forgets everything on reset', function () {
    $this->consent->grantAll();
    $this->consent->reset();

    expect($this->consent->choices())->toBe(['essential' => true, 'analytics' => null, 'ads' => null])
        ->and($this->consent->needsPrompt())->toBeTrue()
        ->and(end($this->dispatched)->changed)->toBe(['analytics', 'ads']);
});

it('asks again when the policy version changes', function () {
    $this->consent->grantAll();

    $record = $this->consent->record();
    $newer = new Consent(purposes: $this->consent->purposes(), version: '3');
    (fn () => $this->memory = $record)->call($newer);

    expect($newer->needsPrompt())->toBeTrue()
        ->and($newer->granted('analytics'))->toBeTrue();
});

it('rejects unknown purposes and non-boolean choices', function () {
    expect(fn () => $this->consent->grant('location'))->toThrow(InvalidArgumentException::class, 'unknown purpose "location"')
        ->and(fn () => $this->consent->granted('nope'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->consent->set(['analytics' => 'yes']))->toThrow(InvalidArgumentException::class, 'must be true or false');
});

it('reads the tracking status as unknown outside a native app', function () {
    expect($this->consent->trackingStatus())->toBe('unknown')
        ->and($this->consent->trackingAllowed())->toBeFalse();

    $this->consent->requestTracking();
});

it('normalises a stored record defensively', function () {
    $normalise = new ReflectionMethod(Consent::class, 'normalise');

    expect($normalise->invoke(null, ['version' => 1, 'choices' => ['analytics' => true, 'ads' => 'yes', 5 => false], 'updatedAt' => null]))
        ->toBe(['version' => '1', 'choices' => ['analytics' => true], 'updatedAt' => null]);
});

it('describes the tracking result', function () {
    expect((new TrackingPermissionResult('authorized', true))->allowed())->toBeTrue()
        ->and((new TrackingPermissionResult('notRequired'))->allowed())->toBeTrue()
        ->and((new TrackingPermissionResult('denied', true))->allowed())->toBeFalse();
});
