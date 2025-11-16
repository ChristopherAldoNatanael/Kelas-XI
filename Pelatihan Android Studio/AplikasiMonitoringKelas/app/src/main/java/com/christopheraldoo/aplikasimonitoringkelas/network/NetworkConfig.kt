package com.christopheraldoo.aplikasimonitoringkelas.network

import android.content.Context
import android.os.Build

object NetworkConfig {
    object BaseUrls {
        // Different URLs for development and production
        private const val LOCAL_URL = "http://10.0.2.2:8000/api/"  // For Android emulator
        private const val DEVELOPMENT_URL = "http://192.168.1.11:8000/api/"  // For development on same network (updated IP)
        const val PRODUCTION_URL = "https://your-production-domain.com/api/"  // Update to your actual production URL

        // Current PC IP - Updated for physical device connection
        const val CURRENT_IP_URL = "http://192.168.40.158:8000/api/"

        // Use the appropriate URL based on runtime environment (emulator vs device)
        fun getDefault(context: Context): String {
            return if (isRunningOnEmulator()) LOCAL_URL else CURRENT_IP_URL
        }

        // Public helpers for fallback probing
        fun getEmulatorUrl(): String = LOCAL_URL
        fun getDeviceLanUrl(): String = CURRENT_IP_URL

        // Helper to detect emulator reliably (support old and new emulator variants)
        private fun isRunningOnEmulator(): Boolean {
            val fingerprint = Build.FINGERPRINT.lowercase()
            val model = Build.MODEL.lowercase()
            val product = Build.PRODUCT.lowercase()
            val hardware = Build.HARDWARE.lowercase()
            val brand = Build.BRAND.lowercase()
            val device = Build.DEVICE.lowercase()

            return (
                fingerprint.startsWith("generic") ||
                fingerprint.contains("vbox") ||
                fingerprint.contains("test-keys") ||
                model.contains("emulator") ||
                model.contains("android sdk built for x86") ||
                model.contains("sdk_gphone") ||
                model.contains("sdk phone") ||
                product.contains("google_sdk") ||
                product.contains("sdk") ||
                product.contains("sdk_gphone") ||
                product.contains("emulator") ||
                product.contains("vbox86p") ||
                product.contains("generic") ||
                hardware.contains("goldfish") ||
                hardware.contains("ranchu") ||
                brand.startsWith("generic") && device.startsWith("generic")
            )
        }

        // Backward compatible alias (avoid using in new code)
        @Deprecated("Use getDefault(context) instead for automatic detection")
        const val DEFAULT = CURRENT_IP_URL
    }

    object Endpoints {
        const val LOGIN = "auth/login"
        const val LOGOUT = "auth/logout"
        const val REGISTER = "auth/register"
        const val ME = "auth/me"
        const val REFRESH = "auth/refresh"
        const val CHANGE_PASSWORD = "auth/change-password"

        // Schedule endpoints
        const val SCHEDULES = "schedules"
        const val SCHEDULES_MOBILE = "schedules-mobile"  // Lightweight endpoint for mobile devices

        // User management
        const val USERS = "users"

        // Master data
        const val SUBJECTS = "subjects"
        const val TEACHERS = "teachers"
        const val CLASSES = "classes"
        const val CLASSROOMS = "classrooms"

        // Dropdown data
        const val DROPDOWN_ALL = "dropdown/all"
        const val DROPDOWN_SUBJECTS = "dropdown/subjects"
        const val DROPDOWN_TEACHERS = "dropdown/{id}/teachers"
        const val DROPDOWN_CLASSROOMS = "dropdown/classrooms"

        // Protected endpoints
        const val NOTIFICATIONS = "notifications"
        const val UNREAD_COUNT = "notifications/unread-count"
        const val DASHBOARD = "dashboard"

        // My schedule (student)
        const val MY_SCHEDULE = "my-schedule"
    }

    object Headers {
        const val AUTHORIZATION = "Authorization"
        const val BEARER = "Bearer"
        const val CONTENT_TYPE = "Content-Type"
        const val APP_JSON = "application/json"
    }

    object Timeouts {
        const val CONNECT_TIMEOUT = 5L   // Faster failover for down servers
        const val READ_TIMEOUT = 10L     // Quicker feedback
        const val WRITE_TIMEOUT = 10L    // Quicker feedback
    }

    object Cache {
        const val MAX_CACHE_SIZE = 10 * 1024 * 1024L  // 10MB cache
        const val CACHE_AGE = 60 * 60L  // 1 hour cache age
    }
    
    object RetryPolicy {
        const val MAX_RETRIES = 1  // Reduced from 2 - Number of retry attempts
        const val INITIAL_DELAY_MS = 2000L  // Increased from 1000L - Initial delay between retries
        const val MAX_DELAY_MS = 3000L  // Reduced from 5000L - Maximum delay between retries
    }
}
