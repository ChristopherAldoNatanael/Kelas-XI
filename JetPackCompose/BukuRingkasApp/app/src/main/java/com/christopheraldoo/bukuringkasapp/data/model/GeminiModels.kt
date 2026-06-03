package com.christopheraldoo.bukuringkasapp.data.model

/**
 * Model untuk Gemini API Request
 */
data class GeminiRequest(
    val contents: List<GeminiContent>,
    val generationConfig: GenerationConfig? = null,
    val safetySettings: List<SafetySetting>? = null
)

data class GeminiContent(
    val parts: List<GeminiPart>,
    val role: String = "user"
)

data class GeminiPart(
    val text: String
)

data class GenerationConfig(
    val temperature: Double = 0.7,
    val topK: Int = 40,
    val topP: Double = 0.95,
    val maxOutputTokens: Int = 1024,
    val stopSequences: List<String>? = null
)

data class SafetySetting(
    val category: String,
    val threshold: String
)

/**
 * Model untuk Gemini API Response
 */
data class GeminiResponse(
    val candidates: List<GeminiCandidate>?,
    val promptFeedback: PromptFeedback?,
    val error: GeminiError?
)

data class GeminiCandidate(
    val content: GeminiContent?,
    val finishReason: String?,
    val index: Int?,
    val safetyRatings: List<SafetyRating>?
)

data class PromptFeedback(
    val safetyRatings: List<SafetyRating>?
)

data class SafetyRating(
    val category: String,
    val probability: String
)

data class GeminiError(
    val code: Int?,
    val message: String?,
    val status: String?
)
