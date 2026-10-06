# Changelog

All notable changes to `vipertecpro/consent` are documented here.
The format is based on Keep a Changelog, and this project adheres to
Semantic Versioning.

## [1.0.0] - 2026-10-07

First release. Verified on the iOS Simulator (iPhone 17 Pro, iOS 26.5) and an
Android emulator (Pixel 9, API 36) against NativePHP Mobile 4.6.

### Added
- **App Tracking Transparency** — `trackingStatus()`, `requestTracking()`
  (waits until the app is active, prompts once) and `trackingAllowed()`; the
  answer arrives as `TrackingPermissionResult`. Android reports `notRequired`.
- **Consent store** — per-purpose choices with `set()`, `grant()`, `deny()`,
  `grantAll()`, `denyAll()`, `reset()`, `granted()`, `denied()`, `decided()`
  and `choices()`; required purposes can never be switched off.
- **Policy version** — `needsPrompt()` turns true when `consent.version`
  changes after the user answered.
- **Native storage** — one JSON record in UserDefaults / SharedPreferences,
  readable by native SDKs.
- **`ConsentUpdated`** Laravel event with the new choices and what changed.
- Publishable `config/consent.php`, a JS bridge and Laravel Boost guidelines.

### Notes
- Zero third-party native dependencies; no Android permissions.
