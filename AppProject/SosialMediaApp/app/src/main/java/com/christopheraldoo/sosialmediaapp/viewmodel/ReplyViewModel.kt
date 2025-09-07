package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Post
import com.christopheraldoo.sosialmediaapp.model.Reply
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Reply/Thread
 * Mengelola data reply dan state untuk ReplyFragment
 */
class ReplyViewModel : ViewModel() {
    
    private val _post = MutableLiveData<Post?>()
    val post: LiveData<Post?> = _post
    
    private val _replies = MutableLiveData<List<Reply>>()
    val replies: LiveData<List<Reply>> = _replies
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _errorMessage = MutableLiveData<String?>()
    val errorMessage: LiveData<String?> = _errorMessage
    
    /**
     * Memuat post dan replies berdasarkan postId
     */
    fun loadPostAndReplies(postId: String) {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            // Cari post berdasarkan ID
            val foundPost = DummyData.posts.find { it.id == postId }
            _post.value = foundPost
            
            // Ambil replies untuk post ini
            val postReplies = DummyData.replies[postId] ?: emptyList()
            _replies.value = postReplies
            
            _isLoading.value = false
        }, 800)
    }
    
    /**
     * Toggle like status pada post utama
     */
    fun togglePostLike() {
        val currentPost = _post.value ?: return
        val updatedPost = if (currentPost.isLiked) {
            currentPost.copy(isLiked = false, likeCount = currentPost.likeCount - 1)
        } else {
            currentPost.copy(isLiked = true, likeCount = currentPost.likeCount + 1)
        }
        _post.value = updatedPost
    }
    
    /**
     * Toggle like status pada reply
     */
    fun toggleReplyLike(replyId: String) {
        val currentReplies = _replies.value?.toMutableList() ?: return
        val replyIndex = currentReplies.indexOfFirst { it.id == replyId }
        
        if (replyIndex != -1) {
            val reply = currentReplies[replyIndex]
            val updatedReply = if (reply.isLiked) {
                reply.copy(isLiked = false, likeCount = reply.likeCount - 1)
            } else {
                reply.copy(isLiked = true, likeCount = reply.likeCount + 1)
            }
            currentReplies[replyIndex] = updatedReply
            _replies.value = currentReplies
        }
    }
    
    /**
     * Menambah reply baru (simulasi)
     */
    fun addReply(content: String) {
        val currentPost = _post.value ?: return
        val currentReplies = _replies.value?.toMutableList() ?: mutableListOf()
        
        // Simulasi user yang sedang login (user pertama dari dummy data)
        val currentUser = DummyData.users[0]
        
        val newReply = Reply(
            id = "r${System.currentTimeMillis()}",
            postId = currentPost.id,
            user = currentUser,
            content = content,
            timestamp = System.currentTimeMillis(),
            likeCount = 0,
            isLiked = false
        )
        
        currentReplies.add(0, newReply) // Add at the beginning
        _replies.value = currentReplies
        
        // Update reply count di post utama
        val updatedPost = currentPost.copy(replyCount = currentPost.replyCount + 1)
        _post.value = updatedPost
    }
}
