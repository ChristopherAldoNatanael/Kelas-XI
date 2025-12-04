package com.christopheraldoo.bukuringkasapp.data.repository

import com.christopheraldoo.bukuringkasapp.data.model.OpenAIChatRequest
import com.christopheraldoo.bukuringkasapp.data.model.OpenAIChatResponse
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.Header
import retrofit2.http.POST
import java.util.concurrent.TimeUnit
import com.christopheraldoo.bukuringkasapp.data.model.QAResponse

/**
 * Interface untuk OpenAI API
 */
interface OpenAIApiService {
    @POST("chat/completions")
    suspend fun getChatCompletion(
        @Header("Authorization") token: String,
        @Body request: OpenAIChatRequest
    ): OpenAIChatResponse
}

/**
 * Repository untuk mengakses OpenAI API
 * Mode online-only, tanpa fallback offline
 */
class OpenAIRepository(private val context: android.content.Context) {
    private val apiService: OpenAIApiService

    /**
     * Check if API key is configured properly
     */
    fun isApiKeyConfigured(): Boolean {
        return ApiConfig.isApiKeyConfigured(context)
    }

    init {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl(ApiConfig.OPENAI_API_BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()

        apiService = retrofit.create(OpenAIApiService::class.java)
    }

    /**
     * Merangkum teks menggunakan OpenAI API - ONLINE ONLY
     */
    suspend fun summarizeText(text: String): Result<String> {
        return try {
            // Validasi input
            if (text.isBlank()) {
                return Result.failure(Exception("Teks tidak boleh kosong"))
            }

            if (text.length > 5000) {
                return Result.failure(Exception("Teks terlalu panjang. Maksimal 5000 karakter."))
            }

            // Check if API key is configured
            if (!ApiConfig.isApiKeyConfigured(context)) {
                return Result.failure(Exception("API key belum dikonfigurasi. Silakan masukkan API key OpenAI Anda di halaman Pengaturan."))
            }

            val apiKey = ApiConfig.getOpenAIApiKey(context)
            val token = "Bearer $apiKey"

            // Bersihkan teks input
            val cleanText = text.trim().replace(Regex("\\s+"), " ")

            val messages = listOf(
                OpenAIChatRequest.Message(
                    role = "user",
                    content = "Rangkum teks berikut dengan singkat dan padat dalam Bahasa Indonesia:\n\n$cleanText"
                )
            )

            val request = OpenAIChatRequest(
                model = "gpt-3.5-turbo",
                messages = messages,
                max_tokens = 200,
                temperature = 0.5
            )

            try {
                println("DEBUG: Making API call with token: ${token.take(20)}...")
                val response = apiService.getChatCompletion(token, request)
                println("DEBUG: API Response received: ${response.choices.size} choices")

                if (response.choices.isNotEmpty()) {
                    val summary = response.choices[0].message.content.trim()
                    println("DEBUG: Summary generated: ${summary.take(50)}...")
                    return Result.success(summary)
                } else {
                    return Result.failure(Exception("API tidak mengembalikan hasil. Silakan coba lagi."))
                }
            } catch (e: Exception) {
                println("DEBUG: API Error: ${e.message}")
                e.printStackTrace()
                return Result.failure(Exception("Error koneksi: ${e.message}. Pastikan internet tersambung dan API key valid."))
            }
        } catch (e: Exception) {
            return Result.failure(Exception("APLIKASI MEMERLUKAN KONEKSI INTERNET! Error: ${e.message}"))
        }
    }

    /**
     * Menjawab pertanyaan menggunakan OpenAI API - ONLINE ONLY
     */
    suspend fun answerQuestion(question: String, contextText: String = ""): Result<QAResponse> {
        return try {
            // Validasi input
            if (question.isBlank()) {
                return Result.failure(Exception("Pertanyaan tidak boleh kosong"))
            }

            // Check if API key is configured
            if (!ApiConfig.isApiKeyConfigured(context)) {
                return Result.failure(Exception("API key belum dikonfigurasi. Silakan masukkan API key OpenAI Anda di halaman Pengaturan."))
            }

            val apiKey = ApiConfig.getOpenAIApiKey(context)
            val token = "Bearer $apiKey"

            val messages = if (contextText.isNotBlank()) {
                listOf(
                    OpenAIChatRequest.Message(
                        role = "user",
                        content = "Jawab pertanyaan ini dalam Bahasa Indonesia berdasarkan konteks berikut.\n\nKonteks: $contextText\n\nPertanyaan: $question"
                    )
                )
            } else {
                listOf(
                    OpenAIChatRequest.Message(
                        role = "user",
                        content = "Jawab pertanyaan berikut dengan singkat dan padat dalam Bahasa Indonesia.\n\nPertanyaan: $question"
                    )
                )
            }

            val request = OpenAIChatRequest(
                model = "gpt-3.5-turbo",
                messages = messages,
                max_tokens = 50,
                temperature = 0.1
            )

            try {
                println("DEBUG Q&A: Making API call with token: ${token.take(20)}...")
                println("DEBUG Q&A: Question: $question")
                val response = apiService.getChatCompletion(token, request)
                println("DEBUG Q&A: API Response received: ${response.choices.size} choices")

                if (response.choices.isNotEmpty()) {
                    val answer = response.choices[0].message.content.trim()
                    println("DEBUG Q&A: Answer generated: ${answer.take(50)}...")
                    return Result.success(QAResponse(
                        answer = answer,
                        explanation = "Jawaban dari AI OpenAI",
                        confidence = 0.9
                    ))
                } else {
                    return Result.failure(Exception("API tidak mengembalikan jawaban. Silakan coba lagi."))
                }
            } catch (e: Exception) {
                println("DEBUG Q&A: API Error: ${e.message}")
                e.printStackTrace()
                return Result.failure(Exception("Error koneksi: ${e.message}. Pastikan internet tersambung dan API key valid."))
            }
        } catch (e: Exception) {
            return Result.failure(Exception("APLIKASI MEMERLUKAN KONEKSI INTERNET! Error: ${e.message}"))
        }
    }

    /**
     * Membuat penjelasan tambahan untuk konsep - ONLINE ONLY
     */
    suspend fun generateExplanation(concept: String, subject: String): Result<String> {
        return try {
            // Validasi input
            if (concept.isBlank()) {
                return Result.failure(Exception("Konsep tidak boleh kosong"))
            }

            // Check if API key is configured
            if (!ApiConfig.isApiKeyConfigured(context)) {
                return Result.failure(Exception("API key belum dikonfigurasi. Silakan masukkan API key OpenAI Anda di halaman Pengaturan."))
            }

            val apiKey = ApiConfig.getOpenAIApiKey(context)
            val token = "Bearer $apiKey"

            val messages = listOf(
                OpenAIChatRequest.Message(
                    role = "user",
                    content = "Jelaskan konsep '$concept' dalam mata pelajaran $subject secara detail namun mudah dimengerti dalam Bahasa Indonesia. Berikan penjelasan yang tepat untuk siswa sekolah menengah."
                )
            )

            val request = OpenAIChatRequest(
                model = "gpt-3.5-turbo",
                messages = messages,
                max_tokens = 250,
                temperature = 0.7
            )

            try {
                val response = apiService.getChatCompletion(token, request)

                if (response.choices.isNotEmpty()) {
                    val explanation = response.choices[0].message.content.trim()
                    return Result.success(explanation)
                } else {
                    return Result.failure(Exception("API tidak mengembalikan penjelasan yang valid. Pastikan koneksi internet stabil."))
                }
            } catch (e: Exception) {
                return Result.failure(Exception("APLIKASI MEMERLUKAN KONEKSI INTERNET! Error Penjelasan: ${e.message}. Pastikan WiFi tersambung dan token API valid."))
            }
        } catch (e: Exception) {
            return Result.failure(Exception("APLIKASI MEMERLUKAN KONEKSI INTERNET! Error: ${e.message}"))
        }
    }
}
