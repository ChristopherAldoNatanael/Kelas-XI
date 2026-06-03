package com.christopheraldoo.petheal.ui.screens.settings

import androidx.compose.foundation.background
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
fun AboutScreen(
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
                title = { Text("About PetHeal", color = textColor, fontSize = 18.sp, fontWeight = FontWeight.Bold) },
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
            // Logo
            Box(
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape)
                    .background(Color(0xFF2BEE6C)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Default.Pets, contentDescription = null, tint = Color.White, modifier = Modifier.size(50.dp))
            }

            Spacer(modifier = Modifier.height(24.dp))

            // App Name
            Text(
                text = "PetHeal",
                fontSize = 28.sp,
                fontWeight = FontWeight.Bold,
                color = textColor,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(8.dp))

            // Version
            Text(
                text = "Version 1.0.0",
                fontSize = 14.sp,
                color = secondaryColor,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(32.dp))

            // Description
            Text(
                text = "PetHeal is your trusted companion for pet health management. Book appointments, track medical records, and keep your furry friends happy and healthy.",
                fontSize = 14.sp,
                color = secondaryColor,
                textAlign = TextAlign.Center,
                lineHeight = 22.sp,
                modifier = Modifier.padding(horizontal = 16.dp)
            )

            Spacer(modifier = Modifier.height(48.dp))

            // Links Section
            SettingsSectionCard(title = "Legal") {
                SettingsActionRow(
                    icon = Icons.Default.Info,
                    label = "Terms of Service",
                    onClick = {}
                )
                Divider(color = secondaryColor.copy(alpha = 0.2f))
                SettingsActionRow(
                    icon = Icons.Default.Info,
                    label = "Privacy Policy",
                    onClick = {}
                )
            }

            Spacer(modifier = Modifier.height(32.dp))

            Text(
                text = "© 2026 PetHeal. All rights reserved.",
                fontSize = 12.sp,
                color = secondaryColor.copy(alpha = 0.6f),
                textAlign = TextAlign.Center
            )
        }
    }
}
