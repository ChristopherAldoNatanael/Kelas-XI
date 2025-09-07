package com.christopheraldoo.earningquizapp.activities

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.User
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager
import com.christopheraldoo.earningquizapp.utils.ValidationUtils
import java.util.UUID

/**
 * Activity for new user registration.
 *
 * This screen allows a new user to create an account by providing their name,
 * email, and a password. It includes basic validation.
 */
class RegisterActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)

        val etFullName = findViewById<EditText>(R.id.et_full_name)
        val etEmail = findViewById<EditText>(R.id.et_email)
        val etPassword = findViewById<EditText>(R.id.et_password)
        val etConfirmPassword = findViewById<EditText>(R.id.et_confirm_password)
        val btnRegister = findViewById<Button>(R.id.btn_register)
        val tvLoginPrompt = findViewById<TextView>(R.id.tv_login_prompt)

        btnRegister.setOnClickListener {
            val fullName = etFullName.text.toString().trim()
            val email = etEmail.text.toString().trim()
            val password = etPassword.text.toString().trim()
            val confirmPassword = etConfirmPassword.text.toString().trim()

            // --- Validation Logic --- //
            if (!ValidationUtils.isNotEmpty(fullName) || !ValidationUtils.isNotEmpty(email) || !ValidationUtils.isNotEmpty(password)) {
                Toast.makeText(this, "All fields are required", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            if (!ValidationUtils.isValidEmail(email)) {
                Toast.makeText(this, "Please enter a valid email", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            if (!ValidationUtils.isValidPassword(password)) {
                Toast.makeText(this, "Password must be at least 6 characters", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            if (!ValidationUtils.doPasswordsMatch(password, confirmPassword)) {
                Toast.makeText(this, "Passwords do not match", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            // --- Mock Registration Logic --- //
            // In a real app, this data would be sent to a backend server.
            // Here, we simulate a successful registration and log the user in directly.
            val newUser = User(
                id = UUID.randomUUID().toString(), // Generate a random unique ID
                fullName = fullName,
                email = email,
                password = password
            )

            // Save the new user's session
            SharedPrefsManager.loginUser(this, newUser)

            // Navigate to MainActivity
            Toast.makeText(this, "Registration successful!", Toast.LENGTH_SHORT).show()
            val intent = Intent(this, com.christopheraldoo.earningquizapp.MainActivity::class.java)
            // Clear the activity stack
            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            finish()
        }

        tvLoginPrompt.setOnClickListener {
            // Navigate back to LoginActivity
            finish() // Simply finish this activity to go back to the previous one (Login)
        }
    }
}
