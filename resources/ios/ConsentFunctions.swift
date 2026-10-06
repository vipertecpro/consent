import Foundation
import UIKit
import AppTrackingTransparency

// =============================================================================
// Consent — iOS native side
// =============================================================================
//
// Four bridge functions, no third-party code:
//   • TrackingStatus  — ATTrackingManager.trackingAuthorizationStatus.
//   • RequestTracking — ATTrackingManager.requestTrackingAuthorization, only
//                       while the app is active (iOS answers "denied" without
//                       showing anything otherwise). Result via event.
//   • Read / Write    — the consent record as a JSON string in UserDefaults
//                       under "vipertecpro.consent", so it survives restarts
//                       and native SDKs can read it.
// =============================================================================

enum ConsentFunctions {
    static let storeKey = "vipertecpro.consent"

    class TrackingStatus: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            ["status": TrackingPermission.current()]
        }
    }

    class RequestTracking: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            DispatchQueue.main.async { TrackingPermission.request() }
            return [:]
        }
    }

    class Read: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let record = UserDefaults.standard.string(forKey: ConsentFunctions.storeKey) else { return [:] }
            return ["record": record]
        }
    }

    class Write: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let record = parameters["record"] as? String,
                  let data = record.data(using: .utf8),
                  (try? JSONSerialization.jsonObject(with: data)) is [String: Any] else {
                return BridgeResponse.error(code: "INVALID_PARAMETERS", message: "The consent record must be a JSON object.")
            }
            UserDefaults.standard.set(record, forKey: ConsentFunctions.storeKey)
            return ["stored": true]
        }
    }
}

// MARK: - App Tracking Transparency

enum TrackingPermission {
    static let event = "Vipertecpro\\Consent\\Events\\TrackingPermissionResult"

    private static var activeObserver: NSObjectProtocol?

    static func current() -> String {
        guard #available(iOS 14, *) else { return "notRequired" }
        return describe(ATTrackingManager.trackingAuthorizationStatus)
    }

    /// Prompts once per install. When the status is already decided the event
    /// fires right away with `prompted: false`.
    static func request() {
        guard #available(iOS 14, *) else {
            send(status: "notRequired", prompted: false)
            return
        }

        guard ATTrackingManager.trackingAuthorizationStatus == .notDetermined else {
            send(status: current(), prompted: false)
            return
        }

        // The prompt is only shown to an active app; asking from a launch
        // path or while a system sheet is up yields an instant "denied".
        guard UIApplication.shared.applicationState == .active else {
            if activeObserver == nil {
                activeObserver = NotificationCenter.default.addObserver(
                    forName: UIApplication.didBecomeActiveNotification, object: nil, queue: .main
                ) { _ in
                    if let observer = activeObserver {
                        NotificationCenter.default.removeObserver(observer)
                        activeObserver = nil
                    }
                    request()
                }
            }
            return
        }

        ATTrackingManager.requestTrackingAuthorization { status in
            DispatchQueue.main.async {
                send(status: describe(status), prompted: true)
            }
        }
    }

    @available(iOS 14, *)
    private static func describe(_ status: ATTrackingManager.AuthorizationStatus) -> String {
        switch status {
        case .authorized: return "authorized"
        case .denied: return "denied"
        case .restricted: return "restricted"
        case .notDetermined: return "notDetermined"
        @unknown default: return "denied"
        }
    }

    private static func send(status: String, prompted: Bool) {
        LaravelBridge.shared.send?(event, ["status": status, "prompted": prompted])
    }
}
