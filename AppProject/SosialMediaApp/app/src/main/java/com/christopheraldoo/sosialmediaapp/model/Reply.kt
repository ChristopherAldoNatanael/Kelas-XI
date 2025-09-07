package com.christopheraldoo.sosialmediaapp.model

/**
 * Data class untuk representasi Reply/Komentar
 * Berisi informasi balasan terhadap post tertentu
 */
data class Reply(
    val id: String,
    val postId: String,
    val user: User,
    val content: String,
    val timestamp: Long,
    val likeCount: Int = 0,
    val isLiked: Boolean = false
)
