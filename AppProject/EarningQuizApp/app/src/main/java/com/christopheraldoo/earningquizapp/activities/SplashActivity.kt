package com.christopheraldoo.earningquizapp.activities

import android.annotation.SuppressLint
import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager

/**
 * The entry point activity of the application.
 *
 * This activity displays a splash screen for a brief period and then navigates
 * the user to the appropriate screen based on their login status.
 */
@SuppressLint("CustomSplashScreen")
class SplashActivity : AppCompatActivity() {

    // Delay in milliseconds before navigating away from the splash screen.
    private val SPLASH_DELAY: Long = 2000 // 2 seconds

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_splash)

        // Use a Handler to delay the navigation.
        Handler(Looper.getMainLooper()).postDelayed({
            checkUserSession()
        }, SPLASH_DELAY)
    }

    /**
     * Checks the user's session status and navigates accordingly.
     *
     * If the user is logged in, it navigates to HomeActivity.
     * Otherwise, it navigates to LoginActivity.
     */
    private fun checkUserSession() {
        try {
            // Check login status using SharedPrefsManager
            if (SharedPrefsManager.isLoggedIn(this)) {
                // User is logged in, go to MainActivity
                val intent = Intent(this, com.christopheraldoo.earningquizapp.MainActivity::class.java)
                startActivity(intent)
            } else {
                // User is not logged in, go to LoginActivity
                val intent = Intent(this, LoginActivity::class.java)
                startActivity(intent)
            }
        } catch (e: Exception) {
            // Fallback to LoginActivity in case of any error
            val intent = Intent(this, LoginActivity::class.java)
            startActivity(intent)
        }
        // Finish SplashActivity so the user cannot navigate back to it.
        finish()
    }
}
