package com.christopheraldoo.sosialmediaapp.model

/**
 * Data class untuk Notification
 * Merepresentasikan notifikasi (like, retweet, follow, reply, dll)
 */
data class Notification(
    val id: String,
    val type: String, // "like", "retweet", "reply", "follow", "mention"
    val fromUser: User,
    val relatedId: String? = null, // ID post/reply yang berkaitan
    val message: String,
    val timestamp: Long,
    val isRead: Boolean = false,
    val relatedPost: Post? = null // untuk konteks notifikasi
)
