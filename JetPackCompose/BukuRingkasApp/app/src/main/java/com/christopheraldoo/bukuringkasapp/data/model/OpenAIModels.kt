package com.christopheraldoo.bukuringkasapp.data.model

/**
 * Model untuk OpenAI Chat API Request
 */
data class OpenAIChatRequest(
    val model: String,
    val messages: List<Message>,
    val max_tokens: Int = 150,
    val temperature: Double = 0.7,
    val top_p: Double = 1.0,
    val frequency_penalty: Double = 0.0,
    val presence_penalty: Double = 0.0
) {
    data class Message(
        val role: String,
        val content: String
    )
}

/**
 * Model untuk OpenAI Chat API Response
 */
data class OpenAIChatResponse(
    val id: String,
    val `object`: String,
    val created: Long,
    val model: String,
    val choices: List<OpenAIChatChoice>,
    val usage: OpenAIUsage
)

data class OpenAIChatChoice(
    val index: Int,
    val message: OpenAIChatMessage,
    val finish_reason: String
)

data class OpenAIChatMessage(
    val role: String,
    val content: String
)

data class OpenAIUsage(
    val prompt_tokens: Int,
    val completion_tokens: Int,
    val total_tokens: Int
)

/**
 * Legacy models (kept for backward compatibility)
 */
data class OpenAIRequest(
    val model: String,
    val prompt: String,
    val max_tokens: Int = 150,
    val temperature: Double = 0.7,
    val top_p: Double = 1.0,
    val frequency_penalty: Double = 0.0,
    val presence_penalty: Double = 0.0
)

data class OpenAIResponse(
    val id: String,
    val `object`: String,
    val created: Long,
    val model: String,
    val choices: List<OpenAIChoice>,
    val usage: OpenAIUsage
)

data class OpenAIChoice(
    val text: String,
    val index: Int,
    val logprobs: Any?,
    val finish_reason: String
)
