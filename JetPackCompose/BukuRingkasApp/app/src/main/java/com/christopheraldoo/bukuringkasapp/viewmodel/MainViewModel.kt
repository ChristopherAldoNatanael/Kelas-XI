package com.christopheraldoo.bukuringkasapp.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.bukuringkasapp.data.firebase.FirebaseService
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import com.christopheraldoo.bukuringkasapp.data.model.QuestionData
import com.christopheraldoo.bukuringkasapp.data.model.UserProfile
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * Main ViewModel untuk mengelola state aplikasi utama
 * Mengikuti pattern MVVM dengan StateFlow untuk reactive UI
 */
class MainViewModel : ViewModel() {

    private val firebaseService = FirebaseService()

    // State untuk user authentication
    private val _isUserSignedIn = MutableStateFlow(false)
    val isUserSignedIn: StateFlow<Boolean> = _isUserSignedIn

    private val _currentUser = MutableStateFlow<UserProfile?>(null)
    val currentUser: StateFlow<UserProfile?> = _currentUser

    // State untuk recent activities
    private val _recentSummaries = MutableStateFlow<List<SummaryData>>(emptyList())
    val recentSummaries: StateFlow<List<SummaryData>> = _recentSummaries

    private val _recentQuestions = MutableStateFlow<List<QuestionData>>(emptyList())
    val recentQuestions: StateFlow<List<QuestionData>> = _recentQuestions

    // State untuk loading dan error
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading

    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage

    init {
        checkUserAuthStatus()
        loadRecentActivities()
    }

    /**
     * Check status autentikasi user
     */
    private fun checkUserAuthStatus() {
        _isUserSignedIn.value = firebaseService.isUserSignedIn
        if (_isUserSignedIn.value) {
            loadUserProfile()
        }
    }

    /**
     * Load profil user dari Firebase
     */
    private fun loadUserProfile() {
        viewModelScope.launch {
            try {
                val result = firebaseService.getUserProfile()
                result.fold(
                    onSuccess = { profile ->
                        _currentUser.value = profile
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal memuat profil: ${error.message}"
                    }
                )
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            }
        }
    }

    /**
     * Load aktivitas terbaru dari Firebase
     */
    private fun loadRecentActivities() {
        if (!firebaseService.isUserSignedIn) return

        viewModelScope.launch {
            _isLoading.value = true
            try {
                // Load recent summaries
                val summariesResult = firebaseService.getUserSummaries()
                summariesResult.fold(
                    onSuccess = { summaries ->
                        _recentSummaries.value = summaries.take(5) // Latest 5
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal memuat ringkasan: ${error.message}"
                    }
                )

                // Load recent questions
                val questionsResult = firebaseService.getUserQuestions()
                questionsResult.fold(
                    onSuccess = { questions ->
                        _recentQuestions.value = questions.take(5) // Latest 5
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal memuat pertanyaan: ${error.message}"
                    }
                )
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    /**
     * Sign out user
     */
    fun signOut() {
        firebaseService.signOut()
        _isUserSignedIn.value = false
        _currentUser.value = null
        _recentSummaries.value = emptyList()
        _recentQuestions.value = emptyList()
    }

    /**
     * Refresh data setelah login
     */
    fun refreshDataAfterLogin() {
        checkUserAuthStatus()
        loadRecentActivities()
    }

    /**
     * Clear error message
     */
    fun clearErrorMessage() {
        _errorMessage.value = null
    }

    /**
     * Get total activities count
     */
    fun getTotalActivities(): Int {
        return _recentSummaries.value.size + _recentQuestions.value.size
    }

    /**
     * Get learning statistics
     */
    fun getLearningStats(): Map<String, Int> {
        val subjects = (_recentSummaries.value.map { it.subject } +
                       _recentQuestions.value.map { it.subject }).distinct()

        return mapOf(
            "total_summaries" to _recentSummaries.value.size,
            "total_questions" to _recentQuestions.value.size,
            "unique_subjects" to subjects.size,
            "total_activities" to getTotalActivities()
        )
    }
}
