package com.christopheraldoo.bukuringkasapp.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.navigation.NavController
import com.christopheraldoo.bukuringkasapp.data.model.QuestionData
import com.christopheraldoo.bukuringkasapp.data.repository.OpenAIRepository
import com.christopheraldoo.bukuringkasapp.ui.theme.PrimaryBlue
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * Ask AI Screen - Untuk mengajukan pertanyaan tentang pelajaran
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AskAiScreen(navController: NavController) {
    val context = LocalContext.current
    var question by remember { mutableStateOf("") }
    var contextText by remember { mutableStateOf("") }
    var isLoading by remember { mutableStateOf(false) }
    var showResultDialog by remember { mutableStateOf(false) }
    var currentAnswer by remember { mutableStateOf<QuestionData?>(null) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Header
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(PrimaryBlue)
                .padding(24.dp)
        ) {
            Column {
                Text(
                    text = "🤖 Tanya AI",
                    style = MaterialTheme.typography.headlineSmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Ajukan pertanyaan tentang mata pelajaran",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.9f)
                )
            }
        }

        // Main Content
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Question Input Section
            item {
                QuestionInputSection(
                    question = question,
                    onQuestionChange = { question = it },
                    context = contextText,
                    onContextChange = { contextText = it }
                )
            }

            // Ask Button
            if (question.isNotEmpty()) {
                item {
                    AskButtonSection(
                        isLoading = isLoading,
                        onAsk = {
                            isLoading = true
                            processQuestion(question, contextText, context) { answer ->
                                currentAnswer = answer
                                showResultDialog = true
                                isLoading = false
                            }
                        }
                    )
                }
            }

            // Tips Section
            item {
                AskAiTipsCard()
            }
        }
    }

    // Result Dialog
    if (showResultDialog && currentAnswer != null) {
        AnswerResultDialog(
            answer = currentAnswer!!,
            onDismiss = { showResultDialog = false },
            onSave = { showResultDialog = false }
        )
    }
}

/**
 * Section untuk input pertanyaan dan konteks
 */
@Composable
fun QuestionInputSection(
    question: String,
    onQuestionChange: (String) -> Unit,
    context: String,
    onContextChange: (String) -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "Pertanyaan Anda",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(12.dp))

            OutlinedTextField(
                value = question,
                onValueChange = onQuestionChange,
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("Ketik pertanyaan Anda di sini...") },
                minLines = 3,
                maxLines = 5
            )

            Spacer(modifier = Modifier.height(16.dp))

            Text(
                text = "Konteks Tambahan (Opsional)",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(8.dp))

            OutlinedTextField(
                value = context,
                onValueChange = onContextChange,
                modifier = Modifier.fillMaxWidth(),
                placeholder = { Text("Berikan konteks atau informasi tambahan...") },
                minLines = 2,
                maxLines = 3
            )
        }
    }
}

/**
 * Section untuk tombol tanya
 */
@Composable
fun AskButtonSection(
    isLoading: Boolean,
    onAsk: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Button(
                onClick = onAsk,
                modifier = Modifier.fillMaxWidth(),
                enabled = !isLoading,
                colors = ButtonDefaults.buttonColors(
                    containerColor = PrimaryBlue
                )
            ) {
                if (isLoading) {
                    CircularProgressIndicator(
                        color = Color.White,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.size(8.dp))
                    Text("Memproses...")
                } else {
                    Icon(
                        painter = painterResource(id = android.R.drawable.ic_menu_send),
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.size(8.dp))
                    Text("Tanya AI")
                }
            }
        }
    }
}

/**
 * Dialog untuk menampilkan jawaban
 */
@Composable
fun AnswerResultDialog(
    answer: QuestionData,
    onDismiss: () -> Unit,
    onSave: () -> Unit
) {
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surface
            )
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                Text(
                    text = "💡 Jawaban AI",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )

                Spacer(modifier = Modifier.height(16.dp))

                // Question
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.3f)
                    )
                ) {
                    Text(
                        text = answer.question,
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(12.dp)
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Answer
                Text(
                    text = "Jawaban:",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )

                Text(
                    text = answer.answer,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(vertical = 8.dp)
                )

                // Explanation if available
                if (answer.explanation.isNotEmpty()) {
                    Spacer(modifier = Modifier.height(12.dp))
                    Text(
                        text = "Penjelasan:",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Bold
                    )

                    Text(
                        text = answer.explanation,
                        style = MaterialTheme.typography.bodySmall,
                        modifier = Modifier.padding(vertical = 8.dp),
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.8f)
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End
                ) {
                    OutlinedButton(onClick = onDismiss) {
                        Text("Tutup")
                    }
                    Spacer(modifier = Modifier.size(8.dp))
                    Button(
                        onClick = onSave,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = PrimaryBlue
                        )
                    ) {
                        Text("Simpan")
                    }
                }
            }
        }
    }
}

/**
 * Card untuk tips penggunaan AI
 */
@Composable
fun AskAiTipsCard() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.1f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "💡 Tips Bertanya yang Efektif",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(12.dp))

            listOf(
                "🔍 Ajukan pertanyaan yang spesifik dan jelas",
                "📚 Berikan konteks jika diperlukan",
                "🎯 Tulis pertanyaan dengan bahasa yang baik",
                "🔄 Jika jawaban kurang jelas, ajukan follow-up question"
            ).forEach { tip ->
                Text(
                    text = tip,
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.padding(vertical = 2.dp)
                )
            }
        }
    }
}

// Helper function untuk memproses pertanyaan
private fun processQuestion(question: String, contextText: String, appContext: android.content.Context, onResult: (QuestionData) -> Unit) {
    val repository = OpenAIRepository(appContext)

    // Check if API key is configured
    if (!repository.isApiKeyConfigured()) {
        onResult(QuestionData(
            question = question,
            answer = "API Key belum dikonfigurasi. Silakan atur API Key di halaman Pengaturan.",
            explanation = "Masuk ke menu Pengaturan untuk mengatur API Key OpenAI Anda.",
            createdAt = System.currentTimeMillis()
        ))
        return
    }

    CoroutineScope(Dispatchers.Main).launch {
        try {
            val response = repository.answerQuestion(question, contextText)

            response.fold(
                onSuccess = { qaResponse ->
                    val answer = QuestionData(
                        question = question,
                        answer = qaResponse.answer,
                        explanation = qaResponse.explanation,
                        subject = "Umum",
                        createdAt = System.currentTimeMillis()
                    )
                    onResult(answer)
                },
                onFailure = { error ->
                    val fallbackAnswer = QuestionData(
                        question = question,
                        answer = "Maaf, terjadi kesalahan saat memproses pertanyaan Anda.",
                        explanation = "Error: ${error.message}",
                        subject = "Umum",
                        createdAt = System.currentTimeMillis()
                    )
                    onResult(fallbackAnswer)
                }
            )
        } catch (e: Exception) {
            val fallbackAnswer = QuestionData(
                question = question,
                answer = "Maaf, terjadi kesalahan saat memproses pertanyaan Anda.",
                explanation = "Error: ${e.message}",
                subject = "Umum",
                createdAt = System.currentTimeMillis()
            )
            onResult(fallbackAnswer)
        }
    }
}
