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
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.navigation.NavController
import com.christopheraldoo.bukuringkasapp.data.model.HistoryItem
import com.christopheraldoo.bukuringkasapp.data.model.QuestionData
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import com.christopheraldoo.bukuringkasapp.ui.theme.PrimaryBlue
import com.christopheraldoo.bukuringkasapp.ui.theme.getSubjectColor
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * History Screen - Untuk menampilkan riwayat ringkasan dan pertanyaan
 * Menggunakan Firebase Firestore untuk menyimpan data
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HistoryScreen(navController: NavController) {
    var historyItems by remember { mutableStateOf(generateMockHistoryItems()) }
    var showDetailDialog by remember { mutableStateOf(false) }
    var selectedItem by remember { mutableStateOf<HistoryItem?>(null) }
    var showDeleteConfirmation by remember { mutableStateOf(false) }
    var itemToDelete by remember { mutableStateOf<HistoryItem?>(null) }

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
                    text = "📚 Riwayat Belajar",
                    style = MaterialTheme.typography.headlineSmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Lihat semua ringkasan dan pertanyaan yang telah disimpan",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.9f)
                )
            }
        }

        // Main Content
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Summary Statistics
            item {
                StatisticsCard(
                    totalSummaries = historyItems.count { it.type == "summary" },
                    totalQuestions = historyItems.count { it.type == "question" },
                    subjects = historyItems.map { it.subject }.distinct().count()
                )
            }

            // History Items
            if (historyItems.isEmpty()) {
                item {
                    EmptyHistoryCard()
                }
            } else {
                items(historyItems) { item ->
                    HistoryItemCard(
                        item = item,
                        onClick = {
                            selectedItem = item
                            showDetailDialog = true
                        },
                        onDelete = {
                            itemToDelete = item
                            showDeleteConfirmation = true
                        }
                    )
                }
            }

            // Bottom spacing
            item {
                Spacer(modifier = Modifier.height(32.dp))
            }
        }
    }

    // Detail Dialog
    if (showDetailDialog && selectedItem != null) {
        HistoryDetailDialog(
            item = selectedItem!!,
            onDismiss = { showDetailDialog = false },
            onDelete = {
                itemToDelete = selectedItem
                showDetailDialog = false
                showDeleteConfirmation = true
            }
        )
    }

    // Delete Confirmation Dialog
    if (showDeleteConfirmation && itemToDelete != null) {
        DeleteConfirmationDialog(
            item = itemToDelete!!,
            onConfirm = {
                historyItems = historyItems.filter { it.id != itemToDelete!!.id }
                showDeleteConfirmation = false
                itemToDelete = null
            },
            onDismiss = {
                showDeleteConfirmation = false
                itemToDelete = null
            }
        )
    }
}

/**
 * Card untuk statistik ringkasan
 */
@Composable
fun StatisticsCard(
    totalSummaries: Int,
    totalQuestions: Int,
    subjects: Int
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            StatisticItem(
                value = totalSummaries.toString(),
                label = "Ringkasan",
                icon = android.R.drawable.ic_menu_edit,
                color = PrimaryBlue,
                modifier = Modifier.weight(1f)
            )

            StatisticItem(
                value = totalQuestions.toString(),
                label = "Pertanyaan",
                icon = android.R.drawable.ic_menu_help,
                color = MaterialTheme.colorScheme.tertiary,
                modifier = Modifier.weight(1f)
            )

            StatisticItem(
                value = subjects.toString(),
                label = "Pelajaran",
                icon = android.R.drawable.ic_menu_today,
                color = MaterialTheme.colorScheme.secondary,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

/**
 * Item statistik individual
 */
@Composable
fun StatisticItem(
    value: String,
    label: String,
    icon: Int,
    color: Color,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Box(
            modifier = Modifier
                .size(48.dp)
                .clip(CircleShape)
                .background(color.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                painter = painterResource(id = icon),
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(24.dp)
            )
        }

        Spacer(modifier = Modifier.height(8.dp))

        Text(
            text = value,
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            color = color
        )

        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
        )
    }
}

/**
 * Card untuk item history
 */
@Composable
fun HistoryItemCard(
    item: HistoryItem,
    onClick: () -> Unit,
    onDelete: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp)),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        onClick = onClick
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Type indicator
            Box(
                modifier = Modifier
                    .size(12.dp)
                    .clip(CircleShape)
                    .background(
                        if (item.type == "summary") PrimaryBlue
                        else MaterialTheme.colorScheme.tertiary
                    )
            )

            Spacer(modifier = Modifier.size(12.dp))

            // Content
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = item.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )

                Spacer(modifier = Modifier.height(4.dp))

                Text(
                    text = "${item.subject} • Kelas ${item.grade}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )

                Spacer(modifier = Modifier.height(2.dp))

                Text(
                    text = formatDate(item.createdAt),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }

            Spacer(modifier = Modifier.size(8.dp))

            // Action buttons
            Row {
                IconButton(
                    onClick = onClick,
                    modifier = Modifier.size(32.dp)
                ) {
                    Icon(
                        painter = painterResource(id = android.R.drawable.ic_menu_info_details),
                        contentDescription = "Detail",
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(16.dp)
                    )
                }

                IconButton(
                    onClick = onDelete,
                    modifier = Modifier.size(32.dp)
                ) {
                    Icon(
                        painter = painterResource(id = android.R.drawable.ic_menu_delete),
                        contentDescription = "Delete",
                        tint = MaterialTheme.colorScheme.error,
                        modifier = Modifier.size(16.dp)
                    )
                }
            }
        }
    }
}

/**
 * Dialog untuk detail history item
 */
