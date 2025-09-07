package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Conversation
import com.christopheraldoo.sosialmediaapp.model.Message
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Messages/Direct Messages
 */
class MessagesViewModel : ViewModel() {
    
    private val _conversations = MutableLiveData<List<Conversation>>()
    val conversations: LiveData<List<Conversation>> = _conversations
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _unreadCount = MutableLiveData<Int>()
    val unreadCount: LiveData<Int> = _unreadCount
    
    private var allConversations = listOf<Conversation>()
    
    /**
     * Load conversations
     */
    fun loadConversations() {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            allConversations = DummyData.conversations
            _conversations.value = allConversations.sortedByDescending { it.timestamp }
            updateUnreadCount()
            _isLoading.value = false
        }, 500)
    }
    
    /**
     * Search conversations
     */
    fun searchConversations(query: String) {
        val filtered = if (query.isEmpty()) {
            allConversations
        } else {
            allConversations.filter { conversation ->
                conversation.otherUser.displayName.contains(query, ignoreCase = true) ||
                conversation.otherUser.username.contains(query, ignoreCase = true) ||
                conversation.lastMessage.content.contains(query, ignoreCase = true)
            }
        }
        
        _conversations.value = filtered.sortedByDescending { it.timestamp }
    }
    
    /**
     * Mark conversation as read
     */
    fun markConversationAsRead(conversationId: String) {
        allConversations = allConversations.map { conversation ->
            if (conversation.id == conversationId) {
                conversation.copy(unreadCount = 0)
            } else {
                conversation
            }
        }
        
        _conversations.value = allConversations.sortedByDescending { it.timestamp }
        updateUnreadCount()
    }
    
    /**
     * Send new message (simulasi)
     */
    fun sendMessage(conversationId: String, content: String) {
        // Simulasi pengiriman pesan
        val currentUser = DummyData.users[0] // User yang sedang login
        
        // Update conversation dengan message baru
        allConversations = allConversations.map { conversation ->
            if (conversation.id == conversationId) {
                val newMessage = Message(
                    id = "m${System.currentTimeMillis()}",
                    conversationId = conversationId,
                    fromUser = currentUser,
                    toUser = conversation.otherUser,
                    content = content,
                    timestamp = System.currentTimeMillis(),
                    isRead = false
                )
                
                conversation.copy(
                    lastMessage = newMessage,
                    timestamp = newMessage.timestamp
                )
            } else {
                conversation
            }
        }
        
        _conversations.value = allConversations.sortedByDescending { it.timestamp }
    }
    
    private fun updateUnreadCount() {
        val totalUnread = allConversations.sumOf { it.unreadCount }
        _unreadCount.value = totalUnread
    }
}
