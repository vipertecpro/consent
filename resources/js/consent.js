/**
 * Consent Plugin for NativePHP Mobile — JavaScript bridge (legacy web-view apps).
 *
 * NOTE: the primary consumer in v4 is the PHP facade (`Consent::granted()`,
 * `Consent::requestTracking()`), which also validates purposes and fires
 * `ConsentUpdated`. This wrapper exposes the native calls for web views.
 *
 * @example
 *   import { consent } from '@vipertecpro/consent';
 *   import { On } from '#nativephp';
 *
 *   On('native:Vipertecpro\\Consent\\Events\\TrackingPermissionResult', ({ status }) => {
 *       // authorized | denied | restricted | notRequired
 *   });
 *
 *   if (await consent.trackingStatus() === 'notDetermined') {
 *       await consent.requestTracking();
 *   }
 */

const baseUrl = '/_native/api/call';

/**
 * Internal bridge call function.
 * @private
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ method, params })
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    return result.data ?? result;
}

/**
 * The App Tracking Transparency status, without prompting.
 * @returns {Promise<'authorized'|'denied'|'restricted'|'notDetermined'|'notRequired'|'unknown'>}
 */
export async function trackingStatus() {
    const result = await bridgeCall('Consent.TrackingStatus');

    return result.status || 'unknown';
}

/** Show the ATT prompt; the answer arrives as `TrackingPermissionResult`. */
export async function requestTracking() {
    await bridgeCall('Consent.RequestTracking');
}

/**
 * The stored consent record.
 * @returns {Promise<{version: string|null, choices: Object<string, boolean>, updatedAt: string|null}>}
 */
export async function record() {
    const result = await bridgeCall('Consent.Read');

    try {
        const parsed = JSON.parse(result.record || '{}');

        return { version: parsed.version ?? null, choices: parsed.choices ?? {}, updatedAt: parsed.updatedAt ?? null };
    } catch {
        return { version: null, choices: {}, updatedAt: null };
    }
}

export const consent = { trackingStatus, requestTracking, record };

export default consent;
