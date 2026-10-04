package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Domain
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.local.PreferencesManager

/**
 * PHASE 7: finish-setup for accounts without a clinic binding.
 *
 * Shown only when the login response carries no clinic. Google accounts are
 * repaired in place (empty account dropped, re-registered bound). Email
 * accounts cannot be repaired without their password, so they are guided
 * to sign out and contact their clinic admin instead.
 */
@Composable
fun CompleteSetupScreen(
    onSetupComplete: () -> Unit,
    onLoggedOut: () -> Unit,
    viewModel: CompleteSetupViewModel = hiltViewModel(),
    pickerViewModel: ClinicPickerViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val pickerState by pickerViewModel.uiState.collectAsState()
    val provider by viewModel.authProvider.collectAsState(initial = null)
    val isGoogle = provider != PreferencesManager.AUTH_PROVIDER_EMAIL_PASSWORD

    LaunchedEffect(Unit) { pickerViewModel.load() }
    LaunchedEffect(uiState.isDone) {
        if (uiState.isDone) onSetupComplete()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Color.White, Color(0xFFF2FBF5), Color(0xFFEFFAF3))
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 20.dp)
                .padding(top = 56.dp, bottom = 28.dp)
        ) {
            // ── Header ──────────────────────────────────────────────
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Box(
                    modifier = Modifier
                        .size(72.dp)
                        .clip(CircleShape)
                        .background(Color(0xFF18C964).copy(alpha = 0.14f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Domain,
                        contentDescription = null,
                        tint = Color(0xFF18C964),
                        modifier = Modifier.size(34.dp)
                    )
                }
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Selesaikan Pendaftaran",
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF0F172A)
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Langkah 2 dari 2 — Pilih klinik",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = Color(0xFF18C964)
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Akun Anda belum terhubung ke klinik mana pun, " +
                        "sehingga layanan belum dapat digunakan. " +
                        "Pilih klinik tempat Anda berobat.",
                    fontSize = 14.sp,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Center,
                    lineHeight = 20.sp
                )
            }

            Spacer(modifier = Modifier.height(20.dp))

            // ── Clinic list ─────────────────────────────────────────
            when {
                pickerState.isLoading -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(color = Color(0xFF18C964))
                    }
                }
                pickerState.error != null && pickerState.clinics.isEmpty() -> {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Text(
                            text = pickerState.error ?: "Gagal memuat klinik",
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.error,
                            textAlign = TextAlign.Center
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        Button(
                            onClick = { pickerViewModel.load(pickerState.selectedSlug) },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFF18C964)
                            )
                        ) {
                            Text("Coba Lagi")
                        }
                    }
                }
                else -> {
                    LazyColumn(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        items(pickerState.clinics, key = { it.id ?: it.slug.orEmpty() }) { clinic ->
                            ClinicPickerRow(
                                clinic = clinic,
                                selected = clinic.slug == pickerState.selectedSlug,
                                onClick = { pickerViewModel.select(clinic) }
                            )
                        }
                    }
                }
            }

            uiState.error?.let {
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = it,
                    color = MaterialTheme.colorScheme.error,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            // ── Actions ─────────────────────────────────────────────
            if (isGoogle) {
                Button(
                    onClick = {
                        pickerState.selectedSlug?.let { viewModel.complete(it) }
                    },
                    enabled = !uiState.isLoading && !pickerState.selectedSlug.isNullOrBlank(),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color(0xFF18C964),
                        contentColor = Color.White
                    )
                ) {
                    if (uiState.isLoading) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(22.dp),
                            color = Color.White,
                            strokeWidth = 2.5.dp
                        )
                    } else {
                        Text("Simpan & Lanjutkan", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    }
                }
            } else {
                Text(
                    text = "Akun email yang belum terhubung perlu dikaitkan " +
                        "oleh admin klinik. Silakan keluar lalu hubungi klinik Anda.",
                    fontSize = 13.sp,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
                Spacer(modifier = Modifier.height(8.dp))
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center
            ) {
                TextButton(onClick = { viewModel.logout(onLoggedOut) }) {
                    Text(
                        "Keluar",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF64748B)
                    )
                }
            }
            Spacer(modifier = Modifier.width(1.dp))
        }
    }
}
