package com.christopheraldoo.petheal.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
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
        private val KEY_AUTH_TOKEN = stringPreferencesKey("auth_token")
        private val KEY_USER_ID = stringPreferencesKey("user_id")
        private val KEY_USER_EMAIL = stringPreferencesKey("user_email")
        private val KEY_USER_NAME = stringPreferencesKey("user_name")
        private val KEY_USER_PHOTO = stringPreferencesKey("user_photo")
        private val KEY_IS_LOGGED_IN = stringPreferencesKey("is_logged_in")
        private val KEY_FCM_TOKEN = stringPreferencesKey("fcm_token")
        private val KEY_HAS_SEEN_ONBOARDING = stringPreferencesKey("has_seen_onboarding")
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

    val hasSeenOnboarding: Flow<Boolean> = context.dataStore.data.map { preferences ->
        preferences[KEY_HAS_SEEN_ONBOARDING] == "true"
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

    suspend fun saveFcmToken(token: String) {
        context.dataStore.edit { preferences ->
            preferences[KEY_FCM_TOKEN] = token
        }
    }

    suspend fun clearAll() {
        context.dataStore.edit { preferences ->
            preferences.clear()
        }
    }

}
