# Contributing

Thanks for helping improve **Consent**. This is a NativePHP Mobile plugin with
a PHP consent store and hand-written native code (Swift on iOS, Kotlin on
Android). Contributions of all kinds are welcome — bug reports, docs, and code.

## Getting set up

```json
"repositories": [
    { "type": "path", "url": "../consent" }
]
```

```bash
composer require vipertecpro/consent:@dev
php artisan native:plugin:register vipertecpro/consent
php artisan native:run ios       # or: android — recompiles the native code
```

## Running the tests

```bash
vendor/bin/pest
```

The PHP tests cover the store (choices, required purposes, version changes,
reset, validation and events); native behaviour is verified on a simulator /
emulator.

## Project layout

```
src/Consent.php                                 the store and the ATT calls
src/Facades/Consent.php                         the Consent facade
src/ConsentServiceProvider.php                  config merge + publish, singleton
src/Events/TrackingPermissionResult.php         native ATT answer (status, prompted)
src/Events/ConsentUpdated.php                   Laravel event on every change
config/consent.php                              purposes and policy version
resources/ios/ConsentFunctions.swift            ATTrackingManager + UserDefaults
resources/android/ConsentFunctions.kt           notRequired + SharedPreferences
resources/js/consent.js                         JS bridge for legacy web-view apps
resources/boost/guidelines/core.blade.php       Laravel Boost / AI usage guidelines
nativephp.json                                  manifest: bridge functions, Info.plist key, events
```

## How it works

```
PHP  Consent::grant('analytics')
  └─ record() ← nativephp_call("Consent.Read")            (cached per process)
  └─ write    → nativephp_call("Consent.Write", {record})  UserDefaults / SharedPreferences
  └─ event(new ConsentUpdated($choices, $changed))

PHP  Consent::requestTracking()
  └─ nativephp_call("Consent.RequestTracking")
        ├─ iOS:     wait until active → ATTrackingManager.requestTrackingAuthorization
        └─ Android: notRequired at once
        └─ dispatch TrackingPermissionResult { status, prompted }
```

## Verifying native changes

1. iOS Simulator: reset with `xcrun simctl privacy <udid> reset all <bundle id>`
   (or erase the app), open the demo, ask for tracking, answer, and confirm the
   status and event. Save choices, terminate and relaunch: they are still there.
2. Android: the status is `notRequired`, the event fires at once, and the
   record survives `adb shell am force-stop`.
