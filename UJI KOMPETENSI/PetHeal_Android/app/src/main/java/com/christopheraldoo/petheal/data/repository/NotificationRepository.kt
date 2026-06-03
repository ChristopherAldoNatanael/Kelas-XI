package com.christopheraldoo.petheal.data.repository

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.christopheraldoo.petheal.data.model.AppNotification
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

private val Context.notifDataStore: DataStore<Preferences> by preferencesDataStore(
    name = "petheal_notifications"
)

@Singleton
class NotificationRepository @Inject constructor(
    @ApplicationContext private val context: Context
) {
    private val gson = Gson()

    companion object {
        private val KEY_NOTIFICATIONS = stringPreferencesKey("notifications_json")
        private val KEY_UNREAD_COUNT  = intPreferencesKey("unread_count")
        private const val MAX_STORED  = 50
    }

    val notifications: Flow<List<AppNotification>> = context.notifDataStore.data.map { prefs ->
        val json = prefs[KEY_NOTIFICATIONS] ?: return@map emptyList()
        try {
            val type = object : TypeToken<List<AppNotification>>() {}.type
            gson.fromJson(json, type) ?: emptyList()
        } catch (e: Exception) {
            emptyList()
        }
    }

    val unreadCount: Flow<Int> = context.notifDataStore.data.map { prefs ->
        prefs[KEY_UNREAD_COUNT] ?: 0
    }

    suspend fun addNotification(notification: AppNotification) {
        context.notifDataStore.edit { prefs ->
            val existing = run {
                val json = prefs[KEY_NOTIFICATIONS] ?: "[]"
                try {
                    val type = object : TypeToken<List<AppNotification>>() {}.type
                    gson.fromJson<List<AppNotification>>(json, type) ?: emptyList()
                } catch (e: Exception) { emptyList() }
            }
            // Prepend newest, cap at MAX_STORED
            val updated = (listOf(notification) + existing).take(MAX_STORED)
            prefs[KEY_NOTIFICATIONS] = gson.toJson(updated)
            prefs[KEY_UNREAD_COUNT]  = (prefs[KEY_UNREAD_COUNT] ?: 0) + 1
        }
    }

    suspend fun markAllRead() {
        context.notifDataStore.edit { prefs ->
            val existing = run {
                val json = prefs[KEY_NOTIFICATIONS] ?: "[]"
                try {
                    val type = object : TypeToken<List<AppNotification>>() {}.type
                    gson.fromJson<List<AppNotification>>(json, type) ?: emptyList()
                } catch (e: Exception) { emptyList() }
            }
            val updated = existing.map { it.copy(isRead = true) }
            prefs[KEY_NOTIFICATIONS] = gson.toJson(updated)
            prefs[KEY_UNREAD_COUNT]  = 0
        }
    }

    suspend fun markRead(id: String) {
        context.notifDataStore.edit { prefs ->
            val existing = run {
                val json = prefs[KEY_NOTIFICATIONS] ?: "[]"
                try {
                    val type = object : TypeToken<List<AppNotification>>() {}.type
                    gson.fromJson<List<AppNotification>>(json, type) ?: emptyList()
                } catch (e: Exception) { emptyList() }
            }
            val updated = existing.map { if (it.id == id) it.copy(isRead = true) else it }
            prefs[KEY_NOTIFICATIONS] = gson.toJson(updated)
            // Recalculate unread
            prefs[KEY_UNREAD_COUNT] = updated.count { !it.isRead }
        }
    }

    suspend fun clearAll() {
        context.notifDataStore.edit { prefs ->
            prefs[KEY_NOTIFICATIONS] = "[]"
            prefs[KEY_UNREAD_COUNT]  = 0
        }
    }
}
