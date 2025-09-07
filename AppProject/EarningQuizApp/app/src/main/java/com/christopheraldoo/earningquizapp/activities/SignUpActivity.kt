package com.christopheraldoo.earningquizapp.activities

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.MainActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.User
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager
import com.christopheraldoo.earningquizapp.utils.ValidationUtils
import java.util.UUID

/**
 * Activity for new user registration/sign up.
 *
 * This screen allows a new user to create an account by providing their name,
 * email, and a password. It includes basic validation and follows material design principles.
 */
class SignUpActivity : AppCompatActivity() {

    private lateinit var etFullName: EditText
    private lateinit var etEmail: EditText
    private lateinit var etPassword: EditText
    private lateinit var etConfirmPassword: EditText
    private lateinit var btnSignUp: Button
    private lateinit var tvLoginPrompt: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_signup)

        initializeViews()
        setupClickListeners()
    }

    /**
     * Initialize all view components
     */
    private fun initializeViews() {
        etFullName = findViewById(R.id.et_full_name)
        etEmail = findViewById(R.id.et_email)
        etPassword = findViewById(R.id.et_password)
        etConfirmPassword = findViewById(R.id.et_confirm_password)
        btnSignUp = findViewById(R.id.btn_signup)
        tvLoginPrompt = findViewById(R.id.tv_login_prompt)
    }

    /**
     * Setup click listeners for interactive elements
     */
    private fun setupClickListeners() {
        btnSignUp.setOnClickListener {
            performSignUp()
        }

        tvLoginPrompt.setOnClickListener {
            navigateToLogin()
        }
    }

    /**
     * Perform user registration with validation
     */
    private fun performSignUp() {
        val fullName = etFullName.text.toString().trim()
        val email = etEmail.text.toString().trim()
        val password = etPassword.text.toString().trim()
        val confirmPassword = etConfirmPassword.text.toString().trim()

        // Comprehensive validation
        if (!validateInputs(fullName, email, password, confirmPassword)) {
            return
        }

        // Create new user
        val newUser = User(
            id = UUID.randomUUID().toString(),
            fullName = fullName,
            email = email,
            password = password, // In real app, this should be hashed
            points = 0
        )

        // Save user data
        SharedPrefsManager.saveUser(this, newUser)
        SharedPrefsManager.setLoggedIn(this, true)

        // Show success message
        Toast.makeText(this, getString(R.string.message_signup_success), Toast.LENGTH_SHORT).show()

        // Navigate to main app
        navigateToHome()
    }

    /**
     * Validate all input fields
     */
    private fun validateInputs(fullName: String, email: String, password: String, confirmPassword: String): Boolean {
        // Check if all fields are filled
        if (!ValidationUtils.isNotEmpty(fullName)) {
            etFullName.error = getString(R.string.error_field_required)
            etFullName.requestFocus()
            return false
        }

        if (!ValidationUtils.isNotEmpty(email)) {
            etEmail.error = getString(R.string.error_field_required)
            etEmail.requestFocus()
            return false
        }

        if (!ValidationUtils.isNotEmpty(password)) {
            etPassword.error = getString(R.string.error_field_required)
            etPassword.requestFocus()
            return false
        }

        // Validate email format
        if (!ValidationUtils.isValidEmail(email)) {
            etEmail.error = getString(R.string.error_invalid_email)
            etEmail.requestFocus()
            return false
        }

        // Validate password strength
        if (!ValidationUtils.isValidPassword(password)) {
            etPassword.error = getString(R.string.error_weak_password)
            etPassword.requestFocus()
            return false
        }

        // Check password confirmation
        if (password != confirmPassword) {
            etConfirmPassword.error = getString(R.string.error_password_mismatch)
            etConfirmPassword.requestFocus()
            return false
        }

        return true
    }

    /**
     * Navigate to login activity
     */
    private fun navigateToLogin() {
        val intent = Intent(this, LoginActivity::class.java)
        startActivity(intent)
        finish()
    }

    /**
     * Navigate to home activity after successful registration
     */
    private fun navigateToHome() {
        val intent = Intent(this, MainActivity::class.java)
        startActivity(intent)
        finish()
    }
}
