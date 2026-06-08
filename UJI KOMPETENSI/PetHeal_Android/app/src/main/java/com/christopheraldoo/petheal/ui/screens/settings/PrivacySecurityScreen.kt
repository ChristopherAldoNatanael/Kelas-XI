package com.christopheraldoo.petheal.ui.screens.settings

import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PrivacySecurityScreen(
    onNavigateBack: () -> Unit
) {
    val isDark = false
    val bgColor = if (isDark) Color(0xFFF6F8F6) else Color(0xFFF6F8F6)
    val textColor = if (isDark) Color(0xFFE8F5E9) else Color(0xFF0F172A)
    val secondaryColor = if (isDark) Color(0xFF9DB9A6) else Color(0xFF64748B)
    val context = LocalContext.current
    var biometricEnabled by remember { mutableStateOf(false) }
    var twoFactorEnabled by remember { mutableStateOf(false) }
    var showPasswordDialog by remember { mutableStateOf(false) }
    var showPermissionsDialog by remember { mutableStateOf(false) }

    if (showPasswordDialog) {
        AlertDialog(
            onDismissRequest = { showPasswordDialog = false },
            title = { Text("Change Password", fontWeight = FontWeight.Bold) },
            text = { Text("Use Forgot Password from the login screen to receive a reset code and create a new password. Google accounts use Google account security.") },
            confirmButton = {
                TextButton(onClick = { showPasswordDialog = false }) { Text("Close") }
            }
        )
    }

    if (showPermissionsDialog) {
        AlertDialog(
            onDismissRequest = { showPermissionsDialog = false },
            title = { Text("Data Permissions", fontWeight = FontWeight.Bold) },
            text = { Text("PetHeal uses camera/gallery access for pet and profile photos, notification permission for booking and vaccination reminders, and network access for backend sync.") },
            confirmButton = {
                TextButton(onClick = { showPermissionsDialog = false }) { Text("Close") }
            }
        )
    }

    Scaffold(
        containerColor = bgColor,
        topBar = {
            TopAppBar(
                title = { Text("Privacy & Security", color = textColor, fontSize = 18.sp, fontWeight = FontWeight.Bold) },
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
            SettingsSectionCard(title = "Security") {
                SettingsActionRow(
                    icon = Icons.Default.Fingerprint,
                    label = "Biometric Login ${if (biometricEnabled) "On" else "Off"}",
                    onClick = { biometricEnabled = !biometricEnabled }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Key,
                    label = "Change Password",
                    onClick = { showPasswordDialog = true }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Security,
                    label = "Two-Factor Authentication ${if (twoFactorEnabled) "On" else "Off"}",
                    onClick = { twoFactorEnabled = !twoFactorEnabled }
                )
            }

            SettingsSectionCard(title = "Data & Privacy") {
                SettingsActionRow(
                    icon = Icons.Default.SwitchLeft,
                    label = "Manage Data Permissions",
                    onClick = { showPermissionsDialog = true }
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.CleaningServices,
                    label = "Clear Cache",
                    onClick = {
                        runCatching {
                            context.cacheDir.deleteRecursively()
                            context.externalCacheDir?.deleteRecursively()
                        }
                        Toast.makeText(context, "Cache cleared", Toast.LENGTH_SHORT).show()
                    }
                )
            }
        }
    }
}
