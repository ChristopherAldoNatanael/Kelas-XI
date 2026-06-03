package com.christopheraldoo.bukuringkasapp.data.repository

import com.christopheraldoo.bukuringkasapp.data.model.*
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query
import java.util.concurrent.TimeUnit

/**
 * Interface untuk Gemini API
 * Menggunakan Gemini 1.5 Flash - Model stabil dan gratis dari Google
 */
interface GeminiApiService {
    @POST("v1beta/models/{model}:generateContent")
    suspend fun generateContent(
        @Path("model") model: String,
        @Query("key") apiKey: String,
        @Body request: GeminiRequest
    ): GeminiResponse

    // Fallback to v1 API (Older stable version)
    @POST("v1/models/gemini-pro:generateContent")
    suspend fun generateContentV1(
        @Query("key") apiKey: String,
        @Body request: GeminiRequest
    ): GeminiResponse
}

/**
 * Repository untuk mengakses Google Gemini API
 */
class GeminiRepository(private val context: android.content.Context) {
    private val apiService: GeminiApiService

    companion object {
        private const val BASE_URL = "https://generativelanguage.googleapis.com/"
    }

    init {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(logging)
            .connectTimeout(60, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .writeTimeout(60, TimeUnit.SECONDS)
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()

        apiService = retrofit.create(GeminiApiService::class.java)
    }

    /**
     * Check if API key is configured properly
     */
    fun isApiKeyConfigured(): Boolean {
        return GeminiConfig.isApiKeyConfigured(context)
    }
    
    /**
     * Convert error to user-friendly message
     */
    private fun getReadableErrorMessage(error: GeminiError?): String {
        return when {
            error == null -> "Terjadi kesalahan yang tidak diketahui"
            error.code == 429 -> "⏳ Server sedang sibuk, coba lagi dalam beberapa detik ya!"
            error.code == 404 -> "🔧 Layanan AI sedang dalam pemeliharaan"
            error.code == 401 || error.code == 403 -> "🔑 Terjadi masalah autentikasi, silakan coba lagi"
            error.code == 500 || error.code == 503 -> "🌐 Server sedang sibuk, coba lagi nanti"
            error.message?.contains("quota", ignoreCase = true) == true -> 
                "⏳ Batas penggunaan tercapai, tunggu sebentar lalu coba lagi"
            error.message?.contains("rate", ignoreCase = true) == true -> 
                "⏳ Terlalu banyak permintaan, tunggu beberapa detik"
            else -> "😕 ${error.message ?: "Gagal memproses permintaan"}"
        }
    }
    
    /**
     * Convert exception to user-friendly message
     */
    private fun getReadableExceptionMessage(e: Exception): String {
        val message = e.message ?: ""
        
        // Coba ambil detail error dari response body jika ada
        var errorDetail = ""
        if (e is retrofit2.HttpException) {
            try {
                val errorBody = e.response()?.errorBody()?.string()
                if (!errorBody.isNullOrBlank()) {
                    errorDetail = errorBody
                }
            } catch (ex: Exception) {
                // Ignore
            }
        }

        return when {
            message.contains("Unable to resolve host", ignoreCase = true) -> 
                "📡 Tidak ada koneksi internet. Pastikan Anda terhubung ke internet."
            message.contains("timeout", ignoreCase = true) -> 
                "⏱️ Koneksi timeout. Periksa koneksi internet Anda."
            message.contains("ConnectException", ignoreCase = true) -> 
                "🌐 Gagal terhubung ke server. Coba lagi nanti."
            message.contains("SocketException", ignoreCase = true) -> 
                "📶 Koneksi terputus. Periksa koneksi internet Anda."
            message.contains("SSL", ignoreCase = true) -> 
                "🔒 Masalah keamanan koneksi. Coba lagi nanti."
            message.contains("429", ignoreCase = true) || message.contains("quota", ignoreCase = true) -> 
                "⏳ Server sedang sibuk, coba lagi dalam beberapa detik"
            message.contains("404", ignoreCase = true) -> 
                "🔧 Layanan AI tidak merespons (404). Detail: ${errorDetail.take(100)}"
            message.contains("400", ignoreCase = true) -> 
                "🔑 Permintaan ditolak (400). Cek API Key. Detail: ${errorDetail.take(100)}"
            else -> "😕 Terjadi kesalahan: ${message.take(100)} $errorDetail"
        }
    }

    /**
     * Helper function to try multiple models if one fails
     */
    private suspend fun safeGenerateContent(apiKey: String, request: GeminiRequest): GeminiResponse {
        // Daftar model yang akan dicoba secara berurutan
        // Kita tambahkan variasi nama model untuk memastikan salah satunya berhasil
        val models = listOf(
            "gemini-1.5-flash",
            "gemini-1.5-flash-001",
            "gemini-1.5-flash-latest",
            "gemini-1.5-pro",
            "gemini-1.0-pro",
            "gemini-pro"
        )
        
        var lastException: Exception? = null
        
        // 1. Coba semua model di v1beta
        for (model in models) {
            try {
                return apiService.generateContent(model, apiKey, request)
            } catch (e: Exception) {
                lastException = e
                println("Model $model gagal: ${e.message}")
            }
        }
        
        // 2. Jika semua gagal, coba fallback ke v1 API (gemini-pro)
        try {
            return apiService.generateContentV1(apiKey, request)
        } catch (e: Exception) {
            lastException = e
            println("Model v1/gemini-pro gagal: ${e.message}")
        }
        
        // Jika semua model gagal, lempar exception terakhir
        throw lastException ?: Exception("Semua model AI gagal dihubungi")
    }

    /**
     * Merangkum teks menggunakan Gemini API
     * Dengan prompt yang dioptimalkan untuk ringkasan berkualitas tinggi
     */
    suspend fun summarizeText(text: String): Result<String> {
        return try {
            if (text.isBlank()) {
                return Result.failure(Exception("Teks tidak boleh kosong"))
            }

            if (text.length > 15000) {
                return Result.failure(Exception("Teks terlalu panjang. Maksimal 15000 karakter."))
            }

            val apiKey = GeminiConfig.getGeminiApiKey(context)
            val cleanText = text.trim().replace(Regex("\\s+"), " ")

            val prompt = """
                Kamu adalah asisten pembelajaran AI yang sangat pintar dan ahli dalam merangkum materi pelajaran untuk siswa Indonesia.
                
                MATERI YANG PERLU DIRANGKUM:
                $cleanText
                
                INSTRUKSI RINGKASAN:
                1. Buat ringkasan yang LENGKAP namun PADAT dalam Bahasa Indonesia yang baik dan benar
                2. Identifikasi dan jelaskan KONSEP UTAMA dari materi
                3. Sertakan POIN-POIN PENTING dalam format yang mudah dibaca
                4. Jika ada rumus/formula, sertakan dengan penjelasan singkat
                5. Berikan KATA KUNCI yang perlu diingat
                6. Gunakan bahasa yang mudah dipahami oleh pelajar SMA/SMK
                7. Format ringkasan yang rapi dan terstruktur
                
                FORMAT OUTPUT:
                📚 **RINGKASAN MATERI**
                
                **Konsep Utama:**
                [Jelaskan konsep utama]
                
                **Poin-Poin Penting:**
                • [Poin 1]
                • [Poin 2]
                • [Poin 3]
                
                **Kata Kunci:** [kata1, kata2, kata3]
            """.trimIndent()

            val request = GeminiRequest(
                contents = listOf(
                    GeminiContent(
                        parts = listOf(GeminiPart(text = prompt))
                    )
                ),
                generationConfig = GenerationConfig(
                    temperature = 0.7,
                    maxOutputTokens = 2048,
                    topK = 40,
                    topP = 0.95
                )
            )
            
            try {
                val response = safeGenerateContent(apiKey, request)

                if (response.error != null) {
                    return Result.failure(Exception(getReadableErrorMessage(response.error)))
                }

                val generatedText = response.candidates?.firstOrNull()?.content?.parts?.firstOrNull()?.text

                if (generatedText.isNullOrBlank()) {
                    return Result.failure(Exception("🤔 AI tidak dapat menghasilkan ringkasan. Coba lagi ya!"))
                }
                
                Result.success(generatedText.trim())
            } catch (e: Exception) {
                e.printStackTrace()
                Result.failure(Exception(getReadableExceptionMessage(e)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(getReadableExceptionMessage(e)))
        }
    }
    
    /**
     * Merangkum dengan opsi enhancement
     * Berbagai mode ringkasan untuk kebutuhan berbeda
     */
    suspend fun summarizeWithEnhancement(text: String, enhancementType: String): Result<String> {
        val enhancementPrompt = when (enhancementType) {
            "ringkas" -> """
                Buat ringkasan yang SANGAT SINGKAT dan PADAT.
                - Hanya poin-poin inti saja (maksimal 5 poin)
                - Setiap poin maksimal 1-2 kalimat
                - Total tidak lebih dari 150 kata
            """.trimIndent()
            "detail" -> """
                Buat ringkasan yang DETAIL dan KOMPREHENSIF.
                - Jelaskan setiap konsep dengan mendalam
                - Sertakan definisi, penjelasan, dan hubungan antar konsep
                - Berikan konteks dan latar belakang jika relevan
                - Sertakan rumus/formula jika ada dengan penjelasan
            """.trimIndent()
            "contoh" -> """
                Buat ringkasan dengan BANYAK CONTOH KONKRET.
                - Setiap konsep disertai minimal 1-2 contoh nyata
                - Contoh yang relevan dengan kehidupan sehari-hari pelajar
                - Sertakan aplikasi praktis dari setiap konsep
                - Berikan analogi yang mudah dipahami
            """.trimIndent()
            "poin" -> """
                Buat ringkasan dalam FORMAT POIN-POIN yang rapi.
                - Gunakan bullet points (•) untuk setiap poin
                - Kelompokkan poin berdasarkan subtopik
                - Setiap poin singkat dan jelas
                - Mudah untuk dipelajari dan dihafalkan
            """.trimIndent()
            else -> "Buat ringkasan yang jelas, lengkap, dan mudah dipahami."
        }

        val prompt = """
            Kamu adalah asisten pembelajaran AI yang sangat pintar untuk pelajar Indonesia.
            
            INSTRUKSI KHUSUS:
            $enhancementPrompt
            
            MATERI YANG PERLU DIRANGKUM:
            $text
            
            Berikan ringkasan dalam Bahasa Indonesia yang mudah dipahami oleh pelajar SMA/SMK.
            Pastikan ringkasan berkualitas tinggi dan membantu untuk belajar.
        """.trimIndent()

        return try {
            val apiKey = GeminiConfig.getGeminiApiKey(context)

            val request = GeminiRequest(
                contents = listOf(
                    GeminiContent(
                        parts = listOf(GeminiPart(text = prompt))
                    )
                ),
                generationConfig = GenerationConfig(
                    temperature = 0.7,
                    maxOutputTokens = 2048,
                    topK = 40,
                    topP = 0.95
                )
            )
            
            val response = safeGenerateContent(apiKey, request)

            if (response.error != null) {
                return Result.failure(Exception(getReadableErrorMessage(response.error)))
            }

            val generatedText = response.candidates?.firstOrNull()?.content?.parts?.firstOrNull()?.text

            if (generatedText.isNullOrBlank()) {
                return Result.failure(Exception("🤔 AI tidak dapat menghasilkan ringkasan. Coba lagi ya!"))
            }

            Result.success(generatedText.trim())
        } catch (e: Exception) {
            Result.failure(Exception(getReadableExceptionMessage(e)))
        }
    }
    
    /**
     * Menjawab pertanyaan menggunakan Gemini API
     * Dengan prompt yang dioptimalkan untuk jawaban pintar dan komprehensif
     */
    suspend fun answerQuestion(question: String, contextText: String = ""): Result<QAResponse> {
        return try {
            if (question.isBlank()) {
                return Result.failure(Exception("Pertanyaan tidak boleh kosong"))
            }

            val apiKey = GeminiConfig.getGeminiApiKey(context)

            val prompt = if (contextText.isNotBlank()) {
                """
                    Kamu adalah asisten AI yang sangat pintar dan membantu untuk pelajar Indonesia.
                    
                    KONTEKS MATERI:
                    $contextText
                    
                    PERTANYAAN: $question
                    
                    INSTRUKSI:
                    1. Jawab dengan lengkap, jelas, dan mudah dipahami dalam Bahasa Indonesia
                    2. Jika pertanyaan terkait pelajaran, berikan penjelasan konsep yang mendasar
                    3. Berikan contoh konkret jika memungkinkan
                    4. Jika ada rumus atau formula, jelaskan dengan detail
                    5. Format jawaban yang rapi dan terstruktur
                """.trimIndent()
            } else {
                """
                    Kamu adalah asisten AI yang sangat pintar, berpengetahuan luas, dan membantu untuk pelajar Indonesia.
                    
                    PERTANYAAN: $question
                    
                    INSTRUKSI:
                    1. Jawab dengan lengkap, akurat, dan mudah dipahami dalam Bahasa Indonesia
                    2. Berikan penjelasan yang komprehensif namun tetap ringkas
                    3. Jika pertanyaan tentang sejarah, sertakan tanggal dan fakta penting
                    4. Jika pertanyaan tentang sains, jelaskan konsepnya dengan jelas
                    5. Jika pertanyaan tentang matematika, berikan langkah-langkah penyelesaian
                    6. Berikan contoh konkret untuk memperjelas jawaban
                    7. Format jawaban yang rapi dan mudah dibaca
                """.trimIndent()
            }

            val request = GeminiRequest(
                contents = listOf(
                    GeminiContent(
                        parts = listOf(GeminiPart(text = prompt))
                    )
                ),
                generationConfig = GenerationConfig(
                    temperature = 0.7,
                    maxOutputTokens = 2048,
                    topK = 40,
                    topP = 0.95
                )
            )
            
            val response = safeGenerateContent(apiKey, request)

            if (response.error != null) {
                return Result.failure(Exception(getReadableErrorMessage(response.error)))
            }

            val answer = response.candidates?.firstOrNull()?.content?.parts?.firstOrNull()?.text

            if (answer.isNullOrBlank()) {
                return Result.failure(Exception("🤔 AI tidak dapat menjawab pertanyaan ini. Coba tanya dengan cara lain ya!"))
            }

            Result.success(
                QAResponse(
                    answer = answer.trim(),
                    explanation = "Dijawab oleh AI Gemini 1.5 Flash",
                    confidence = 0.95
                )
            )
        } catch (e: Exception) {
            Result.failure(Exception(getReadableExceptionMessage(e)))
        }
    }
}
