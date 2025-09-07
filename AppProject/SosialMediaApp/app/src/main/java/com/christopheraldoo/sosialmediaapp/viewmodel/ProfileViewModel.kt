package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Post
import com.christopheraldoo.sosialmediaapp.model.User
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Profile pengguna
 * Mengelola data user dan post milik user
 */
class ProfileViewModel : ViewModel() {
    
    private val _user = MutableLiveData<User?>()
    val user: LiveData<User?> = _user
    
    private val _userPosts = MutableLiveData<List<Post>>()
    val userPosts: LiveData<List<Post>> = _userPosts
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _errorMessage = MutableLiveData<String?>()
    val errorMessage: LiveData<String?> = _errorMessage
    
    /**
     * Memuat data user dan post milik user berdasarkan userId
     */
    fun loadUserProfile(userId: String) {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            // Cari user berdasarkan ID
            val foundUser = DummyData.users.find { it.id == userId }
            _user.value = foundUser
            
            // Ambil post milik user ini
            val posts = DummyData.posts.filter { it.user.id == userId }
            _userPosts.value = posts
            
            _isLoading.value = false
        }, 800)
    }
    
    /**
     * Memuat current user profile (simulasi user yang sedang login)
     */
    fun loadCurrentUserProfile() {
        loadUserProfile(DummyData.users[0].id) // Simulasi user yang sedang login
    }
    
    /**
     * Toggle follow status untuk user
     */
    fun toggleFollowStatus() {
        val currentUser = _user.value ?: return
        val updatedUser = if (currentUser.isFollowing) {
            currentUser.copy(
                isFollowing = false,
                followers = currentUser.followers - 1
            )
        } else {
            currentUser.copy(
                isFollowing = true,
                followers = currentUser.followers + 1
            )
        }
        _user.value = updatedUser
    }
    
    /**
     * Toggle like status pada post di profile
     */
    fun togglePostLike(postId: String) {
        val currentPosts = _userPosts.value?.toMutableList() ?: return
        val postIndex = currentPosts.indexOfFirst { it.id == postId }
        
        if (postIndex != -1) {
            val post = currentPosts[postIndex]
            val updatedPost = if (post.isLiked) {
                post.copy(isLiked = false, likeCount = post.likeCount - 1)
            } else {
                post.copy(isLiked = true, likeCount = post.likeCount + 1)
            }
            currentPosts[postIndex] = updatedPost
            _userPosts.value = currentPosts
        }
    }
    
    /**
     * Refresh profile data
     */
    fun refreshProfile() {
        val currentUserId = _user.value?.id ?: return
        loadUserProfile(currentUserId)
    }
}
