package com.christopheraldoo.sosialmediaapp.model

/**
 * Data class untuk representasi User
 * Berisi informasi dasar pengguna sosial media
 */
data class User(
    val id: String,
    val username: String,
    val displayName: String,
    val bio: String,
    val profileImageUrl: String,
    val followers: Int = 0,
    val following: Int = 0,
    val isFollowing: Boolean = false
)
