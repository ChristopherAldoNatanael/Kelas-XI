package com.christopheraldoo.petheal.ui.screens.settings

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HelpSupportScreen(
    onNavigateBack: () -> Unit
) {
    val isDark = false
    val bgColor = if (isDark) Color(0xFFF6F8F6) else Color(0xFFF6F8F6)
    val textColor = if (isDark) Color(0xFFE8F5E9) else Color(0xFF0F172A)
    val secondaryColor = if (isDark) Color(0xFF9DB9A6) else Color(0xFF64748B)
    val context = LocalContext.current
    var showFaqDialog by remember { mutableStateOf(false) }

    if (showFaqDialog) {
        AlertDialog(
            onDismissRequest = { showFaqDialog = false },
            title = { Text("FAQ", fontWeight = FontWeight.Bold) },
            text = {
                Text(
                    "Booking: choose doctor, pet, schedule, and payment type.\n\nPayment: use Midtrans sandbox while testing. If setup fails, check Payment Preflight in backend health.\n\nPet health: weight and vaccination records are available from pet detail."
                )
            },
            confirmButton = {
                TextButton(onClick = { showFaqDialog = false }) { Text("Close") }
            }
        )
    }

    Scaffold(
        containerColor = bgColor,
        topBar = {
            TopAppBar(
                title = { Text("Help & Support", color = textColor, fontSize = 18.sp, fontWeight = FontWeight.Bold) },
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
                .padding(16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            SettingsSectionCard(title = "Support") {
                SettingsActionRow(
                    icon = Icons.Default.Email,
                    label = "Contact Us",
                    onClick = {
                        val intent = Intent(Intent.ACTION_SENDTO).apply {
                            data = Uri.parse("mailto:support@petheal.app")
                            putExtra(Intent.EXTRA_SUBJECT, "PetHeal Support")
                        }
                        context.startActivity(intent)
                    }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Phone,
                    label = "Call Support",
                    onClick = {
                        context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:+6281234567890")))
                    }
                )
            }

            SettingsSectionCard(title = "Resources") {
                SettingsActionRow(
                    icon = Icons.Default.HelpOutline,
                    label = "FAQ",
                    onClick = { showFaqDialog = true }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.BugReport,
                    label = "Report a Bug",
                    onClick = {
                        val intent = Intent(Intent.ACTION_SENDTO).apply {
                            data = Uri.parse("mailto:support@petheal.app")
                            putExtra(Intent.EXTRA_SUBJECT, "PetHeal Bug Report")
                        }
                        context.startActivity(intent)
                    }
                )
            }
        }
    }
}
