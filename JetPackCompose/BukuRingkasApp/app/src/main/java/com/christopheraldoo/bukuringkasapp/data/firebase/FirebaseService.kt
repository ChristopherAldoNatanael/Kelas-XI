package com.christopheraldoo.bukuringkasapp.data.firebase

import android.util.Log
import com.christopheraldoo.bukuringkasapp.data.model.HistoryItem
import com.christopheraldoo.bukuringkasapp.data.model.QuestionData
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import com.christopheraldoo.bukuringkasapp.data.model.UserProfile
import com.christopheraldoo.bukuringkasapp.data.model.KeyPoint
import com.christopheraldoo.bukuringkasapp.data.model.Formula
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.FirebaseUser
import com.google.firebase.firestore.FirebaseFirestore
import com.google.firebase.firestore.Query
import kotlinx.coroutines.tasks.await

/**
 * Service class untuk menangani semua operasi Firebase
 * Termasuk Authentication, Firestore, dan ML Kit
 */
class FirebaseService {

    private val auth: FirebaseAuth = FirebaseAuth.getInstance()
    private val firestore: FirebaseFirestore = FirebaseFirestore.getInstance()

    // Authentication methods
    val currentUser: FirebaseUser?
        get() = auth.currentUser

    val isUserSignedIn: Boolean
        get() = currentUser != null

    suspend fun signInWithGoogle(idToken: String): Result<FirebaseUser> {
        return try {
            // Implementasi Google Sign-In akan ditambahkan di sini
            // Untuk sementara return error
            Result.failure(Exception("Google Sign-In belum diimplementasi"))
        } catch (e: Exception) {
            Log.e(TAG, "Error signing in with Google", e)
            Result.failure(e)
        }
    }

    fun signOut() {
        auth.signOut()
    }

    // Firestore methods untuk Summary
    suspend fun saveSummary(summary: SummaryData): Result<Unit> {
        return try {
            val userId = currentUser?.uid ?: return Result.failure(Exception("User not signed in"))

            val summaryMap = mapOf(
                "id" to summary.id.ifEmpty { firestore.collection("summaries").document().id },
                "title" to summary.title,
                "subject" to summary.subject,
                "grade" to summary.grade,
                "mainConcept" to summary.mainConcept,
                "keyPoints" to summary.keyPoints,
                "formulas" to summary.formulas,
                "example" to summary.example,
                "keywords" to summary.keywords,
                "createdAt" to summary.createdAt,
                "userId" to userId
            )

            firestore.collection("summaries")
                .document(summary.id.ifEmpty { summaryMap["id"] as String })
                .set(summaryMap)
                .await()

            Result.success(Unit)
        } catch (e: Exception) {
            Log.e(TAG, "Error saving summary", e)
            Result.failure(e)
        }
    }

    suspend fun getUserSummaries(): Result<List<SummaryData>> {
        return try {
            val userId = currentUser?.uid ?: return Result.failure(Exception("User not signed in"))

            val documents = firestore.collection("summaries")
                .whereEqualTo("userId", userId)
                .orderBy("createdAt", Query.Direction.DESCENDING)
                .get()
                .await()

            val summaries = documents.map { doc ->
                SummaryData(
                    id = doc.id,
                    title = doc.getString("title") ?: "",
                    subject = doc.getString("subject") ?: "",
                    grade = doc.getLong("grade")?.toInt() ?: 10,
                    mainConcept = doc.getString("mainConcept") ?: "",
                    keyPoints = doc.get("keyPoints") as? List<KeyPoint> ?: emptyList(),
                    formulas = doc.get("formulas") as? List<Formula> ?: emptyList(),
                    example = doc.getString("example") ?: "",
                    keywords = doc.get("keywords") as? List<String> ?: emptyList(),
                    createdAt = doc.getLong("createdAt") ?: System.currentTimeMillis(),
                    userId = doc.getString("userId") ?: ""
                )
            }

            Result.success(summaries)
        } catch (e: Exception) {
            Log.e(TAG, "Error getting user summaries", e)
            Result.failure(e)
        }
    }

    // Firestore methods untuk Questions
    suspend fun saveQuestion(question: QuestionData): Result<Unit> {
        return try {
            val userId = currentUser?.uid ?: return Result.failure(Exception("User not signed in"))

            val questionMap = mapOf(
                "id" to question.id.ifEmpty { firestore.collection("questions").document().id },
                "question" to question.question,
                "answer" to question.answer,
                "explanation" to question.explanation,
                "subject" to question.subject,
                "grade" to question.grade,
                "createdAt" to question.createdAt,
                "userId" to userId
            )

            firestore.collection("questions")
                .document(question.id.ifEmpty { questionMap["id"] as String })
                .set(questionMap)
                .await()

            Result.success(Unit)
        } catch (e: Exception) {
            Log.e(TAG, "Error saving question", e)
            Result.failure(e)
        }
    }

    suspend fun getUserQuestions(): Result<List<QuestionData>> {
        return try {
            val userId = currentUser?.uid ?: return Result.failure(Exception("User not signed in"))

            val documents = firestore.collection("questions")
                .whereEqualTo("userId", userId)
                .orderBy("createdAt", Query.Direction.DESCENDING)
                .get()
                .await()

            val questions = documents.map { doc ->
                QuestionData(
                    id = doc.id,
                    question = doc.getString("question") ?: "",
                    answer = doc.getString("answer") ?: "",
                    explanation = doc.getString("explanation") ?: "",
                    subject = doc.getString("subject") ?: "",
                    grade = doc.getLong("grade")?.toInt() ?: 10,
                    createdAt = doc.getLong("createdAt") ?: System.currentTimeMillis(),
                    userId = doc.getString("userId") ?: ""
                )
            }

            Result.success(questions)
        } catch (e: Exception) {
            Log.e(TAG, "Error getting user questions", e)
            Result.failure(e)
        }
    }

    // Room database methods untuk offline storage
    suspend fun saveHistoryItem(historyItem: HistoryItem): Result<Unit> {
        return try {
            // Implementasi akan menggunakan Room DAO
            // Untuk sementara return success
            Result.success(Unit)
        } catch (e: Exception) {
            Log.e(TAG, "Error saving history item", e)
            Result.failure(e)
        }
    }

    suspend fun getHistoryItems(): Result<List<HistoryItem>> {
        return try {
            // Implementasi akan menggunakan Room DAO
            // Untuk sementara return empty list
            Result.success(emptyList())
        } catch (e: Exception) {
            Log.e(TAG, "Error getting history items", e)
            Result.failure(e)
        }
    }

    // User profile methods
    suspend fun saveUserProfile(profile: UserProfile): Result<Unit> {
        return try {
            val userId = currentUser?.uid ?: return Result.failure(Exception("User not signed in"))

            firestore.collection("users")
                .document(userId)
                .set(profile)
                .await()

            Result.success(Unit)
        } catch (e: Exception) {
            Log.e(TAG, "Error saving user profile", e)
            Result.failure(e)
        }
    }

    suspend fun getUserProfile(): Result<UserProfile?> {
        return try {
            val userId = currentUser?.uid ?: return Result.failure(Exception("User not signed in"))

            val document = firestore.collection("users")
                .document(userId)
                .get()
                .await()

            if (document.exists()) {
                val profile = document.toObject(UserProfile::class.java)
                Result.success(profile)
            } else {
                Result.success(null)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error getting user profile", e)
            Result.failure(e)
        }
    }

    companion object {
        private const val TAG = "FirebaseService"
    }
}
