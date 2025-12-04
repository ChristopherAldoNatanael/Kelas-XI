package com.christopheraldoo.bukuringkasapp.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.bukuringkasapp.data.firebase.FirebaseService
import com.christopheraldoo.bukuringkasapp.data.model.HistoryItem
import com.christopheraldoo.bukuringkasapp.data.model.QuestionData
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * ViewModel untuk History Screen
 * Mengelola data history dari Firebase dan Room database
 */
class HistoryViewModel : ViewModel() {

    private val firebaseService = FirebaseService()

    // State untuk UI
    private val _historyItems = MutableStateFlow<List<HistoryItem>>(emptyList())
    val historyItems: StateFlow<List<HistoryItem>> = _historyItems

    private val _summaries = MutableStateFlow<List<SummaryData>>(emptyList())
    val summaries: StateFlow<List<SummaryData>> = _summaries

    private val _questions = MutableStateFlow<List<QuestionData>>(emptyList())
    val questions: StateFlow<List<QuestionData>> = _questions

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading

    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage

    private val _selectedItem = MutableStateFlow<HistoryItem?>(null)
    val selectedItem: StateFlow<HistoryItem?> = _selectedItem

    init {
        loadAllHistory()
    }

    /**
     * Load semua history dari Firebase
     */
    fun loadAllHistory() {
        if (!firebaseService.isUserSignedIn) {
            _errorMessage.value = "User belum login"
            return
        }

        _isLoading.value = true
        _errorMessage.value = null

        viewModelScope.launch {
            try {
                // Load summaries
                val summariesResult = firebaseService.getUserSummaries()
                summariesResult.fold(
                    onSuccess = { summaryList ->
                        _summaries.value = summaryList
                        // Convert ke HistoryItem format untuk kompatibilitas
                        val summaryHistoryItems = summaryList.map { summary ->
                            HistoryItem(
                                title = summary.title,
                                type = "summary",
                                subject = summary.subject,
                                grade = summary.grade,
                                content = "${summary.mainConcept.take(100)}...",
                                createdAt = summary.createdAt
                            )
                        }
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal memuat ringkasan: ${error.message}"
                    }
                )

                // Load questions
                val questionsResult = firebaseService.getUserQuestions()
                questionsResult.fold(
                    onSuccess = { questionList ->
                        _questions.value = questionList
                        // Convert ke HistoryItem format untuk kompatibilitas
                        val questionHistoryItems = questionList.map { question ->
                            HistoryItem(
                                title = question.question,
                                type = "question",
                                subject = question.subject,
                                grade = question.grade,
                                content = "${question.answer.take(100)}...",
                                createdAt = question.createdAt
                            )
                        }
                    },
                    onFailure = { error ->
                        _errorMessage.value = "Gagal memuat pertanyaan: ${error.message}"
                    }
                )

                // Combine all items
                val allItems = (_summaries.value.map { summary ->
                    HistoryItem(
                        title = summary.title,
                        type = "summary",
                        subject = summary.subject,
                        grade = summary.grade,
                        content = summary.mainConcept.take(100),
                        createdAt = summary.createdAt
                    )
                } + _questions.value.map { question ->
                    HistoryItem(
                        title = question.question,
                        type = "question",
                        subject = question.subject,
                        grade = question.grade,
                        content = question.answer.take(100),
                        createdAt = question.createdAt
                    )
                }).sortedByDescending { it.createdAt }

                _historyItems.value = allItems

            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    /**
     * Delete history item
     */
    fun deleteHistoryItem(item: HistoryItem) {
        viewModelScope.launch {
            try {
                // Delete dari Firebase berdasarkan tipe
                when (item.type) {
                    "summary" -> {
                        // Delete summary dari Firebase
                        val summaryToDelete = _summaries.value.find { it.title == item.title }
                        summaryToDelete?.let {
                            // Implementasi delete summary
                        }
                    }
                    "question" -> {
                        // Delete question dari Firebase
                        val questionToDelete = _questions.value.find { it.question == item.title }
                        questionToDelete?.let {
                            // Implementasi delete question
                        }
                    }
                }

                // Update local state
                _historyItems.value = _historyItems.value.filter { it != item }

            } catch (e: Exception) {
                _errorMessage.value = "Gagal menghapus: ${e.message}"
            }
        }
    }

    /**
     * Search history berdasarkan query
     */
    fun searchHistory(query: String) {
        if (query.isBlank()) {
            loadAllHistory()
            return
        }

        val filteredItems = _historyItems.value.filter { item ->
            item.title.contains(query, ignoreCase = true) ||
            item.subject.contains(query, ignoreCase = true) ||
            item.content.contains(query, ignoreCase = true)
        }

        _historyItems.value = filteredItems
    }

    /**
     * Filter berdasarkan mata pelajaran
     */
    fun filterBySubject(subject: String) {
        if (subject == "Semua") {
            loadAllHistory()
            return
        }

        val filteredItems = _historyItems.value.filter { item ->
            item.subject.equals(subject, ignoreCase = true)
        }

        _historyItems.value = filteredItems
    }

    /**
     * Filter berdasarkan tipe
     */
    fun filterByType(type: String) {
        if (type == "Semua") {
            loadAllHistory()
            return
        }

        val filteredItems = _historyItems.value.filter { item ->
            item.type == type.lowercase()
        }

        _historyItems.value = filteredItems
    }

    /**
     * Select item untuk detail view
     */
    fun selectItem(item: HistoryItem) {
        _selectedItem.value = item
    }

    /**
     * Clear selection
     */
    fun clearSelection() {
        _selectedItem.value = null
    }

    /**
     * Clear error message
     */
    fun clearErrorMessage() {
        _errorMessage.value = null
    }

    /**
     * Refresh data
     */
    fun refreshData() {
        loadAllHistory()
    }

    /**
     * Get statistics
     */
    fun getStatistics(): Map<String, Int> {
        val totalItems = _historyItems.value.size
        val totalSummaries = _summaries.value.size
        val totalQuestions = _questions.value.size
        val uniqueSubjects = _historyItems.value.map { it.subject }.distinct().size

        return mapOf(
            "total_items" to totalItems,
            "total_summaries" to totalSummaries,
            "total_questions" to totalQuestions,
            "unique_subjects" to uniqueSubjects
        )
    }

    /**
     * Get items berdasarkan tipe
     */
    fun getItemsByType(type: String): List<HistoryItem> {
        return _historyItems.value.filter { it.type == type }
    }

    /**
     * Get items berdasarkan mata pelajaran
     */
    fun getItemsBySubject(subject: String): List<HistoryItem> {
        return _historyItems.value.filter { it.subject.equals(subject, ignoreCase = true) }
    }

    /**
     * Get recent items (7 hari terakhir)
     */
    fun getRecentItems(): List<HistoryItem> {
        val sevenDaysAgo = System.currentTimeMillis() - (7 * 24 * 60 * 60 * 1000)
        return _historyItems.value.filter { it.createdAt > sevenDaysAgo }
    }
}
