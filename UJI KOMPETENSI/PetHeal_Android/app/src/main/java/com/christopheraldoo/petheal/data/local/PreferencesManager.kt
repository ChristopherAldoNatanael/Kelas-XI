package com.christopheraldoo.petheal.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.core.stringSetPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.christopheraldoo.petheal.data.model.LocationSource
import com.christopheraldoo.petheal.data.model.UserLocation
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "petheal_prefs")

@Singleton
class PreferencesManager @Inject constructor(
    @ApplicationContext private val context: Context
) {
    companion object {
        const val AUTH_PROVIDER_EMAIL_PASSWORD = "email_password"
        const val AUTH_PROVIDER_GOOGLE = "google"

        private val KEY_AUTH_TOKEN = stringPreferencesKey("auth_token")
        private val KEY_USER_ID = stringPreferencesKey("user_id")
        private val KEY_USER_EMAIL = stringPreferencesKey("user_email")
        private val KEY_USER_NAME = stringPreferencesKey("user_name")
        private val KEY_USER_PHOTO = stringPreferencesKey("user_photo")
        private val KEY_IS_LOGGED_IN = stringPreferencesKey("is_logged_in")
        private val KEY_FCM_TOKEN = stringPreferencesKey("fcm_token")
        private val KEY_AUTH_PROVIDER = stringPreferencesKey("auth_provider")
        private val KEY_HAS_SEEN_ONBOARDING = stringPreferencesKey("has_seen_onboarding")

        // PHASE 9: booking ids already nudged for a doctor rating. The reminder
        // appears once per finished visit — never nagging.
        private val KEY_RATING_PROMPTED_IDS = stringSetPreferencesKey("rating_prompted_ids")

        // PHASE 7: selected tenant (1 APK dinamis). Survives logout so the
        // picker is not forced on every login; refreshed from login response.
        private val KEY_CLINIC_ID = stringPreferencesKey("clinic_id")
        private val KEY_CLINIC_NAME = stringPreferencesKey("clinic_name")
        private val KEY_CLINIC_SLUG = stringPreferencesKey("clinic_slug")
        private val KEY_CLINIC_LOGO = stringPreferencesKey("clinic_logo")
        private val KEY_CLINIC_COLOR = stringPreferencesKey("clinic_color")
        private val KEY_CLINIC_ADDRESS = stringPreferencesKey("clinic_address")

        // MULTI-CLINIC DISCOVERY: lokasi pengguna untuk mencari klinik
        // terdekat. Opsional — kosong berarti "Lokasi belum diatur" dan
        // aplikasi tetap berjalan (klinik tampil tanpa jarak).
        private val KEY_LOCATION_LAT = stringPreferencesKey("location_lat")
        private val KEY_LOCATION_LNG = stringPreferencesKey("location_lng")
        private val KEY_LOCATION_LABEL = stringPreferencesKey("location_label")
        private val KEY_LOCATION_SOURCE = stringPreferencesKey("location_source")
    }

    val authToken: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_AUTH_TOKEN]
    }

    val isLoggedIn: Flow<Boolean> = context.dataStore.data.map { preferences ->
        preferences[KEY_IS_LOGGED_IN] == "true"
    }

    val userId: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_USER_ID]
    }

    val userName: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_USER_NAME]
    }

    val userEmail: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_USER_EMAIL]
    }

    val userPhoto: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_USER_PHOTO]
    }

    val fcmToken: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_FCM_TOKEN]
    }

    val authProvider: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_AUTH_PROVIDER]
    }

    val hasSeenOnboarding: Flow<Boolean> = context.dataStore.data.map { preferences ->
        preferences[KEY_HAS_SEEN_ONBOARDING] == "true"
    }

    val clinicSlug: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_CLINIC_SLUG]
    }

    val clinicName: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_CLINIC_NAME]
    }

    val clinicLogo: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_CLINIC_LOGO]
    }

    val clinicColor: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_CLINIC_COLOR]
    }

    val clinicAddress: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[KEY_CLINIC_ADDRESS]
    }

    val clinicId: Flow<Int?> = context.dataStore.data.map { preferences ->
        preferences[KEY_CLINIC_ID]?.toIntOrNull()
    }

    /** Booking ids (as strings) already nudged for a doctor rating. */
    val ratingPromptedIds: Flow<Set<String>> = context.dataStore.data.map { preferences ->
        preferences[KEY_RATING_PROMPTED_IDS] ?: emptySet()
    }

    suspend fun markRatingPrompted(ids: Collection<Int>) {
        if (ids.isEmpty()) return
        context.dataStore.edit { preferences ->
            val current = preferences[KEY_RATING_PROMPTED_IDS] ?: emptySet()
            preferences[KEY_RATING_PROMPTED_IDS] = current + ids.map { it.toString() }
        }
    }

    suspend fun setOnboardingSeen() {
        context.dataStore.edit { preferences ->
            preferences[KEY_HAS_SEEN_ONBOARDING] = "true"
        }
    }

    suspend fun saveAuthToken(token: String) {
        context.dataStore.edit { preferences ->
            preferences[KEY_AUTH_TOKEN] = token
        }
    }

    suspend fun saveUserInfo(userId: Int, email: String, name: String, photo: String?) {
        context.dataStore.edit { preferences ->
            preferences[KEY_USER_ID] = userId.toString()
            preferences[KEY_USER_EMAIL] = email
            preferences[KEY_USER_NAME] = name
            if (photo.isNullOrBlank()) {
                preferences.remove(KEY_USER_PHOTO)
            } else {
                preferences[KEY_USER_PHOTO] = photo
            }
            preferences[KEY_IS_LOGGED_IN] = "true"
        }
    }

    suspend fun saveAuthProvider(provider: String) {
        context.dataStore.edit { preferences ->
            preferences[KEY_AUTH_PROVIDER] = provider
        }
    }

    suspend fun saveFcmToken(token: String) {
        context.dataStore.edit { preferences ->
            preferences[KEY_FCM_TOKEN] = token
        }
    }

    suspend fun saveClinic(
        id: Int?,
        name: String?,
        slug: String?,
        logoUrl: String?,
        color: String?,
        address: String?
    ) {
        context.dataStore.edit { preferences ->
            if (id != null) preferences[KEY_CLINIC_ID] = id.toString()
            else preferences.remove(KEY_CLINIC_ID)
            if (!name.isNullOrBlank()) preferences[KEY_CLINIC_NAME] = name
            else preferences.remove(KEY_CLINIC_NAME)
            if (!slug.isNullOrBlank()) preferences[KEY_CLINIC_SLUG] = slug
            else preferences.remove(KEY_CLINIC_SLUG)
            if (!logoUrl.isNullOrBlank()) preferences[KEY_CLINIC_LOGO] = logoUrl
            else preferences.remove(KEY_CLINIC_LOGO)
            if (!color.isNullOrBlank()) preferences[KEY_CLINIC_COLOR] = color
            else preferences.remove(KEY_CLINIC_COLOR)
            if (!address.isNullOrBlank()) preferences[KEY_CLINIC_ADDRESS] = address
            else preferences.remove(KEY_CLINIC_ADDRESS)
        }
    }

    suspend fun clearClinic() {
        context.dataStore.edit { preferences ->
            preferences.remove(KEY_CLINIC_ID)
            preferences.remove(KEY_CLINIC_NAME)
            preferences.remove(KEY_CLINIC_SLUG)
            preferences.remove(KEY_CLINIC_LOGO)
            preferences.remove(KEY_CLINIC_COLOR)
            preferences.remove(KEY_CLINIC_ADDRESS)
        }
    }

    // ── MULTI-CLINIC DISCOVERY: lokasi pengguna ────────────────────────────

    /** Null = user belum pernah mengatur lokasi (state "Lokasi belum diatur"). */
    val userLocation: Flow<UserLocation?> = context.dataStore.data.map { preferences ->
        val lat = preferences[KEY_LOCATION_LAT]?.toDoubleOrNull() ?: return@map null
        val lng = preferences[KEY_LOCATION_LNG]?.toDoubleOrNull() ?: return@map null
        UserLocation(
            latitude = lat,
            longitude = lng,
            label = preferences[KEY_LOCATION_LABEL],
            source = if (preferences[KEY_LOCATION_SOURCE] == LocationSource.MANUAL.name) {
                LocationSource.MANUAL
            } else {
                LocationSource.GPS
            }
        )
    }

    suspend fun saveUserLocation(location: UserLocation) {
        context.dataStore.edit { preferences ->
            preferences[KEY_LOCATION_LAT] = location.latitude.toString()
            preferences[KEY_LOCATION_LNG] = location.longitude.toString()
            if (!location.label.isNullOrBlank()) {
                preferences[KEY_LOCATION_LABEL] = location.label
            } else {
                preferences.remove(KEY_LOCATION_LABEL)
            }
            preferences[KEY_LOCATION_SOURCE] = location.source.name
        }
    }

    suspend fun clearUserLocation() {
        context.dataStore.edit { preferences ->
            preferences.remove(KEY_LOCATION_LAT)
            preferences.remove(KEY_LOCATION_LNG)
            preferences.remove(KEY_LOCATION_LABEL)
            preferences.remove(KEY_LOCATION_SOURCE)
        }
    }

    suspend fun clearSession() {
        context.dataStore.edit { preferences ->
            preferences.remove(KEY_AUTH_TOKEN)
            preferences.remove(KEY_USER_ID)
            preferences.remove(KEY_USER_EMAIL)
            preferences.remove(KEY_USER_NAME)
            preferences.remove(KEY_USER_PHOTO)
            preferences.remove(KEY_IS_LOGGED_IN)
            preferences.remove(KEY_FCM_TOKEN)
        }
    }

    suspend fun clearAll() {
        context.dataStore.edit { preferences ->
            preferences.clear()
        }
    }

}
