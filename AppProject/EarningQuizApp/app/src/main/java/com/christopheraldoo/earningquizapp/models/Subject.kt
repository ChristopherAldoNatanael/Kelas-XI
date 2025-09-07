package com.christopheraldoo.earningquizapp.models

/**
 * Data class representing a subject or category for quizzes.
 *
 * This model is used to display the different quiz categories available to the user.
 *
 * @property name The name of the subject (e.g., "Mathematics", "History").
 * @property iconResId The resource ID for the drawable icon representing the subject.
 */
data class Subject(
    val name: String,
    val iconResId: Int
)
