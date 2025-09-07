package com.christopheraldoo.earningquizapp.models

/**
 * Data class representing a quiz category.
 *
 * This model holds information about different subject categories
 * available for quiz selection.
 *
 * @property id Unique identifier for the category.
 * @property name Display name of the category.
 * @property description Brief description of the category.
 * @property iconResId Resource ID for the category icon.
 * @property color Color resource ID for category theming.
 * @property questionCount Total number of questions in this category.
 * @property isActive Whether this category is currently active.
 */
data class Category(
    val id: String,
    val name: String,
    val description: String,
    val iconResId: Int,
    val color: Int,
    val questionCount: Int = 10,
    val isActive: Boolean = true
)
