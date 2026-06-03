package com.christopheraldoo.petheal.ui.screens.settings

import androidx.compose.foundation.isSystemInDarkTheme
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PrivacySecurityScreen(
    onNavigateBack: () -> Unit
) {
    val isDark = isSystemInDarkTheme()
    val bgColor = if (isDark) Color(0xFF102216) else Color(0xFFF6F8F6)
    val textColor = if (isDark) Color(0xFFE8F5E9) else Color(0xFF0F172A)
    val secondaryColor = if (isDark) Color(0xFF9DB9A6) else Color(0xFF64748B)

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
                    label = "Biometric Login",
                    onClick = {}
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Key,
                    label = "Change Password",
                    onClick = {}
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Security,
                    label = "Two-Factor Authentication",
                    onClick = {}
                )
            }

            SettingsSectionCard(title = "Data & Privacy") {
                SettingsActionRow(
                    icon = Icons.Default.SwitchLeft,
                    label = "Manage Data Permissions",
                    onClick = {}
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.ArrowBack,
                    label = "Clear Cache",
                    onClick = {}
                )
            }
        }
    }
}
