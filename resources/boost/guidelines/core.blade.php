## vipertecpro/consent

A NativePHP Mobile plugin for the iOS App Tracking Transparency prompt and a
cross-platform consent store (analytics, ads, personalisation or custom
purposes from `config/consent.php`). Works with NativePHP Mobile v4.

### What it does / does not do

- `requestTracking()` shows the iOS ATT prompt once per install, only while the
  app is active; the answer arrives as the native `TrackingPermissionResult`
  event. Android has no prompt: status `notRequired`, event fires at once.
- The store keeps one record natively (UserDefaults / SharedPreferences, key
  `vipertecpro.consent`). It is not an IAB TCF CMP.
- Outside a native app the store is in memory and the status is `unknown`.

### Facade methods

`use Vipertecpro\Consent\Facades\Consent;`

- `Consent::trackingStatus(): string` — `authorized`, `denied`, `restricted`, `notDetermined`, `notRequired`, `unknown`.
- `Consent::requestTracking(): void`, `Consent::trackingAllowed(): bool`
- `Consent::needsPrompt(): bool` — never answered, a purpose undecided, or the config `version` changed.
- `Consent::granted($purpose)`, `denied($purpose)`, `decided($purpose)`, `choices()` (purpose => true|false|null)
- `Consent::set([...])`, `grant(...$purposes)`, `deny(...$purposes)`, `grantAll()`, `denyAll()`, `reset()`
- `Consent::purposes()`, `updatedAt()`, `answeredVersion()`

### Events

- `Vipertecpro\Consent\Events\TrackingPermissionResult(string $status, bool $prompted)` — native; handle with `#[On(...)]`.
- `Vipertecpro\Consent\Events\ConsentUpdated(array $choices, array $changed, string $version)` — Laravel event; handle with `Event::listen`.

### Do

- Check `Consent::granted('analytics')` before logging analytics; `granted('ads')` before personalised ads.
- Show your consent screen when `needsPrompt()` is true; bump `consent.version` when the policy changes.
- Ask for ATT after explaining the benefit, not at launch.

### Don't

- Don't call `requestTracking()` before the app is on screen — iOS ignores it (the plugin waits for you, but the moment matters).
- Don't treat `notDetermined` as allowed.
