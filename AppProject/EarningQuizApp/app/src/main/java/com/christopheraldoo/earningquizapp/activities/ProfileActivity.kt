package com.christopheraldoo.earningquizapp.activities

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager

/**
 * Activity for displaying user profile information.
 *
 * This screen shows user's personal information, statistics,
 * and provides options to edit profile or logout.
 */
class ProfileActivity : AppCompatActivity() {

    private lateinit var tvUserName: TextView
    private lateinit var tvUserEmail: TextView
    private lateinit var tvTotalPoints: TextView
    private lateinit var tvQuizzesCompleted: TextView
    private lateinit var tvCorrectAnswers: TextView
    private lateinit var tvAccuracyRate: TextView
    private lateinit var btnEditProfile: Button
    private lateinit var btnLogout: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_profile)

        initializeViews()
        loadUserData()
        setupClickListeners()
    }

    /**
     * Initialize view components
     */
    private fun initializeViews() {
        tvUserName = findViewById(R.id.tv_user_name)
        tvUserEmail = findViewById(R.id.tv_user_email)
        tvTotalPoints = findViewById(R.id.tv_total_points)
        tvQuizzesCompleted = findViewById(R.id.tv_quizzes_completed)
        tvCorrectAnswers = findViewById(R.id.tv_correct_answers)
        tvAccuracyRate = findViewById(R.id.tv_accuracy_rate)
        btnEditProfile = findViewById(R.id.btn_edit_profile)
        btnLogout = findViewById(R.id.btn_logout)
    }

    /**
     * Load and display user data
     */
    private fun loadUserData() {
        val currentUser = SharedPrefsManager.getCurrentUser(this)
        
        if (currentUser != null) {
            // Display basic user information
            tvUserName.text = currentUser.fullName
            tvUserEmail.text = currentUser.email
            tvTotalPoints.text = getString(R.string.points_format, currentUser.points)
            
            // Load quiz statistics
            loadQuizStatistics()
        } else {
            // Handle case where user data is not found
            navigateToLogin()
        }
    }

    /**
     * Load quiz statistics from SharedPreferences
     */
    private fun loadQuizStatistics() {
        val totalQuizzes = SharedPrefsManager.getTotalQuizzesCompleted(this)
        val correctAnswers = SharedPrefsManager.getTotalCorrectAnswers(this)
        val totalQuestions = SharedPrefsManager.getTotalQuestionsAnswered(this)
        
        tvQuizzesCompleted.text = totalQuizzes.toString()
        tvCorrectAnswers.text = correctAnswers.toString()
        
        // Calculate accuracy rate
        val accuracyRate = if (totalQuestions > 0) {
            (correctAnswers.toFloat() / totalQuestions.toFloat() * 100).toInt()
        } else {
            0
        }
        
        tvAccuracyRate.text = getString(R.string.percentage_format, accuracyRate)
    }

    /**
     * Setup click listeners
     */
    private fun setupClickListeners() {
        btnEditProfile.setOnClickListener {
            navigateToEditProfile()
        }

        btnLogout.setOnClickListener {
            performLogout()
        }
    }

    /**
     * Navigate to edit profile activity
     */
    private fun navigateToEditProfile() {
        val intent = Intent(this, EditProfileActivity::class.java)
        startActivity(intent)
    }

    /**
     * Perform user logout
     */
    private fun performLogout() {
        // Show confirmation dialog
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle(getString(R.string.logout_confirmation_title))
            .setMessage(getString(R.string.logout_confirmation_message))
            .setPositiveButton(getString(R.string.action_logout)) { _, _ ->
                // Clear user session
                SharedPrefsManager.clearUserSession(this)
                
                // Navigate to login
                navigateToLogin()
            }
            .setNegativeButton(getString(R.string.action_cancel), null)
            .show()
    }

    /**
     * Navigate to login activity
     */
    private fun navigateToLogin() {
        val intent = Intent(this, LoginActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        startActivity(intent)
        finish()
    }

    /**
     * Refresh user data when returning from edit profile
     */
    override fun onResume() {
        super.onResume()
        loadUserData()
    }
}
