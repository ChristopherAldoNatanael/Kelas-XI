package com.christopheraldoo.earningquizapp.utils

import android.util.Patterns

/**
 * A utility object for input validation.
 *
 * Provides simple, reusable functions to validate common input fields like
 * emails and passwords. This helps ensure data integrity before processing.
 */
object ValidationUtils {

    /**
     * Checks if an email address has a valid format.
     *
     * @param email The email string to validate.
     * @return True if the email is valid, false otherwise.
     */
    fun isValidEmail(email: CharSequence): Boolean {
        return Patterns.EMAIL_ADDRESS.matcher(email).matches()
    }

    /**
     * Checks if a password meets the minimum length requirement.
     *
     * @param password The password string to validate.
     * @param minLength The minimum required length for the password.
     * @return True if the password is long enough, false otherwise.
     */
    fun isValidPassword(password: String, minLength: Int = 6): Boolean {
        return password.length >= minLength
    }

    /**
     * Checks if two passwords match.
     *
     * @param password The first password.
     * @param confirmPassword The second password to compare against.
     * @return True if the passwords match, false otherwise.
     */
    fun doPasswordsMatch(password: String, confirmPassword: String): Boolean {
        return password == confirmPassword
    }

    /**
     * Checks if a text field is empty.
     *
     * @param text The text to check.
     * @return True if the text is not empty, false otherwise.
     */
    fun isNotEmpty(text: String): Boolean {
        return text.trim().isNotEmpty()
    }
}
