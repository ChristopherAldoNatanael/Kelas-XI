package com.christopheraldoo.petheal.ui.screens.settings

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material3.AlertDialog
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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.BuildConfig

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AboutScreen(
    onNavigateBack: () -> Unit
) {
    val bgColor = Color(0xFFF6F8F6)
    val textColor = Color(0xFF0F172A)
    val secondaryColor = Color(0xFF64748B)
    var legalDialog by remember { mutableStateOf<String?>(null) }

    legalDialog?.let { type ->
        AlertDialog(
            onDismissRequest = { legalDialog = null },
            title = { Text(type, fontWeight = FontWeight.Bold) },
            text = {
                Text(
                    when (type) {
                        "Syarat Layanan" -> "PetHeal membantu pengguna mengelola booking dokter hewan, catatan medis, pengingat vaksinasi, dan status pembayaran. Pengguna bertanggung jawab menjaga data akun dan hewan peliharaan tetap akurat."
                        else -> "PetHeal menyimpan data profil, hewan, booking, notifikasi, dan status pembayaran untuk menjalankan layanan. Detail pembayaran diproses oleh Midtrans dan tidak disimpan sebagai data kartu mentah di aplikasi."
                    }
                )
            },
            confirmButton = {
                TextButton(onClick = { legalDialog = null }) { Text("Tutup") }
            }
        )
    }

    Scaffold(
        containerColor = bgColor,
        topBar = {
            TopAppBar(
                title = { Text("Tentang PetHeal", color = textColor, fontSize = 18.sp, fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back", tint = textColor)
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
                .padding(24.dp)
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Box(
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape)
                    .background(Color(0xFF2BEE6C))
                    .padding(24.dp),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Default.Pets, contentDescription = null, tint = Color.White)
            }

            Spacer(modifier = Modifier.height(24.dp))

            Text(
                text = "PetHeal",
                fontSize = 28.sp,
                fontWeight = FontWeight.Bold,
                color = textColor,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = "Versi ${BuildConfig.VERSION_NAME}",
                fontSize = 14.sp,
                color = secondaryColor,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(32.dp))

            Text(
                text = "PetHeal membantu Anda mengatur booking dokter hewan, memantau rekam medis, dan menjaga tindak lanjut kesehatan hewan kesayangan tetap rapi dalam satu aplikasi.",
                fontSize = 14.sp,
                color = secondaryColor,
                textAlign = TextAlign.Center,
                lineHeight = 22.sp,
                modifier = Modifier.padding(horizontal = 16.dp)
            )

            Spacer(modifier = Modifier.height(48.dp))

            SettingsSectionCard(title = "Informasi Hukum") {
                SettingsActionRow(
                    icon = Icons.Default.Info,
                    label = "Syarat Layanan",
                    onClick = { legalDialog = "Syarat Layanan" }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Info,
                    label = "Kebijakan Privasi",
                    onClick = { legalDialog = "Kebijakan Privasi" }
                )
            }

            Spacer(modifier = Modifier.height(32.dp))

            Text(
                text = "© 2026 PetHeal. Seluruh hak cipta dilindungi.",
                fontSize = 12.sp,
                color = secondaryColor.copy(alpha = 0.7f),
                textAlign = TextAlign.Center
            )
        }
    }
}
