package com.christopheraldoo.sosialmediaapp.model

/**
 * Data class untuk Conversation
 * Merepresentasikan percakapan antara dua user
 */
data class Conversation(
    val id: String,
    val otherUser: User, // Lawan bicara
    val lastMessage: Message, // Pesan terakhir
    val timestamp: Long, // Waktu pesan terakhir
    val unreadCount: Int = 0, // Jumlah pesan yang belum dibaca
    val isGroup: Boolean = false // Untuk future implementation group chat
)
