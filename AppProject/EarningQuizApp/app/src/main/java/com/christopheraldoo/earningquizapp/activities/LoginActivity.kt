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
 * Activity for user login.
 *
 * This screen provides fields for email and password and a button to log in.
 * It also includes a link to navigate to the registration screen.
 */
class LoginActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        val etEmail = findViewById<EditText>(R.id.et_email)
        val etPassword = findViewById<EditText>(R.id.et_password)
        val btnLogin = findViewById<Button>(R.id.btn_login)
        val tvRegisterPrompt = findViewById<TextView>(R.id.tv_register_prompt)

        btnLogin.setOnClickListener {
            val email = etEmail.text.toString().trim()
            val password = etPassword.text.toString().trim()

            // --- Mock Login Logic --- //
            // In a real app, you would validate against a backend or a local database.
            // Here, we'll perform basic validation and simulate a successful login.

            if (!ValidationUtils.isNotEmpty(email) || !ValidationUtils.isNotEmpty(password)) {
                Toast.makeText(this, "Email and password cannot be empty", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            if (!ValidationUtils.isValidEmail(email)) {
                Toast.makeText(this, "Please enter a valid email", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            // Simulate a successful login by creating a mock user object.
            // The ID would normally come from a backend.
            val mockUser = User(
                id = UUID.randomUUID().toString(),
                fullName = "Mock User", // In a real app, you'd fetch the user's name
                email = email,
                password = password
            )

            // Save the user session
            SharedPrefsManager.loginUser(this, mockUser)

            // Navigate to MainActivity
            val intent = Intent(this, com.christopheraldoo.earningquizapp.MainActivity::class.java)
            // Clear the activity stack so the user can't go back to the login screen
            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            finish()
        }

        tvRegisterPrompt.setOnClickListener {
            // Navigate to SignUpActivity
            val intent = Intent(this, SignUpActivity::class.java)
            startActivity(intent)
        }
    }
}
