package com.christopheraldoo.petheal.ui.screens.settings

import android.widget.Toast
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Badge
import androidx.compose.material.icons.filled.CleaningServices
import androidx.compose.material.icons.filled.Key
import androidx.compose.material.icons.filled.Security
import androidx.compose.material.icons.filled.SwitchLeft
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Divider
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.local.PreferencesManager

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PrivacySecurityScreen(
    onNavigateBack: () -> Unit,
    viewModel: SettingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val isDark = false
    val bgColor = if (isDark) Color(0xFFF6F8F6) else Color(0xFFF6F8F6)
    val textColor = if (isDark) Color(0xFFE8F5E9) else Color(0xFF0F172A)
    val secondaryColor = if (isDark) Color(0xFF9DB9A6) else Color(0xFF64748B)
    val context = LocalContext.current

    val isGoogleAccount = uiState.authProvider == PreferencesManager.AUTH_PROVIDER_GOOGLE
    var showPasswordDialog by remember { mutableStateOf(false) }
    var showPermissionsDialog by remember { mutableStateOf(false) }

    if (showPasswordDialog) {
        AlertDialog(
            onDismissRequest = {
                viewModel.clearMessages()
                showPasswordDialog = false
            },
            title = {
                Text(
                    if (isGoogleAccount) "Keamanan Akun Google" else "Reset Password",
                    fontWeight = FontWeight.Bold
                )
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    if (isGoogleAccount) {
                        Text("Akun ini masuk menggunakan Google. Pengaturan password dan perlindungan akun dikelola dari akun Google Anda.")
                    } else {
                        Text("Kirim kode reset ke email yang terdaftar, lalu selesaikan penggantian password dari halaman login.")
                        AccountInfoCard(
                            label = "Email reset",
                            value = uiState.userEmail ?: "Email akun tidak ditemukan"
                        )
                    }

                    uiState.resetMessage?.let {
                        Text(it, color = Color(0xFF047857), fontSize = 13.sp)
                    }

                    uiState.error?.let {
                        Text(it, color = Color(0xFFDC2626), fontSize = 13.sp)
                    }
                }
            },
            confirmButton = {
                if (isGoogleAccount) {
                    TextButton(onClick = {
                        viewModel.clearMessages()
                        showPasswordDialog = false
                    }) { Text("Tutup") }
                } else {
                    TextButton(
                        enabled = !uiState.isSendingReset && !uiState.userEmail.isNullOrBlank(),
                        onClick = { viewModel.sendPasswordReset(uiState.userEmail.orEmpty()) }
                    ) {
                        Text(if (uiState.isSendingReset) "Mengirim..." else "Kirim Kode Reset")
                    }
                }
            },
            dismissButton = {
                TextButton(onClick = {
                    viewModel.clearMessages()
                    showPasswordDialog = false
                }) { Text("Tutup") }
            }
        )
    }

    if (showPermissionsDialog) {
        AlertDialog(
            onDismissRequest = { showPermissionsDialog = false },
            title = { Text("Izin Data", fontWeight = FontWeight.Bold) },
            text = {
                Text("Aplikasi memakai akses kamera/galeri untuk foto hewan dan profil, izin notifikasi untuk pengingat, serta akses jaringan untuk sinkronisasi booking, pembayaran, dan rekam medis.")
            },
            confirmButton = {
                TextButton(onClick = { showPermissionsDialog = false }) { Text("Tutup") }
            }
        )
    }

    var showClearCacheDialog by remember { mutableStateOf(false) }
    if (showClearCacheDialog) {
        AlertDialog(
            onDismissRequest = { showClearCacheDialog = false },
            title = { Text("Bersihkan Cache?", fontWeight = FontWeight.Bold) },
            text = {
                Text("File sementara (seperti gambar yang tersimpan) akan dihapus. Data akun Anda tidak ikut terhapus.")
            },
            confirmButton = {
                TextButton(onClick = {
                    runCatching {
                        context.cacheDir.deleteRecursively()
                        context.externalCacheDir?.deleteRecursively()
                    }
                    showClearCacheDialog = false
                    Toast.makeText(context, "Cache sementara dibersihkan", Toast.LENGTH_SHORT).show()
                }) { Text("Bersihkan") }
            },
            dismissButton = {
                TextButton(onClick = { showClearCacheDialog = false }) { Text("Batal") }
            }
        )
    }

    Scaffold(
        containerColor = bgColor,
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        "Privasi & Keamanan",
                        color = textColor,
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Kembali", tint = textColor)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = bgColor)
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            SettingsSectionCard(title = "Keamanan Akun") {
                SettingsActionRow(
                    icon = Icons.Default.Badge,
                    label = "Metode Masuk",
                    showChevron = false,
                    enabled = false,
                    trailing = {
                        Text(
                            if (isGoogleAccount) "Google" else "Email",
                            color = secondaryColor,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    },
                    onClick = {}
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Key,
                    label = if (isGoogleAccount) "Keamanan Akun Google" else "Reset Password",
                    onClick = { showPasswordDialog = true }
                )
            }

            SettingsSectionCard(title = "Privasi") {
                SettingsActionRow(
                    icon = Icons.Default.SwitchLeft,
                    label = "Kelola Izin Data",
                    onClick = { showPermissionsDialog = true }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.CleaningServices,
                    label = "Bersihkan Cache",
                    onClick = { showClearCacheDialog = true }
                )
            }

            Card(
                colors = CardDefaults.cardColors(containerColor = Color.White)
            ) {
                Column(
                    modifier = Modifier.padding(18.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .clip(CircleShape)
                            .padding(2.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.Security, contentDescription = null, tint = Color(0xFF18C964))
                    }
                    Text("Hanya pengaturan yang benar-benar aktif yang ditampilkan di sini.", color = textColor, fontWeight = FontWeight.SemiBold)
                    Text(
                        "Biometric sign-in dan autentikasi dua langkah disembunyikan sampai benar-benar memiliki perlindungan akun yang nyata. Ini menjaga layar tetap jujur dan menghindari UI yang terlihat siap tetapi tidak melakukan apa-apa.",
                        color = secondaryColor,
                        fontSize = 13.sp,
                        lineHeight = 20.sp,
                        textAlign = TextAlign.Start
                    )
                }
            }
        }
    }
}

@Composable
private fun AccountInfoCard(label: String, value: String) {
    Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC))) {
        Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp)) {
            Text(label, color = Color(0xFF64748B), fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
            Text(value, color = Color(0xFF0F172A), fontSize = 14.sp, fontWeight = FontWeight.Medium)
        }
    }
}
