package com.christopheraldoo.sosialmediaapp.model

/**
 * Data class untuk representasi Post/Tweet
 * Berisi semua informasi yang diperlukan untuk menampilkan post di timeline
 */
data class Post(
    val id: String,
    val user: User,
    val content: String,
    val timestamp: Long,
    val likeCount: Int = 0,
    val replyCount: Int = 0,
    val retweetCount: Int = 0,
    val isLiked: Boolean = false,
    val isRetweeted: Boolean = false
)
