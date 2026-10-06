package com.vipertecpro.plugins.consent

// =============================================================================
// Consent — Android native side
// =============================================================================
//
// Four bridge functions, no third-party code (mirrors the iOS implementation):
//   • TrackingStatus  — Android has no App Tracking Transparency prompt:
//                       always "notRequired".
//   • RequestTracking — answers at once via the event with "notRequired".
//   • Read / Write    — the consent record as a JSON string in the app's
//                       SharedPreferences ("vipertecpro_consent" / "record"),
//                       so it survives restarts and native SDKs can read it.
// =============================================================================

import android.content.Context
import android.os.Handler
import android.os.Looper
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONException
import org.json.JSONObject

object ConsentFunctions {

    private const val EVENT_TRACKING = "Vipertecpro\\Consent\\Events\\TrackingPermissionResult"
    const val PREFS = "vipertecpro_consent"
    const val KEY = "record"

    class TrackingStatus(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = mapOf("status" to "notRequired")
    }

    class RequestTracking(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Handler(Looper.getMainLooper()).post {
                val payload = JSONObject().apply {
                    put("status", "notRequired")
                    put("prompted", false)
                }
                NativeActionCoordinator.dispatchEvent(activity, EVENT_TRACKING, payload.toString())
            }
            return emptyMap()
        }
    }

    class Read(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val record = activity.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null)
            return if (record == null) emptyMap() else mapOf("record" to record)
        }
    }

    class Write(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val record = parameters["record"] as? String
                ?: return BridgeResponse.error("INVALID_PARAMETERS", "The consent record must be a JSON object.")
            try {
                JSONObject(record)
            } catch (e: JSONException) {
                return BridgeResponse.error("INVALID_PARAMETERS", "The consent record must be a JSON object.")
            }
            val stored = activity.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(KEY, record).commit()
            return mapOf("stored" to stored)
        }
    }
}
