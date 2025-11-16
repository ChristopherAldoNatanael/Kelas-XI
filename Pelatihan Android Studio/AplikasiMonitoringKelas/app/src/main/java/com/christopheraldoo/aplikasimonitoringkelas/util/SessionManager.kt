package com.christopheraldoo.aplikasimonitoringkelas.util

import android.content.Context
import android.content.SharedPreferences

class SessionManager(context: Context) {
    private val pref: SharedPreferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
    
    companion object {
        private const val PREF_NAME = "MonitoringKelasSession"
        private const val IS_LOGIN = "IsLoggedIn"
        private const val KEY_ID = "userId"
        private const val KEY_NAME = "userName"
        private const val KEY_EMAIL = "userEmail"
        private const val KEY_ROLE = "userRole"
        private const val KEY_CLASS_ID = "userClassId"
        private const val KEY_TOKEN = "authToken"
    }
    
    fun createLoginSession(id: Long, name: String, email: String, role: String, token: String, classId: Int? = null) {
        pref.edit().apply {
            putBoolean(IS_LOGIN, true)
            putLong(KEY_ID, id)
            putString(KEY_NAME, name)
            putString(KEY_EMAIL, email)
            putString(KEY_ROLE, role)
            putString(KEY_TOKEN, token) // Save the token
            if (classId != null) {
                putInt(KEY_CLASS_ID, classId)
            }
            apply()
        }
    }

    fun getUserDetails(): HashMap<String, String> {
        val user = HashMap<String, String>()
        user["userId"] = pref.getLong(KEY_ID, -1).toString()
        user["userName"] = pref.getString(KEY_NAME, "") ?: ""
        user["userEmail"] = pref.getString(KEY_EMAIL, "") ?: ""
        user["userRole"] = pref.getString(KEY_ROLE, "") ?: ""
        user["token"] = getAuthToken() ?: ""
        return user
    }

    fun logoutUser() {
        pref.edit().clear().apply()
    }

    fun isLoggedIn(): Boolean {
        return pref.getBoolean(IS_LOGIN, false)
    }

    fun getUserId(): Long? {
        val id = pref.getLong(KEY_ID, -1)
        return if (id != -1L) id else null
    }

    fun getUserName(): String? {
        val name = pref.getString(KEY_NAME, "")
        return name?.takeIf { it.isNotEmpty() }
    }

    fun getUserEmail(): String? {
        val email = pref.getString(KEY_EMAIL, "")
        return email?.takeIf { it.isNotEmpty() }
    }

    fun getUserRole(): String? {
        val role = pref.getString(KEY_ROLE, "")
        return role?.takeIf { it.isNotEmpty() }
    }

    fun getAuthToken(): String? {
        // Try KEY_TOKEN first (new standard)
        var token = pref.getString(KEY_TOKEN, "")
        if (token != null && token.isNotEmpty()) {
            return token
        }
        
        // Fallback to "token" key (used by LoginActivity)
        token = pref.getString("token", "")
        return token?.takeIf { it.isNotEmpty() }
    }

    fun saveAuthToken(token: String) {
        pref.edit().apply {
            putString(KEY_TOKEN, token)
            putString("token", token)  // Also save to "token" key for compatibility
            apply()
        }
    }

    fun getUserClassId(): Int? {
        val classId = pref.getInt(KEY_CLASS_ID, -1)
        return if (classId != -1) classId else null
    }

    fun saveUserClassId(classId: Int) {
        pref.edit().putInt(KEY_CLASS_ID, classId).apply()
    }

    fun logout() {
        pref.edit().clear().apply()
    }

    fun clearSession() {
        pref.edit().clear().apply()
    }
}
