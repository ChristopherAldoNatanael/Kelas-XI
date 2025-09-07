package com.christopheraldoo.earningquizapp.activities

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.RadioButton
import android.widget.RadioGroup
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.Question

/**
 * Activity that hosts the quiz gameplay.
 *
 * This screen displays one question at a time, accepts the user's answer,
 * and navigates through the question list.
 */
class QuizActivity : AppCompatActivity() {

    private lateinit var questions: List<Question>
    private var currentQuestionIndex = 0
    private var score = 0

    private lateinit var tvQuestionNumber: TextView
    private lateinit var tvQuestionText: TextView
    private lateinit var rgOptions: RadioGroup
    private lateinit var rbOption1: RadioButton
    private lateinit var rbOption2: RadioButton
    private lateinit var rbOption3: RadioButton
    private lateinit var rbOption4: RadioButton
    private lateinit var btnSubmit: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_quiz)

        // Initialize views
        tvQuestionNumber = findViewById(R.id.tv_question_number)
        tvQuestionText = findViewById(R.id.tv_question_text)
        rgOptions = findViewById(R.id.rg_options)
        rbOption1 = findViewById(R.id.rb_option1)
        rbOption2 = findViewById(R.id.rb_option2)
        rbOption3 = findViewById(R.id.rb_option3)
        rbOption4 = findViewById(R.id.rb_option4)
        btnSubmit = findViewById(R.id.btn_submit_answer)

        // Load mock questions
        loadMockQuestions()

        // Display the first question
        displayQuestion()

        btnSubmit.setOnClickListener {
            handleSubmit()
        }
    }

    private fun loadMockQuestions() {
        // In a real app, you would fetch questions based on the selected subject
        questions = listOf(
            Question("What is 2 + 2?", listOf("3", "4", "5", "6"), 1, "Mathematics"),
            Question("What is the capital of Japan?", listOf("Beijing", "Seoul", "Tokyo", "Bangkok"), 2, "Geography"),
            Question("Who wrote Hamlet?", listOf("Charles Dickens", "William Shakespeare", "Leo Tolstoy", "Mark Twain"), 1, "History"),
            Question("What is the chemical symbol for water?", listOf("H2O", "CO2", "O2", "NaCl"), 0, "Science")
        )
    }

    private fun displayQuestion() {
        if (currentQuestionIndex < questions.size) {
            val question = questions[currentQuestionIndex]
            tvQuestionNumber.text = "Question ${currentQuestionIndex + 1}/${questions.size}"
            tvQuestionText.text = question.questionText
            rbOption1.text = question.options[0]
            rbOption2.text = question.options[1]
            rbOption3.text = question.options[2]
            rbOption4.text = question.options[3]
            rgOptions.clearCheck()
        } else {
            // End of quiz
            showResults()
        }
    }

    private fun handleSubmit() {
        val selectedOptionId = rgOptions.checkedRadioButtonId
        if (selectedOptionId == -1) {
            Toast.makeText(this, "Please select an answer", Toast.LENGTH_SHORT).show()
            return
        }

        val selectedRadioButton = findViewById<RadioButton>(selectedOptionId)
        val selectedAnswerIndex = rgOptions.indexOfChild(selectedRadioButton)

        if (selectedAnswerIndex == questions[currentQuestionIndex].correctAnswerIndex) {
            score++
        }

        currentQuestionIndex++
        displayQuestion()
    }

    private fun showResults() {
        val intent = Intent(this, ResultActivity::class.java)
        intent.putExtra("USER_SCORE", score)
        intent.putExtra("TOTAL_QUESTIONS", questions.size)
        startActivity(intent)
        finish()
    }
}
