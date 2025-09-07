package com.christopheraldoo.earningquizapp.activities

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R

/**
 * Activity to display the results of a completed quiz.
 *
 * This screen shows the user's final score and provides an option to return
 * to the main screen.
 */
class ResultActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_result)

        val tvScore = findViewById<TextView>(R.id.tv_score)
        val btnFinish = findViewById<Button>(R.id.btn_finish)

        // Get score from QuizActivity
        val score = intent.getIntExtra("USER_SCORE", 0)
        val totalQuestions = intent.getIntExtra("TOTAL_QUESTIONS", 0)

        // Display the score
        tvScore.text = "Your Score: $score/$totalQuestions"

        // Set listener for the finish button
        btnFinish.setOnClickListener {
            // Navigate back to MainActivity
            val intent = Intent(this, com.christopheraldoo.earningquizapp.MainActivity::class.java)
            intent.flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            startActivity(intent)
            finish()
        }
    }
}
