package com.christopheraldoo.bukuringkasapp.data.model

import androidx.room.Entity
import androidx.room.PrimaryKey

// Model untuk data ringkasan dari buku
data class SummaryData(
    val id: String = "",
    val title: String = "",
    val subject: String = "",
    val grade: Int = 10,
    val mainConcept: String = "",
    val keyPoints: List<KeyPoint> = emptyList(),
    val formulas: List<Formula> = emptyList(),
    val example: String = "",
    val keywords: List<String> = emptyList(),
    val createdAt: Long = System.currentTimeMillis(),
    val userId: String = ""
)

// Model untuk poin-poin penting dalam ringkasan
data class KeyPoint(
    val title: String = "",
    val explanation: String = ""
)

// Model untuk rumus matematika/fisika/kimia
data class Formula(
    val name: String = "",
    val formula: String = "",
    val description: String = ""
)

// Model untuk pertanyaan dan jawaban
data class QuestionData(
    val id: String = "",
    val question: String = "",
    val answer: String = "",
    val explanation: String = "",
    val subject: String = "",
    val grade: Int = 10,
    val createdAt: Long = System.currentTimeMillis(),
    val userId: String = ""
)

// Model untuk history item di Room database
@Entity(tableName = "history")
data class HistoryItem(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val title: String = "",
    val type: String = "", // "summary" atau "question"
    val subject: String = "",
    val grade: Int = 10,
    val content: String = "", // JSON string dari SummaryData atau QuestionData
    val createdAt: Long = System.currentTimeMillis(),
    val userId: String = ""
)

// Model untuk user profile
data class UserProfile(
    val id: String = "",
    val name: String = "",
    val email: String = "",
    val photoUrl: String = "",
    val createdAt: Long = System.currentTimeMillis()
)

// Model untuk progress pembelajaran
data class LearningProgress(
    val subject: String = "",
    val totalSummaries: Int = 0,
    val totalQuestions: Int = 0,
    val lastActivity: Long = System.currentTimeMillis()
)

// Model untuk response dari Hugging Face API
data class HuggingFaceResponse(
    val generated_text: String = "",
    val answer: String? = null,
    val score: Double? = null
)

// Model untuk request ke Hugging Face API  
data class HuggingFaceRequest(
    val inputs: Any, // Bisa String atau Map untuk Q&A
    val parameters: Map<String, Any> = mapOf(),
    val options: Map<String, Any> = mapOf("wait_for_model" to true)
)

// Model untuk response dari Q&A API
// Moved to QAResponse.kt
