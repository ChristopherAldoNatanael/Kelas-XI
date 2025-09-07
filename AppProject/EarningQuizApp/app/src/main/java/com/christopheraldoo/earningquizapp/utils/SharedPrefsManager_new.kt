package com.christopheraldoo.earningquizapp.utils

import android.content.Context
import android.content.SharedPreferences
import com.christopheraldoo.earningquizapp.models.User
import com.google.gson.Gson
import java.text.SimpleDateFormat
import java.util.*

/**
 * A singleton utility object for managing SharedPreferences.
 *
 * This class simplifies the process of reading from and writing to SharedPreferences.
 * It is used to persist user session data, statistics, and app preferences locally.
 */
object SharedPrefsManager {

    private const val PREF_NAME = "EarningQuizAppPrefs"
    
    // User session keys
    private const val KEY_IS_LOGGED_IN = "isLoggedIn"
    private const val KEY_CURRENT_USER = "currentUser"
    
    // Statistics keys
    private const val KEY_TOTAL_QUIZZES = "totalQuizzes"
    private const val KEY_CORRECT_ANSWERS = "correctAnswers"
    private const val KEY_TOTAL_QUESTIONS = "totalQuestions"
    
    // Spin wheel keys
    private const val KEY_LAST_SPIN_DATE = "lastSpinDate"
    private const val KEY_HAS_SPUN_TODAY = "hasSpunToday"

    private val gson = Gson()
    private val dateFormat = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())

    /**
     * Get SharedPreferences instance
     */
    private fun getPrefs(context: Context): SharedPreferences {
        return context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
    }

    // ==================== USER SESSION MANAGEMENT ====================

    /**
     * Save user data and set logged in status
     */
    fun saveUser(context: Context, user: User) {
        val editor = getPrefs(context).edit()
        editor.putString(KEY_CURRENT_USER, gson.toJson(user))
        editor.putBoolean(KEY_IS_LOGGED_IN, true)
        editor.apply()
    }

    /**
     * Get current logged-in user
     */
    fun getCurrentUser(context: Context): User? {
        val userJson = getPrefs(context).getString(KEY_CURRENT_USER, null)
        return if (userJson != null) {
            try {
                gson.fromJson(userJson, User::class.java)
            } catch (e: Exception) {
                null
            }
        } else {
            null
        }
    }

    /**
     * Check if user is logged in
     */
    fun isLoggedIn(context: Context): Boolean {
        return getPrefs(context).getBoolean(KEY_IS_LOGGED_IN, false)
    }

    /**
     * Set logged in status
     */
    fun setLoggedIn(context: Context, isLoggedIn: Boolean) {
        val editor = getPrefs(context).edit()
        editor.putBoolean(KEY_IS_LOGGED_IN, isLoggedIn)
        editor.apply()
    }

    /**
     * Clear user session and all data
     */
    fun clearUserSession(context: Context) {
        val editor = getPrefs(context).edit()
        editor.clear()
        editor.apply()
    }

    // ==================== QUIZ STATISTICS ====================

    /**
     * Increment total quizzes completed
     */
    fun incrementQuizzesCompleted(context: Context) {
        val current = getTotalQuizzesCompleted(context)
        val editor = getPrefs(context).edit()
        editor.putInt(KEY_TOTAL_QUIZZES, current + 1)
        editor.apply()
    }

    /**
     * Get total quizzes completed
     */
    fun getTotalQuizzesCompleted(context: Context): Int {
        return getPrefs(context).getInt(KEY_TOTAL_QUIZZES, 0)
    }

    /**
     * Add correct answers count
     */
    fun addCorrectAnswers(context: Context, count: Int) {
        val current = getTotalCorrectAnswers(context)
        val editor = getPrefs(context).edit()
        editor.putInt(KEY_CORRECT_ANSWERS, current + count)
        editor.apply()
    }

    /**
     * Get total correct answers
     */
    fun getTotalCorrectAnswers(context: Context): Int {
        return getPrefs(context).getInt(KEY_CORRECT_ANSWERS, 0)
    }

    /**
     * Add total questions answered
     */
    fun addTotalQuestions(context: Context, count: Int) {
        val current = getTotalQuestionsAnswered(context)
        val editor = getPrefs(context).edit()
        editor.putInt(KEY_TOTAL_QUESTIONS, current + count)
        editor.apply()
    }

    /**
     * Get total questions answered
     */
    fun getTotalQuestionsAnswered(context: Context): Int {
        return getPrefs(context).getInt(KEY_TOTAL_QUESTIONS, 0)
    }

    // ==================== SPIN WHEEL MANAGEMENT ====================

    /**
     * Check if user has spun today
     */
    fun hasSpunToday(context: Context): Boolean {
        val lastSpinDate = getPrefs(context).getString(KEY_LAST_SPIN_DATE, "")
        val today = dateFormat.format(Date())
        return lastSpinDate == today
    }

    /**
     * Set that user has spun today
     */
    fun setSpunToday(context: Context, hasSpun: Boolean) {
        val editor = getPrefs(context).edit()
        if (hasSpun) {
            val today = dateFormat.format(Date())
            editor.putString(KEY_LAST_SPIN_DATE, today)
        }
        editor.putBoolean(KEY_HAS_SPUN_TODAY, hasSpun)
        editor.apply()
    }

    // ==================== USER MANAGEMENT COMPATIBILITY ====================

    /**
     * Login user compatibility method
     */
    fun loginUser(context: Context, user: User) {
        saveUser(context, user)
    }

    /**
     * Logout user compatibility method
     */
    fun logoutUser(context: Context) {
        clearUserSession(context)
    }

    /**
     * Get user compatibility method
     */
    fun getUser(context: Context): User? {
        return getCurrentUser(context)
    }
}
