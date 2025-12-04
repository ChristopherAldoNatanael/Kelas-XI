package com.christopheraldoo.bukuringkasapp.data.model

/**
 * Model respons untuk pertanyaan dan jawaban
 */
data class QAResponse(
    val answer: String,
    val explanation: String,
    val confidence: Double
)