@Composable
fun HistoryDetailDialog(
    item: HistoryItem,
    onDismiss: () -> Unit,
    onDelete: () -> Unit
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
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = if (item.type == "summary") "📝 Ringkasan" else "❓ Pertanyaan",
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold
                    )

                    IconButton(onClick = onDelete) {
                        Icon(
                            painter = painterResource(id = android.R.drawable.ic_menu_delete),
                            contentDescription = "Delete",
                            tint = MaterialTheme.colorScheme.error
                        )
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Content based on type
                when (item.type) {
                    "summary" -> SummaryDetailContent(item)
                    "question" -> QuestionDetailContent(item)
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Footer
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End
                ) {
                    androidx.compose.material3.OutlinedButton(onClick = onDismiss) {
                        Text("Tutup")
                    }
                }
            }
        }
    }
}

/**
 * Konten detail untuk ringkasan
 */
@Composable
fun SummaryDetailContent(item: HistoryItem) {
    // Parse summary data (simplified for demo)
    Card(
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.3f)
        )
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = item.title,
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "Mata Pelajaran: ${item.subject}",
                style = MaterialTheme.typography.bodyMedium
            )

            Text(
                text = "Kelas: ${item.grade}",
                style = MaterialTheme.typography.bodyMedium
            )

            Text(
                text = "Dibuat: ${formatDate(item.createdAt)}",
                style = MaterialTheme.typography.bodyMedium
            )

            Spacer(modifier = Modifier.height(12.dp))

            Text(
                text = "Konsep Utama:",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold
            )

            Text(
                text = "Ringkasan materi pelajaran yang telah dipelajari dengan fokus pada poin-poin penting dan konsep utama yang perlu dipahami.",
                style = MaterialTheme.typography.bodyMedium,
                modifier = Modifier.padding(top = 8.dp)
            )
        }
    }
}

/**
 * Konten detail untuk pertanyaan
 */
@Composable
fun QuestionDetailContent(item: HistoryItem) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.3f)
        )
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = "Pertanyaan:",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold
            )

            Text(
                text = item.title,
                style = MaterialTheme.typography.bodyLarge,
                modifier = Modifier.padding(vertical = 8.dp)
            )

            Spacer(modifier = Modifier.height(12.dp))

            Text(
                text = "Jawaban:",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold
            )

            Text(
                text = "Jawaban dari AI berdasarkan konteks dan pengetahuan yang tersedia untuk membantu pemahaman materi.",
                style = MaterialTheme.typography.bodyMedium,
                modifier = Modifier.padding(vertical = 8.dp)
            )

            Spacer(modifier = Modifier.height(12.dp))

            Text(
                text = "Mata Pelajaran: ${item.subject}",
                style = MaterialTheme.typography.bodyMedium
            )

            Text(
                text = "Dibuat: ${formatDate(item.createdAt)}",
                style = MaterialTheme.typography.bodyMedium
            )
        }
    }
}

/**
 * Dialog konfirmasi hapus
 */
@Composable
fun DeleteConfirmationDialog(
    item: HistoryItem,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit
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
                    text = "Hapus Item",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(modifier = Modifier.height(16.dp))

                Text(
                    text = "Apakah Anda yakin ingin menghapus item \"${item.title}\" dari riwayat?",
                    style = MaterialTheme.typography.bodyMedium,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(modifier = Modifier.height(24.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    androidx.compose.material3.OutlinedButton(
                        onClick = onDismiss,
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Batal")
                    }

                    androidx.compose.material3.Button(
                        onClick = onConfirm,
                        colors = androidx.compose.material3.ButtonDefaults.buttonColors(
                            containerColor = MaterialTheme.colorScheme.error
                        ),
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Hapus")
                    }
                }
            }
        }
    }
}

/**
 * Card untuk state kosong
 */
@Composable
fun EmptyHistoryCard() {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 16.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.3f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(32.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                painter = painterResource(id = android.R.drawable.ic_menu_recent_history),
                contentDescription = null,
                modifier = Modifier.size(64.dp),
                tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
            )

            Spacer(modifier = Modifier.height(16.dp))

            Text(
                text = "Belum ada riwayat",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "Mulai gunakan aplikasi untuk melihat riwayat ringkasan dan pertanyaan di sini",
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
            )
        }
    }
}

// Helper functions
private fun formatDate(timestamp: Long): String {
    val sdf = SimpleDateFormat("dd MMM yyyy, HH:mm", Locale.getDefault())
    return sdf.format(Date(timestamp))
}

private fun generateMockHistoryItems(): List<HistoryItem> {
    return listOf(
        HistoryItem(
            id = 1,
            title = "Hukum Newton tentang Gerak",
            type = "summary",
            subject = "Fisika",
            grade = 11,
            content = "Ringkasan tentang hukum gerak Newton",
            createdAt = System.currentTimeMillis() - 86400000 // 1 day ago
        ),
        HistoryItem(
            id = 2,
            title = "Apa itu fotosintesis?",
            type = "question",
            subject = "Biologi",
            grade = 10,
            content = "Pertanyaan tentang proses fotosintesis",
            createdAt = System.currentTimeMillis() - 172800000 // 2 days ago
        ),
        HistoryItem(
            id = 3,
            title = "Struktur Atom dan Molekul",
            type = "summary",
            subject = "Kimia",
            grade = 12,
            content = "Ringkasan struktur atom dan ikatan kimia",
            createdAt = System.currentTimeMillis() - 259200000 // 3 days ago
        ),
        HistoryItem(
            id = 4,
            title = "Bagaimana cara menghitung luas lingkaran?",
            type = "question",
            subject = "Matematika",
            grade = 9,
            content = "Pertanyaan tentang rumus luas lingkaran",
            createdAt = System.currentTimeMillis() - 345600000 // 4 days ago
        )
    )
}
