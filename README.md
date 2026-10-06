# Consent for NativePHP — tracking permission and privacy choices

Two things every app with analytics or ads needs, in one small plugin:

- the **App Tracking Transparency** prompt on iOS ("Allow *Your App* to track
  your activity across other companies' apps and websites?"), with the status
  readable at any time and the answer delivered as an event; and
- a **consent store** for the purposes you ask about — analytics, advertising,
  personalisation or your own — with "Accept all", "Reject all", per-purpose
  answers, a policy version that re-asks users when it changes, and a Laravel
  event whenever anything changes.

Choices are saved **natively** (UserDefaults on iOS, SharedPreferences on
Android), so they survive restarts and native SDKs can read them too. Hand-
written Swift and Kotlin, **zero third-party libraries**, no Android permissions.

## Features

- 🛡️ **ATT prompt** — `requestTracking()` shows it only when the app is active (iOS silently answers "denied" otherwise) and only once
- 🔎 **ATT status** — `trackingStatus()` without prompting: authorized, denied, restricted, notDetermined, notRequired
- ✅ **Consent store** — `grant()`, `deny()`, `set()`, `grantAll()`, `denyAll()`, `reset()`, `granted()`, `choices()`
- 🔁 **Re-ask on policy change** — bump `version` in `config/consent.php` and `needsPrompt()` turns true again
- 🔒 **Required purposes** — mark "essential" as required and it can never be switched off
- 📣 **Events** — `TrackingPermissionResult` from the native prompt, `ConsentUpdated` (a Laravel event) on every change
- 📱 **Stored natively** — one JSON record under `vipertecpro.consent`, readable from Swift and Kotlin
- 📱 **iOS + Android** behind one PHP API

## Requirements

- PHP 8.4+
- NativePHP Mobile v4 (`nativephp/mobile: ^4.0`) — tested on iOS and Android against 4.6
- iOS 15+ / Android 10+ (API 29)

## Installation

```bash
composer require vipertecpro/consent
php artisan vendor:publish --tag=nativephp-plugins-provider   # once per app
php artisan native:plugin:register vipertecpro/consent
php artisan vendor:publish --tag=consent-config               # optional: your purposes and version
php artisan native:run ios           # or: android — rebuild so the native code compiles in
```

> Requiring with Composer is **not** enough — an unregistered plugin does
> nothing. Always run `native:plugin:register` and confirm with
> `native:plugin:list`.

## Permissions

| Platform | What the plugin adds | When it is used |
|---|---|---|
| iOS | `NSUserTrackingUsageDescription` (Info.plist) | The text under the ATT prompt — required by Apple before you call `requestTracking()` |
| Android | nothing | Android has no tracking prompt; `trackingStatus()` is `notRequired` |

Write your own purpose string for App Store review: set
`NSUserTrackingUsageDescription` in `config/nativephp.php` under
`permissions` — app-level values always win over plugin defaults.

## Usage

```php
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Vipertecpro\Consent\Events\TrackingPermissionResult;
use Vipertecpro\Consent\Facades\Consent;

class Welcome extends NativeComponent
{
    public bool $askConsent = false;

    public function mount(): void
    {
        $this->askConsent = Consent::needsPrompt();     // never answered, or the policy changed
    }

    public function acceptAll(): void
    {
        Consent::grantAll();
        Consent::requestTracking();                      // iOS prompt; Android answers at once
        $this->askConsent = false;
    }

    public function onlyEssential(): void
    {
        Consent::denyAll();
        $this->askConsent = false;
    }

    #[On(TrackingPermissionResult::class)]
    public function onTracking(string $status): void
    {
        // authorized | denied | restricted | notRequired
    }
}
```

Anywhere in your app:

```php
if (Consent::granted('analytics')) {
    // log the screen view
}
```

Switch SDKs on and off when the answer changes, with a normal listener:

```php
use Illuminate\Support\Facades\Event;
use Vipertecpro\Consent\Events\ConsentUpdated;

Event::listen(ConsentUpdated::class, function (ConsentUpdated $event) {
    // $event->choices  ['analytics' => true, 'ads' => false, 'personalisation' => null]
    // $event->changed  ['analytics']
});
```

### Purposes and the policy version

`config/consent.php`:

```php
'purposes' => [
    'essential' => ['label' => 'Essential', 'description' => 'Sign-in and security.', 'required' => true],
    'analytics' => ['label' => 'Analytics', 'description' => 'Count screens and taps so we can improve the app.'],
    'ads' => ['label' => 'Advertising', 'description' => 'Measure ads and show relevant ones.'],
],

'version' => '1',   // bump when your policy or purposes change → users are asked again
```

Labels and descriptions are there for your consent screen (`Consent::purposes()`).

### API

```php
// App Tracking Transparency
Consent::trackingStatus(): string;     // authorized | denied | restricted | notDetermined | notRequired | unknown
Consent::requestTracking(): void;      // → TrackingPermissionResult
Consent::trackingAllowed(): bool;      // authorized, or no prompt on this platform

// Consent store
Consent::purposes(): array;
Consent::choices(): array;             // purpose => true | false | null (not answered)
Consent::granted(string $purpose): bool;
Consent::denied(string $purpose): bool;
Consent::decided(string $purpose): bool;
Consent::needsPrompt(): bool;
Consent::set(array $choices): void;    // ['analytics' => true, 'ads' => false]
Consent::grant(string ...$purposes): void;
Consent::deny(string ...$purposes): void;
Consent::grantAll(): void;
Consent::denyAll(): void;              // required purposes stay granted
Consent::reset(): void;
Consent::updatedAt(): ?CarbonImmutable;
Consent::answeredVersion(): ?string;
```

An unknown purpose or a non-boolean choice throws `InvalidArgumentException`.
Outside a native app (tests, web) the store lives in memory and the tracking
status is `unknown`, so your feature tests run without a device.

### Reading the record natively

Both platforms keep the same JSON string, so a native SDK or another plugin
can respect the user's choice before PHP even starts:

```
iOS      UserDefaults.standard.string(forKey: "vipertecpro.consent")
Android  getSharedPreferences("vipertecpro_consent", MODE_PRIVATE).getString("record", null)

{"version":"1","choices":{"analytics":true,"ads":false},"updatedAt":"2026-10-07T09:41:00+00:00"}
```

### Events

| Event | Payload | Fired when |
|---|---|---|
| `Vipertecpro\Consent\Events\TrackingPermissionResult` (native) | `string $status`, `bool $prompted` | After `requestTracking()`. `prompted` is false when the user had already answered, and on Android. |
| `Vipertecpro\Consent\Events\ConsentUpdated` (Laravel) | `array $choices`, `array $changed`, `string $version` | After every `set()`, `grant()`, `deny()`, `grantAll()`, `denyAll()` and `reset()`. |

### Web-view screens

A JS bridge is shipped at `resources/js/consent.js` with
`trackingStatus()`, `requestTracking()` and `record()`.

## Limitations

- **The ATT prompt appears once per install.** After that iOS answers with
  the stored status; users change it in Settings › Privacy & Security ›
  Tracking. Ask at a moment that explains the benefit, not at launch.
- **The iOS Simulator shows the prompt** but there is no real IDFA behind it.
- **This is a consent store, not an IAB-registered consent management platform.** It does not produce an
  IAB TCF string. If your ad network requires TCF, use its own consent SDK and
  keep this plugin for your app's other purposes.
- **Not synced across devices.** The record lives on the device; send it to
  your API if you need it server-side.

## Verified on

- iOS Simulator, iPhone 17 Pro (iOS 26.5): the ATT prompt and both answers
  (`authorized` after Allow, `denied` after Ask App Not to Track), saving,
  accept all, reject all and reset, the record surviving an app restart, in
  light and dark mode.
- Android emulator, Pixel 9 (API 36): `notRequired` with no prompt, the same
  store operations and the record surviving an app restart, in light and dark
  mode.

## Demo

The companion demo app **free-plugins-demo** contains a complete "Privacy
choices" screen — ATT status and prompt, purpose switches, accept all, reject
all, save and reset — in one small `NativeComponent` you can copy from.

## Contributing

Issues and pull requests are welcome. See the `CONTRIBUTING.md` file included
with the package for local setup, the project layout and how it works.

## Changelog

See the `CHANGELOG.md` file included with the package for the full version history.

## License

MIT — see the `LICENSE` file included with the package.

vipertecpro is an independent developer. NativePHP, Laravel, Apple, Google, Firebase and other names are trademarks of their respective owners; this package is not affiliated with or endorsed by them. iOS and Apple are trademarks of Apple Inc. Android, Google Play and Firebase are trademarks of Google LLC.

Consent is a free plugin from vipertecpro.com, home of the paid plugins for NativePHP Mobile: Rich-Text Editor, Onboarding & Tours, Health Data and Native Charts.
