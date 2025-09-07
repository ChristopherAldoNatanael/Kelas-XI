package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Message
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Chat individual
 */
class ChatViewModel : ViewModel() {
    
    private val _messages = MutableLiveData<List<Message>>()
    val messages: LiveData<List<Message>> = _messages
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _error = MutableLiveData<String?>()
    val error: LiveData<String?> = _error
    
    private var allMessages = mutableListOf<Message>()
    
    /**
     * Load messages for conversation
     */
    fun loadMessages(conversationId: String) {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            // Simulate loading messages for this conversation
            allMessages = generateDummyMessages(conversationId).toMutableList()
            _messages.value = allMessages.sortedBy { it.timestamp }
            _isLoading.value = false
        }, 500)
    }
    
    /**
     * Send new message
     */
    fun sendMessage(conversationId: String, content: String) {
        val currentUser = DummyData.users[0] // Current user
        val otherUser = DummyData.users.find { it.id != currentUser.id } ?: DummyData.users[1]
        
        val newMessage = Message(
            id = "msg${System.currentTimeMillis()}",
            conversationId = conversationId,
            fromUser = currentUser,
            toUser = otherUser,
            content = content,
            timestamp = System.currentTimeMillis(),
            isRead = false
        )
        
        allMessages.add(newMessage)
        _messages.value = allMessages.sortedBy { it.timestamp }
    }
    
    private fun generateDummyMessages(conversationId: String): List<Message> {
        val currentUser = DummyData.users[0]
        val otherUser = DummyData.users[1] // Jane Smith
        
        return listOf(
            Message(
                id = "msg_${conversationId}_1",
                conversationId = conversationId,
                fromUser = otherUser,
                toUser = currentUser,
                content = "Hey! How's the new project going?",
                timestamp = System.currentTimeMillis() - 3600000, // 1 hour ago
                isRead = true
            ),
            Message(
                id = "msg_${conversationId}_2",
                conversationId = conversationId,
                fromUser = currentUser,
                toUser = otherUser,
                content = "It's going great! Just finished the UI design.",
                timestamp = System.currentTimeMillis() - 3300000, // 55 minutes ago
                isRead = true
            ),
            Message(
                id = "msg_${conversationId}_3",
                conversationId = conversationId,
                fromUser = otherUser,
                toUser = currentUser,
                content = "That's awesome! Can't wait to see it.",
                timestamp = System.currentTimeMillis() - 3000000, // 50 minutes ago
                isRead = true
            ),
            Message(
                id = "msg_${conversationId}_4",
                conversationId = conversationId,
                fromUser = currentUser,
                toUser = otherUser,
                content = "I'll share the screenshots later today 📱",
                timestamp = System.currentTimeMillis() - 2700000, // 45 minutes ago
                isRead = true
            ),
            Message(
                id = "msg_${conversationId}_5",
                conversationId = conversationId,
                fromUser = otherUser,
                toUser = currentUser,
                content = "Perfect! Looking forward to it 👍",
                timestamp = System.currentTimeMillis() - 600000, // 10 minutes ago
                isRead = false
            )
        )
    }
}
