package com.christopheraldoo.bukuringkasapp.viewmodel

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.bukuringkasapp.data.firebase.FirebaseService
import com.christopheraldoo.bukuringkasapp.data.model.QuestionData
import com.christopheraldoo.bukuringkasapp.data.repository.ApiConfig
import com.christopheraldoo.bukuringkasapp.data.repository.OpenAIRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * ViewModel untuk Ask AI Screen
 * Mengelola Q&A functionality dengan AI
 */
class AskAiViewModel : ViewModel() {

    private var repository: OpenAIRepository? = null
    private val firebaseService = FirebaseService()
    
    // Inisialisasi repository dengan context
    fun initRepository(context: Context) {
        if (repository == null) {
            repository = OpenAIRepository(context)
        }
    }

    // State untuk UI
    private val _currentQuestion = MutableStateFlow("")
    val currentQuestion: StateFlow<String> = _currentQuestion

    private val _currentContext = MutableStateFlow("")
    val currentContext: StateFlow<String> = _currentContext

    private val _selectedSubject = MutableStateFlow("Matematika")
    val selectedSubject: StateFlow<String> = _selectedSubject

    private val _isProcessing = MutableStateFlow(false)
    val isProcessing: StateFlow<Boolean> = _isProcessing

    private val _answerResult = MutableStateFlow<QuestionData?>(null)
    val answerResult: StateFlow<QuestionData?> = _answerResult

    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage

    // Quick questions untuk referensi
    val quickQuestions = listOf(
        "Apa itu fotosintesis?",
        "Jelaskan hukum Newton yang pertama",
        "Bagaimana cara menghitung luas lingkaran?",
        "Apa perbedaan antara mitosis dan meiosis?",
        "Jelaskan proses pembentukan awan",
        "Bagaimana cara kerja sistem pernapasan?",
        "Apa itu pecahan dalam matematika?",
        "Jelaskan konsep gaya dalam fisika"
    )

    /**
     * Update pertanyaan saat ini
     */
    fun updateQuestion(question: String) {
        _currentQuestion.value = question
    }

    /**
     * Update konteks tambahan
     */
    fun updateContext(context: String) {
        _currentContext.value = context
    }

    /**
     * Update mata pelajaran yang dipilih
     */
    fun updateSelectedSubject(subject: String) {
        _selectedSubject.value = subject
    }    /**
     * Process pertanyaan dengan AI
     */
    fun processQuestion(context: Context) {
        // Ensure repository is initialized
        initRepository(context)
        
        val question = _currentQuestion.value.trim()
        val questionContext = _currentContext.value.trim()

        if (question.isEmpty()) {
            _errorMessage.value = "Pertanyaan tidak boleh kosong"
            return
        }

        // Check if API key is configured
        if (!ApiConfig.isApiKeyConfigured(context)) {
            _errorMessage.value = "API Key OpenAI belum dikonfigurasi. Silakan masuk ke menu Settings untuk mengatur API key."
            return
        }

        _isProcessing.value = true
        _errorMessage.value = null

        viewModelScope.launch {
            try {
                val result = repository?.answerQuestion(question, questionContext)

                result?.fold(
                    onSuccess = { qaResponse ->
                        val answer = QuestionData(
                            question = question,
                            answer = qaResponse.answer,
                            explanation = qaResponse.explanation,
                            subject = _selectedSubject.value,
                            grade = detectGradeFromQuestion(question)
                        )
                        _answerResult.value = answer
                    },
                    onFailure = { error ->
                        // Fallback ke mock answer
                        val fallbackAnswer = QuestionData(
                            question = question,
                            answer = generateFallbackAnswer(question),
                            explanation = "API tidak tersedia: ${error.message}",
                            subject = _selectedSubject.value,
                            grade = detectGradeFromQuestion(question)
                        )
                        _answerResult.value = fallbackAnswer
                    }
                )
            } catch (e: Exception) {
                // Fallback ke mock answer
                val fallbackAnswer = QuestionData(
                    question = question,
                    answer = generateFallbackAnswer(question),
                    explanation = "Error: ${e.message}",
                    subject = _selectedSubject.value,
                    grade = detectGradeFromQuestion(question)
                )
                _answerResult.value = fallbackAnswer
            } finally {
                _isProcessing.value = false
            }
        }
    }

