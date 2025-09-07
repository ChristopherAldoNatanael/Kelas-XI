package com.christopheraldoo.sosialmediaapp.model

/**
 * Data class untuk Message
 * Merepresentasikan pesan individual dalam percakapan
 */
data class Message(
    val id: String,
    val conversationId: String,
    val fromUser: User,
    val toUser: User,
    val content: String,
    val timestamp: Long,
    val isRead: Boolean = false,
    val messageType: String = "text" // "text", "image", "media"
)
