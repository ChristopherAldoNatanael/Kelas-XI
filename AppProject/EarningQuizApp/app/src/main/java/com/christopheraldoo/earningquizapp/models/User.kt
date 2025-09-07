package com.christopheraldoo.earningquizapp.models

/**
 * Data class representing a user in the application.
 *
 * This model holds all the necessary information about a user, including their
 * unique identifier, personal details, and their statistics within the quiz app.
 *
 * @property id A unique identifier for the user.
 * @property fullName The full name of the user.
 * @property email The user's email address, used for login.
 * @property password The user's password (in real app, this should be hashed).
 * @property avatarUrl A URL string pointing to the user's profile picture.
 * @property points The total points or score the user has accumulated.
 * @property rank The user's current rank on the leaderboard.
 * @property quizzesCompleted Total number of quizzes completed by the user.
 * @property correctAnswers Total number of correct answers given by the user.
 * @property joinDate The date when the user joined the app.
 */
data class User(
    val id: String,
    var fullName: String,
    var email: String,
    var password: String,
    var avatarUrl: String? = null,
    var points: Int = 0,
    var rank: Int = 0,
    var quizzesCompleted: Int = 0,
    var correctAnswers: Int = 0,
    val joinDate: Long = System.currentTimeMillis()
)
