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
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.navigation.NavController
import com.christopheraldoo.bukuringkasapp.data.model.SummaryData
import com.christopheraldoo.bukuringkasapp.ui.theme.EducationalTypography
import com.christopheraldoo.bukuringkasapp.ui.theme.GradientEnd
import com.christopheraldoo.bukuringkasapp.ui.theme.GradientMiddle
import com.christopheraldoo.bukuringkasapp.ui.theme.GradientStart
import com.christopheraldoo.bukuringkasapp.ui.theme.PrimaryBlue
import com.christopheraldoo.bukuringkasapp.ui.theme.getSubjectColor

/**
 * Home Screen - Layar utama aplikasi BukuRingkasApp
 * Menampilkan menu utama dan ringkasan terbaru
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(navController: NavController) {
    var recentSummaries by remember { mutableStateOf(listOf<SummaryData>()) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    colors = listOf(
                        MaterialTheme.colorScheme.background,
                        MaterialTheme.colorScheme.surface
                    )
                )
            )
    ) {
        // Header Section dengan gradient background
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.horizontalGradient(
                        colors = listOf(GradientStart, GradientMiddle, GradientEnd)
                    )
                )
                .padding(24.dp)
        ) {
            Column {
                Text(
                    text = "Selamat Datang di BukuRingkasApp! 📚",
                    style = MaterialTheme.typography.headlineMedium,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Aplikasi asisten belajar AI untuk pelajar SD, SMP, dan SMA",
                    style = MaterialTheme.typography.bodyLarge,
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
            // Quick Actions Section
            item {
                Text(
                    text = "Aksi Cepat",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            }

            // Main Action Buttons
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Scan Buku Button
                    ActionButton(
                        title = "📸 Scan Buku",
                        description = "Foto halaman buku untuk diringkas",
                        color = PrimaryBlue,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate("scan") }
                    )

                    // Input Teks Button
                    ActionButton(
                        title = "✍️ Input Teks",
                        description = "Ketik teks manual untuk diringkas",
                        color = MaterialTheme.colorScheme.secondary,
                        modifier = Modifier.weight(1f),
                        onClick = { /* Navigate to text input */ }
                    )
                }
            }

            // Ask AI Section
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Ask AI Button
                    ActionButton(
                        title = "🤖 Tanya AI",
                        description = "Ajukan pertanyaan tentang pelajaran",
                        color = MaterialTheme.colorScheme.tertiary,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate("ask_ai") }
                    )

                    // History Button
                    ActionButton(
                        title = "📚 Riwayat",
                        description = "Lihat ringkasan dan pertanyaan sebelumnya",
                        color = MaterialTheme.colorScheme.surfaceVariant,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate("history") }
                    )
                }
            }

            // Recent Summaries Section
            item {
                Text(
                    text = "Ringkasan Terbaru",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            }

            // Recent Summaries List
            if (recentSummaries.isEmpty()) {
                item {
                    EmptyStateCard(
                        message = "Belum ada ringkasan",
                        description = "Mulai dengan scan buku atau input teks untuk melihat ringkasan di sini"
                    )
                }
            } else {
                items(recentSummaries) { summary ->
                    SummaryCard(summary = summary)
                }
            }

            // Tips Section
            item {
                TipsCard()
            }

            // Bottom spacing
            item {
                Spacer(modifier = Modifier.height(32.dp))
            }
        }
    }
}

/**
 * Action Button untuk menu utama
 */
@Composable
fun ActionButton(
    title: String,
    description: String,
    color: Color,
    modifier: Modifier = Modifier,
    onClick: () -> Unit
) {
    Card(
        modifier = modifier
            .height(120.dp)
            .clip(RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = color.copy(alpha = 0.1f),
            contentColor = color
        ),
        onClick = onClick
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = description,
                style = MaterialTheme.typography.bodySmall,
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
            )
        }
    }
}

/**
 * Card untuk menampilkan ringkasan terbaru
 */
@Composable
fun SummaryCard(summary: SummaryData) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp)),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Subject color indicator
            Box(
                modifier = Modifier
                    .size(4.dp)
                    .clip(CircleShape)
                    .background(getSubjectColor(summary.subject))
            )

            Spacer(modifier = Modifier.size(12.dp))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = summary.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${summary.subject} • Kelas ${summary.grade}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )
                Text(
                    text = summary.mainConcept.take(100) + if (summary.mainConcept.length > 100) "..." else "",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
            }

            Spacer(modifier = Modifier.size(12.dp))

            Icon(
                painter = painterResource(id = android.R.drawable.ic_menu_info_details),
                contentDescription = "Detail",
                tint = MaterialTheme.colorScheme.primary
            )
        }
    }
}

/**
 * Card untuk state kosong
 */
@Composable
fun EmptyStateCard(
    message: String,
    description: String
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                painter = painterResource(id = android.R.drawable.ic_menu_today),
                contentDescription = null,
                modifier = Modifier.size(48.dp),
                tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = message,
                style = MaterialTheme.typography.titleMedium,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = description,
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
            )
        }
    }
}

/**
 * Card untuk tips penggunaan aplikasi
 */
@Composable
fun TipsCard() {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp)),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.1f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    painter = painterResource(id = android.R.drawable.ic_menu_info_details),
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.size(8.dp))
                Text(
                    text = "Tips Penggunaan",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
            }

            Spacer(modifier = Modifier.height(12.dp))

            listOf(
                "📷 Foto halaman buku dengan pencahayaan yang baik",
                "✍️ Ketik teks pelajaran yang ingin diringkas",
                "🤖 Ajukan pertanyaan tentang mata pelajaran",
                "📚 Lihat riwayat untuk mengulang materi"
            ).forEach { tip ->
                Text(
                    text = "• $tip",
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.padding(vertical = 2.dp)
                )
            }
        }
    }
}
