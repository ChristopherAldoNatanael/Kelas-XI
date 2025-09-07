package com.christopheraldoo.earningquizapp.models

/**
 * Data class representing a single quiz question.
 *
 * This model defines the structure of a question, including the question itself,
 * a list of possible answers (options), and the index of the correct option.
 *
 * @property questionText The text of the quiz question.
 * @property options A list of strings representing the choices for the answer.
 * @property correctAnswerIndex The 0-based index of the correct answer in the `options` list.
 * @property subject The subject category this question belongs to.
 */
data class Question(
    val questionText: String,
    val options: List<String>,
    val correctAnswerIndex: Int,
    val subject: String
)
