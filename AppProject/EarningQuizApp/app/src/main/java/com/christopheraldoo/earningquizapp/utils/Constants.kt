package com.christopheraldoo.earningquizapp.utils

/**
 * Constants used throughout the application.
 *
 * This object holds all the constant values used across different parts
 * of the app to ensure consistency and ease of maintenance.
 */
object Constants {

    // ==================== POINT SYSTEM ====================
    const val POINTS_PER_CORRECT_ANSWER = 10
    const val BONUS_POINTS_COMPLETION = 50
    const val MINIMUM_WITHDRAWAL_POINTS = 1000

    // ==================== QUIZ SETTINGS ====================
    const val QUESTIONS_PER_QUIZ = 10
    const val QUIZ_TIME_LIMIT_SECONDS = 30
    const val QUIZ_PASSING_PERCENTAGE = 60

    // ==================== SPIN WHEEL ====================
    const val MIN_SPIN_REWARD = 10
    const val MAX_SPIN_REWARD = 300
    const val SPINS_PER_DAY = 1

    // ==================== USER LEVELS ====================
    const val BEGINNER_THRESHOLD = 100
    const val INTERMEDIATE_THRESHOLD = 500
    const val ADVANCED_THRESHOLD = 1000
    const val EXPERT_THRESHOLD = 2000

    // ==================== ANIMATION DURATIONS ====================
    const val ANIMATION_DURATION_SHORT = 200L
    const val ANIMATION_DURATION_MEDIUM = 500L
    const val ANIMATION_DURATION_LONG = 1000L
    const val SPIN_ANIMATION_DURATION = 3000L

    // ==================== INTENT EXTRAS ====================
    const val EXTRA_CATEGORY_ID = "CATEGORY_ID"
    const val EXTRA_CATEGORY_NAME = "CATEGORY_NAME"
    const val EXTRA_QUIZ_SCORE = "QUIZ_SCORE"
    const val EXTRA_TOTAL_QUESTIONS = "TOTAL_QUESTIONS"
    const val EXTRA_CORRECT_ANSWERS = "CORRECT_ANSWERS"

    // ==================== QUIZ CATEGORIES ====================
    const val CATEGORY_MATH = "math"
    const val CATEGORY_SCIENCE = "science"
    const val CATEGORY_HISTORY = "history"
    const val CATEGORY_GEOGRAPHY = "geography"
    const val CATEGORY_ENGLISH = "english"
    const val CATEGORY_BIOLOGY = "biology"
    const val CATEGORY_PHYSICS = "physics"
    const val CATEGORY_CHEMISTRY = "chemistry"

    // ==================== USER PREFERENCES ====================
    const val PREF_SOUND_ENABLED = "sound_enabled"
    const val PREF_NOTIFICATIONS_ENABLED = "notifications_enabled"
    const val PREF_THEME_MODE = "theme_mode"

    // ==================== VALIDATION RULES ====================
    const val MIN_PASSWORD_LENGTH = 6
    const val MAX_NAME_LENGTH = 50
    const val MIN_NAME_LENGTH = 2

    // ==================== ERROR CODES ====================
    const val ERROR_NETWORK = "NETWORK_ERROR"
    const val ERROR_VALIDATION = "VALIDATION_ERROR"
    const val ERROR_USER_NOT_FOUND = "USER_NOT_FOUND"
    const val ERROR_INSUFFICIENT_POINTS = "INSUFFICIENT_POINTS"

    // ==================== SUCCESS CODES ====================
    const val SUCCESS_LOGIN = "LOGIN_SUCCESS"
    const val SUCCESS_REGISTER = "REGISTER_SUCCESS"
    const val SUCCESS_QUIZ_COMPLETED = "QUIZ_COMPLETED"
    const val SUCCESS_WITHDRAWAL = "WITHDRAWAL_SUCCESS"
}
