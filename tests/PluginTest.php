<?php

/**
 * Plugin structure tests for Consent — manifest, native files, PHP classes.
 *
 * Run with: ./vendor/bin/pest
 */
beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifest = json_decode(file_get_contents($this->pluginPath.'/nativephp.json'), true);
    $this->swift = file_get_contents($this->pluginPath.'/resources/ios/ConsentFunctions.swift');
    $this->kotlin = file_get_contents($this->pluginPath.'/resources/android/ConsentFunctions.kt');
});

describe('Plugin Manifest', function () {
    it('has required fields', function () {
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($this->manifest['name'])->toBe('vipertecpro/consent');
        expect($this->manifest['namespace'])->toBe('Consent');
        expect($this->manifest['version'])->toBe('1.0.0');
    });

    it('exposes the four bridge functions on both platforms', function () {
        expect(array_column($this->manifest['bridge_functions'], 'name'))->toBe([
            'Consent.TrackingStatus', 'Consent.RequestTracking', 'Consent.Read', 'Consent.Write',
        ]);

        foreach ($this->manifest['bridge_functions'] as $function) {
            $android = explode('.', $function['android']);
            $ios = explode('.', $function['ios']);
            expect($this->kotlin)->toContain('class '.end($android).'(');
            expect($this->swift)->toContain('class '.end($ios).':');
        }
    });

    it('declares the tracking usage string and no Android permission', function () {
        expect($this->manifest['ios']['info_plist'])->toHaveKey('NSUserTrackingUsageDescription');
        expect($this->manifest['android']['permissions'])->toBe([]);
    });

    it('has marketplace metadata filled in', function () {
        expect($this->manifest['category'])->toBe('privacy');
        expect($this->manifest['pricing']['type'])->toBe('free');
        expect($this->manifest['platforms'])->toBe(['android', 'ios']);
        expect(array_slice(getimagesize($this->pluginPath.'/resources/icon.png'), 0, 2))->toBe([512, 512]);
    });

    it('declares the native event and sends it from both platforms', function () {
        expect($this->manifest['events'])->toBe(['Vipertecpro\\Consent\\Events\\TrackingPermissionResult']);
        expect(class_exists($this->manifest['events'][0]))->toBeTrue();
        expect($this->swift)->toContain('Vipertecpro\\\\Consent\\\\Events\\\\TrackingPermissionResult');
        expect($this->kotlin)->toContain('Vipertecpro\\\\Consent\\\\Events\\\\TrackingPermissionResult');
    });
});

describe('Native Code', function () {
    it('uses App Tracking Transparency and the same storage key everywhere', function () {
        expect($this->swift)->toContain('ATTrackingManager.requestTrackingAuthorization')
            ->toContain('UIApplication.didBecomeActiveNotification')
            ->toContain('"vipertecpro.consent"');
        expect($this->kotlin)->toContain('"vipertecpro_consent"')->toContain('getSharedPreferences');
    });
});

describe('Documentation', function () {
    it('ships the product files and the config', function () {
        foreach (['README.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'RELEASING.md', 'LICENSE', 'config/consent.php', 'resources/boost/guidelines/core.blade.php', 'resources/js/consent.js'] as $file) {
            expect(file_exists($this->pluginPath.'/'.$file))->toBeTrue("missing {$file}");
        }
    });

    it('keeps the README free of links and ends with the store line', function () {
        $readme = file_get_contents($this->pluginPath.'/README.md');

        expect($readme)->not->toMatch('/\]\(/')->not->toMatch('/https?:\/\//')->not->toContain('<a ');
        expect(trim(last(explode("\n", trim($readme)))))->toContain('vipertecpro.com');
    });

    it('states that the package is independent of the brands it names', function () {
        $readme = file_get_contents($this->pluginPath.'/README.md');

        expect($readme)->toContain('vipertecpro is an independent developer.')
            ->toContain('this package is not affiliated with or endorsed by them.')
            ->not->toMatch('/\\b(official|certified|partner)\\b/i');
    });

    it('lists the 1.0.0 release in the changelog', function () {
        expect(file_get_contents($this->pluginPath.'/CHANGELOG.md'))->toContain('## [1.0.0]');
    });
});
