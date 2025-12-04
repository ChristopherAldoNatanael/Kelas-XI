package com.christopheraldoo.bukuringkasapp.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavController
import com.christopheraldoo.bukuringkasapp.ui.theme.PrimaryBlue
import com.christopheraldoo.bukuringkasapp.viewmodel.SettingsViewModel

/**
 * Settings Screen - Untuk mengatur konfigurasi aplikasi
 * Pengaturan bahasa dan tema aplikasi
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(navController: NavController) {
    var selectedLanguage by remember { mutableStateOf("id") }
    var isDarkTheme by remember { mutableStateOf(false) }
    var showLanguageDialog by remember { mutableStateOf(false) }
    var showThemeDialog by remember { mutableStateOf(false) }

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
                    text = "⚙️ Pengaturan",
                    style = MaterialTheme.typography.headlineSmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Konfigurasi aplikasi Buku Ringkas",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.9f)
                )
            }
        }

        // Main Content
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Language Settings
            Card(
                modifier = Modifier.fillMaxWidth(),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                shape = RoundedCornerShape(8.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Text(
                        text = "🌐 Bahasa Aplikasi",
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    Text(
                        text = "Pilih bahasa yang digunakan dalam aplikasi",
                        fontSize = 14.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = when(selectedLanguage) {
                                "id" -> "🇮🇩 Bahasa Indonesia"
                                "en" -> "🇺🇸 English"
                                else -> "🇮🇩 Bahasa Indonesia"
                            },
                            fontSize = 16.sp
                        )

                        Button(
                            onClick = { showLanguageDialog = true }
                        ) {
                            Text("Ubah")
                        }
                    }
                }
            }

            // Theme Settings
            Card(
                modifier = Modifier.fillMaxWidth(),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                shape = RoundedCornerShape(8.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Text(
                        text = "🎨 Tema Aplikasi",
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    Text(
                        text = "Pilih tema terang atau gelap untuk aplikasi",
                        fontSize = 14.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = if (isDarkTheme) "🌙 Tema Gelap" else "☀️ Tema Terang",
                            fontSize = 16.sp
                        )

                        Button(
                            onClick = { showThemeDialog = true }
                        ) {
                            Text("Ubah")
                        }
                    }
                }
            }

            // App Information
            Card(
                modifier = Modifier.fillMaxWidth(),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                shape = RoundedCornerShape(8.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Text(
                        text = "ℹ️ Informasi Aplikasi",
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp
                    )

                    Spacer(modifier = Modifier.height(12.dp))

                    listOf(
                        "📚 Buku Ringkas App v1.0",
                        "🤖 AI-Powered Learning Assistant",
                        "📷 Text Scanner & Summarizer",
                        "🌐 Multi-language Support",
                        "👨‍💻 Developed for Students"
                    ).forEach { info ->
                        Text(
                            text = info,
                            fontSize = 14.sp,
                            modifier = Modifier.padding(vertical = 2.dp)
                        )
                    }
                }
            }
        }
    }

    // Language Selection Dialog
    if (showLanguageDialog) {
        AlertDialog(
            onDismissRequest = { showLanguageDialog = false },
            title = { Text("Pilih Bahasa") },
            text = {
                Column {
                    Text("Pilih bahasa yang akan digunakan:")
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("• 🇮🇩 Bahasa Indonesia")
                    Text("• 🇺🇸 English")
                }
            },
            confirmButton = {
                Row {
                    Button(
                        onClick = {
                            selectedLanguage = "id"
                            showLanguageDialog = false
                        }
                    ) {
                        Text("Indonesia")
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Button(
                        onClick = {
                            selectedLanguage = "en"
                            showLanguageDialog = false
                        }
                    ) {
                        Text("English")
                    }
                }
            },
            dismissButton = {
                Button(onClick = { showLanguageDialog = false }) {
                    Text("Batal")
                }
            }
        )
    }

    // Theme Selection Dialog
    if (showThemeDialog) {
        AlertDialog(
            onDismissRequest = { showThemeDialog = false },
            title = { Text("Pilih Tema") },
            text = {
                Column {
                    Text("Pilih tema tampilan aplikasi:")
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("• ☀️ Tema Terang - Untuk penggunaan siang hari")
                    Text("• 🌙 Tema Gelap - Untuk penggunaan malam hari")
                }
            },
            confirmButton = {
                Row {
                    Button(
                        onClick = {
                            isDarkTheme = false
                            showThemeDialog = false
                        }
                    ) {
                        Text("Terang")
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Button(
                        onClick = {
                            isDarkTheme = true
                            showThemeDialog = false
                        }
                    ) {
                        Text("Gelap")
                    }
                }
            },
            dismissButton = {
                Button(onClick = { showThemeDialog = false }) {
                    Text("Batal")
                }
            }
        )
    }
}
