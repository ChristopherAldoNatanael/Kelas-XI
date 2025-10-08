package com.christopheraldoo.aplikasimonitoringkelas.util

import android.content.Context
import android.content.SharedPreferences

class SessionManager(context: Context) {
    private val pref: SharedPreferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
    private val editor: SharedPreferences.Editor = pref.edit()

    companion object {
        private const val PREF_NAME = "MonitoringKelasSession"
        private const val IS_LOGIN = "IsLoggedIn"
        private const val KEY_ID = "userId"
        private const val KEY_NAME = "userName"
        private const val KEY_EMAIL = "userEmail"
        private const val KEY_ROLE = "userRole"
    }

    fun createLoginSession(id: Long, name: String, email: String, role: String) {
        editor.apply {
            putBoolean(IS_LOGIN, true)
            putLong(KEY_ID, id)
            putString(KEY_NAME, name)
            putString(KEY_EMAIL, email)
            putString(KEY_ROLE, role)
            apply()
        }
    }

    fun getUserDetails(): HashMap<String, String> {
        val user = HashMap<String, String>()
        user["userId"] = pref.getLong(KEY_ID, -1).toString()
        user["userName"] = pref.getString(KEY_NAME, "").toString()
        user["userEmail"] = pref.getString(KEY_EMAIL, "").toString()
        user["userRole"] = pref.getString(KEY_ROLE, "").toString()
        return user
    }

    fun logoutUser() {
        editor.apply {
            clear()
            apply()
        }
    }

    fun isLoggedIn(): Boolean {
        return pref.getBoolean(IS_LOGIN, false)
    }
}