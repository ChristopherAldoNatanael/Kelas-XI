package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Post
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Search/Explore functionality
 */
class SearchViewModel : ViewModel() {
    
    private val _searchResults = MutableLiveData<List<Post>>()
    val searchResults: LiveData<List<Post>> = _searchResults
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _trendingTopics = MutableLiveData<List<String>>()
    val trendingTopics: LiveData<List<String>> = _trendingTopics
    
    private val _errorMessage = MutableLiveData<String?>()
    val errorMessage: LiveData<String?> = _errorMessage
    
    private var allPosts = listOf<Post>()
    
    init {
        allPosts = DummyData.posts
    }
    
    /**
     * Cari posts berdasarkan query
     */
    fun searchPosts(query: String) {
        if (query.isEmpty()) {
            _searchResults.value = emptyList()
            return
        }
        
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            val filteredPosts = allPosts.filter { post ->
                post.content.contains(query, ignoreCase = true) ||
                post.user.displayName.contains(query, ignoreCase = true) ||
                post.user.username.contains(query, ignoreCase = true)
            }
            
            _searchResults.value = filteredPosts
            _isLoading.value = false
        }, 500)
    }
    
    /**
     * Clear search results
     */
    fun clearSearch() {
        _searchResults.value = emptyList()
    }
    
    /**
     * Load trending topics
     */
    fun loadTrendingData() {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            val trending = listOf(
                "#Android",
                "#Kotlin", 
                "#Development",
                "#Technology",
                "#Programming",
                "#Mobile",
                "#OpenSource",
                "#Coding",
                "#SoftwareEngineering",
                "#TechNews"
            )
            
            _trendingTopics.value = trending.shuffled().take(5)
            _isLoading.value = false
        }, 300)
    }
    
    /**
     * Toggle like pada post dari search results
     */
    fun togglePostLike(postId: String) {
        val currentResults = _searchResults.value?.toMutableList() ?: return
        val postIndex = currentResults.indexOfFirst { it.id == postId }
        
        if (postIndex != -1) {
            val post = currentResults[postIndex]
            val updatedPost = if (post.isLiked) {
                post.copy(isLiked = false, likeCount = post.likeCount - 1)
            } else {
                post.copy(isLiked = true, likeCount = post.likeCount + 1)
            }
            currentResults[postIndex] = updatedPost
            _searchResults.value = currentResults
            
            // Also update in DummyData
            val globalIndex = DummyData.posts.indexOfFirst { it.id == postId }
            if (globalIndex != -1) {
                DummyData.posts = DummyData.posts.toMutableList().apply {
                    set(globalIndex, updatedPost)
                }
            }
        }
    }
}
