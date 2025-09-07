package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Post
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Feed/Timeline utama
 * Mengelola data post dan state untuk FeedFragment
 */
class FeedViewModel : ViewModel() {
    
    private val _posts = MutableLiveData<List<Post>>()
    val posts: LiveData<List<Post>> = _posts
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _errorMessage = MutableLiveData<String?>()
    val errorMessage: LiveData<String?> = _errorMessage
    
    init {
        loadPosts()
    }
    
    /**
     * Memuat data post dari dummy data
     * Simulasi loading dengan delay
     */
    private fun loadPosts() {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            _posts.value = DummyData.posts
            _isLoading.value = false
        }, 1000)
    }
    
    /**
     * Refresh data post
     */
    fun refreshPosts() {
        loadPosts()
    }
    
    /**
     * Toggle like status pada post
     */
    fun toggleLike(postId: String) {
        val currentPosts = _posts.value?.toMutableList() ?: return
        val postIndex = currentPosts.indexOfFirst { it.id == postId }
        
        if (postIndex != -1) {
            val post = currentPosts[postIndex]
            val updatedPost = if (post.isLiked) {
                post.copy(isLiked = false, likeCount = post.likeCount - 1)
            } else {
                post.copy(isLiked = true, likeCount = post.likeCount + 1)
            }
            currentPosts[postIndex] = updatedPost
            _posts.value = currentPosts
        }
    }
    
    /**
     * Toggle retweet status pada post
     */
    fun toggleRetweet(postId: String) {
        val currentPosts = _posts.value?.toMutableList() ?: return
        val postIndex = currentPosts.indexOfFirst { it.id == postId }
        
        if (postIndex != -1) {
            val post = currentPosts[postIndex]
            val updatedPost = if (post.isRetweeted) {
                post.copy(isRetweeted = false, retweetCount = post.retweetCount - 1)
            } else {
                post.copy(isRetweeted = true, retweetCount = post.retweetCount + 1)
            }
            currentPosts[postIndex] = updatedPost
            _posts.value = currentPosts
        }
    }
}
