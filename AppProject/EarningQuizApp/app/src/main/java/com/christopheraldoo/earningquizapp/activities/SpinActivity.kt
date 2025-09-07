package com.christopheraldoo.earningquizapp.activities

import android.animation.ObjectAnimator
import android.os.Bundle
import android.view.animation.AccelerateDecelerateInterpolator
import android.widget.Button
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager
import kotlin.random.Random

/**
 * Activity for the spin wheel feature.
 *
 * This screen provides a fun way for users to earn bonus points
 * through a spinning wheel game. Users can spin once per day.
 */
class SpinActivity : AppCompatActivity() {

    private lateinit var ivSpinWheel: ImageView
    private lateinit var btnSpin: Button
    private lateinit var tvCurrentPoints: TextView
    private lateinit var tvSpinResult: TextView

    private var isSpinning = false
    private var hasSpunToday = false

    // Possible rewards for the spin wheel
    private val spinRewards = listOf(10, 25, 50, 75, 100, 150, 200, 300)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_spin)

        initializeViews()
        setupInitialState()
        setupClickListeners()
    }

    /**
     * Initialize view components
     */
    private fun initializeViews() {
        ivSpinWheel = findViewById(R.id.iv_spin_wheel)
        btnSpin = findViewById(R.id.btn_spin)
        tvCurrentPoints = findViewById(R.id.tv_current_points)
        tvSpinResult = findViewById(R.id.tv_spin_result)
    }

    /**
     * Setup initial state of the activity
     */
    private fun setupInitialState() {
        // Load current user points
        updatePointsDisplay()
        
        // Check if user has already spun today
        hasSpunToday = SharedPrefsManager.hasSpunToday(this)
        updateSpinButtonState()
        
        // Reset spin result text
        tvSpinResult.text = getString(R.string.spin_instruction)
    }

    /**
     * Setup click listeners
     */
    private fun setupClickListeners() {
        btnSpin.setOnClickListener {
            if (!isSpinning && !hasSpunToday) {
                performSpin()
            }
        }
    }

    /**
     * Perform the spinning animation and reward calculation
     */
    private fun performSpin() {
        isSpinning = true
        btnSpin.isEnabled = false
        
        // Generate random rotation (multiple full rotations + random angle)
        val randomRotation = Random.nextFloat() * 360f + (360f * 3) // 3+ full rotations
        
        // Create rotation animation
        val rotationAnimator = ObjectAnimator.ofFloat(ivSpinWheel, "rotation", 0f, randomRotation)
        rotationAnimator.duration = 3000 // 3 seconds
        rotationAnimator.interpolator = AccelerateDecelerateInterpolator()
        
        rotationAnimator.addListener(object : android.animation.Animator.AnimatorListener {
            override fun onAnimationStart(animation: android.animation.Animator) {
                tvSpinResult.text = getString(R.string.spin_spinning)
            }
            
            override fun onAnimationEnd(animation: android.animation.Animator) {
                onSpinComplete()
            }
            
            override fun onAnimationCancel(animation: android.animation.Animator) {}
            override fun onAnimationRepeat(animation: android.animation.Animator) {}
        })
        
        rotationAnimator.start()
    }

    /**
     * Handle spin completion and reward distribution
     */
    private fun onSpinComplete() {
        isSpinning = false
        hasSpunToday = true
        
        // Calculate reward based on wheel position
        val reward = spinRewards.random()
          // Add points to user account
        val currentUser = SharedPrefsManager.getCurrentUser(this)
        currentUser?.let { user ->
            val updatedUser = user.copy(points = user.points + reward)
            SharedPrefsManager.saveUser(this, updatedUser)
        }
        
        // Save that user has spun today
        SharedPrefsManager.setSpunToday(this, true)
        
        // Update UI
        updatePointsDisplay()
        updateSpinButtonState()
        
        // Show result
        tvSpinResult.text = getString(R.string.spin_result, reward)
        Toast.makeText(this, getString(R.string.spin_success, reward), Toast.LENGTH_LONG).show()
    }

    /**
     * Update points display
     */
    private fun updatePointsDisplay() {
        val currentUser = SharedPrefsManager.getCurrentUser(this)
        val points = currentUser?.points ?: 0
        tvCurrentPoints.text = getString(R.string.current_points, points)
    }

    /**
     * Update spin button state based on daily limit
     */
    private fun updateSpinButtonState() {
        if (hasSpunToday) {
            btnSpin.isEnabled = false
            btnSpin.text = getString(R.string.spin_completed_today)
            tvSpinResult.text = getString(R.string.spin_come_back_tomorrow)
        } else {
            btnSpin.isEnabled = true
            btnSpin.text = getString(R.string.action_spin)
        }
    }

    /**
     * Reset the wheel rotation for visual consistency
     */
    override fun onResume() {
        super.onResume()
        if (!isSpinning) {
            ivSpinWheel.rotation = 0f
        }
    }
}