    /**
     * Save pertanyaan dan jawaban ke Firebase
     */
    fun saveQuestionAndAnswer() {
        _answerResult.value?.let { answer ->
            viewModelScope.launch {
                try {
                    val result = firebaseService.saveQuestion(answer)
                    result.fold(
                        onSuccess = {
                            // Success - bisa tambahkan feedback ke UI
                        },
                        onFailure = { error ->
                            _errorMessage.value = "Gagal menyimpan: ${error.message}"
                        }
                    )
                } catch (e: Exception) {
                    _errorMessage.value = "Error: ${e.message}"
                }
            }
        }
    }

    /**
     * Clear all states
     */
    fun clearAll() {
        _currentQuestion.value = ""
        _currentContext.value = ""
        _answerResult.value = null
        _errorMessage.value = null
        _isProcessing.value = false
    }

    /**
     * Clear error message
     */
    fun clearErrorMessage() {
        _errorMessage.value = null
    }

    /**
     * Set quick question
     */
    fun setQuickQuestion(question: String) {
        _currentQuestion.value = question
    }

    // Helper functions
    private fun detectGradeFromQuestion(question: String): Int {
        return when {
            question.contains("sd", ignoreCase = true) ||
            question.contains("kelas 1", ignoreCase = true) ||
            question.contains("kelas 2", ignoreCase = true) ||
            question.contains("kelas 3", ignoreCase = true) ||
            question.contains("kelas 4", ignoreCase = true) ||
            question.contains("kelas 5", ignoreCase = true) ||
            question.contains("kelas 6", ignoreCase = true) -> {
                6 // SD kelas 6 rata-rata
            }
            question.contains("smp", ignoreCase = true) ||
            question.contains("kelas 7", ignoreCase = true) ||
            question.contains("kelas 8", ignoreCase = true) ||
            question.contains("kelas 9", ignoreCase = true) -> {
                9 // SMP kelas 9 rata-rata
            }
            else -> 11 // Default SMA kelas 11
        }
    }

    private fun generateFallbackAnswer(question: String): String {
        return when {
            question.contains("fotosintesis", ignoreCase = true) -> {
                "Fotosintesis adalah proses pembuatan makanan oleh tumbuhan hijau dengan bantuan cahaya matahari. Proses ini mengubah karbon dioksida dan air menjadi glukosa dan oksigen melalui reaksi kimia yang kompleks."
            }
            question.contains("newton", ignoreCase = true) -> {
                "Hukum Newton yang pertama (hukum kelembaman) menyatakan bahwa suatu benda akan tetap dalam keadaan diam atau bergerak lurus beraturan jika resultan gaya yang bekerja padanya adalah nol."
            }
            question.contains("lingkaran", ignoreCase = true) -> {
                "Luas lingkaran dihitung dengan rumus L = π × r², dimana π adalah konstanta matematika (3,14) dan r adalah jari-jari lingkaran. Rumus ini didasarkan pada hubungan antara keliling dan diameter lingkaran."
            }
            question.contains("mitosis", ignoreCase = true) -> {
                "Mitosis adalah proses pembelahan sel yang menghasilkan dua sel anak yang identik dengan sel induk. Proses ini terjadi dalam empat tahap: profase, metafase, anafase, dan telofase."
            }
            question.contains("awan", ignoreCase = true) -> {
                "Awan terbentuk melalui proses kondensasi uap air di atmosfer. Ketika udara hangat dan lembab naik, uap air mendingin dan berubah menjadi titik-titik air atau es yang membentuk awan."
            }
            question.contains("pernapasan", ignoreCase = true) -> {
                "Sistem pernapasan manusia melibatkan hidung, tenggorokan, batang tenggorokan, bronkus, dan paru-paru. Proses inspirasi menghirup oksigen dan ekspirasi mengeluarkan karbon dioksida."
            }
            question.contains("pecahan", ignoreCase = true) -> {
                "Pecahan adalah cara menyatakan bagian dari keseluruhan dalam matematika. Pecahan terdiri dari pembilang (atas) dan penyebut (bawah), seperti 1/2 yang berarti satu bagian dari dua bagian yang sama."
            }
            question.contains("gaya", ignoreCase = true) -> {
                "Gaya adalah dorongan atau tarikan yang dapat mengubah keadaan gerak suatu benda. Gaya memiliki besar dan arah, serta dapat menyebabkan benda diam menjadi bergerak atau mengubah arah gerakannya."
            }
            else -> {
                "Pertanyaan yang bagus! Berdasarkan pengetahuan yang tersedia, saya akan menjelaskan konsep ini dengan cara yang mudah dipahami. Silakan ajukan pertanyaan lebih spesifik untuk penjelasan yang lebih detail."
            }
        }
    }
}
