package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.christopheraldoo.petheal.data.model.Clinic
import com.christopheraldoo.petheal.ui.theme.ClinicTheme

/**
 * PHASE 7: reusable tenant picker bottom sheet.
 *
 * Lists `GET /api/public/clinics` (active only, server-filtered).
 * The chosen clinic is persisted; registration sends its slug so the new
 * account is born bound (`clinic_id`, never null).
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClinicPickerSheet(
    onDismiss: () -> Unit,
    onClinicSelected: (Clinic) -> Unit,
    viewModel: ClinicPickerViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    LaunchedEffect(Unit) { viewModel.load() }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 28.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Pilih Klinik",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF0F172A)
                )
                IconButton(onClick = { viewModel.load(uiState.selectedSlug) }) {
                    Icon(Icons.Filled.Refresh, contentDescription = "Muat ulang")
                }
            }
            Text(
                text = "Satu akun terikat pada satu klinik. Data tiap klinik terisolasi penuh.",
                fontSize = 13.sp,
                color = Color(0xFF64748B)
            )
            Spacer(modifier = Modifier.height(16.dp))

            when {
                uiState.isLoading -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 32.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator()
                    }
                }
                uiState.error != null && uiState.clinics.isEmpty() -> {
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(
                            text = uiState.error ?: "Gagal memuat klinik",
                            fontSize = 13.sp,
                            color = MaterialTheme.colorScheme.error
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        Button(
                            onClick = { viewModel.load(uiState.selectedSlug) },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = ClinicTheme.FallbackPrimary
                            )
                        ) {
                            Text("Coba Lagi")
                        }
                    }
                }
                uiState.clinics.isEmpty() -> {
                    Text(
                        text = "Belum ada klinik aktif. Hubungi administrator.",
                        fontSize = 13.sp,
                        color = Color(0xFF64748B),
                        modifier = Modifier.padding(vertical = 24.dp)
                    )
                }
                else -> {
                    LazyColumn(
                        modifier = Modifier.heightIn(max = 320.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        items(
                            uiState.clinics,
                            key = { it.id?.toString() ?: it.slug ?: it.name.orEmpty() }
                        ) { clinic ->
                            ClinicPickerRow(
                                clinic = clinic,
                                selected = clinic.slug == uiState.selectedSlug,
                                onClick = {
                                    viewModel.select(clinic) { onClinicSelected(it) }
                                }
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * Shared tenant row (also used by the full-screen setup flow).
 */
@Composable
fun ClinicPickerRow(
    clinic: Clinic,
    selected: Boolean,
    onClick: () -> Unit
) {
    val brand = ClinicTheme.parsePrimaryColor(clinic.primaryColor)
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(if (selected) brand.copy(alpha = 0.10f) else Color(0xFFF8FAFC))
            .clickable(onClick = onClick)
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(48.dp)
                .clip(CircleShape)
                .background(brand.copy(alpha = 0.16f)),
            contentAlignment = Alignment.Center
        ) {
            if (!clinic.logoUrl.isNullOrBlank()) {
                AsyncImage(
                    model = clinic.logoUrl,
                    contentDescription = clinic.name,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .size(48.dp)
                        .clip(CircleShape)
                )
            } else {
                Icon(
                    imageVector = Icons.Filled.LocalHospital,
                    contentDescription = null,
                    tint = brand,
                    modifier = Modifier.size(26.dp)
                )
            }
        }
        Spacer(modifier = Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = clinic.name.orEmpty(),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = Color(0xFF0F172A),
                maxLines = 1,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
            )
            if (!clinic.address.isNullOrBlank()) {
                Text(
                    text = clinic.address.orEmpty(),
                    fontSize = 12.sp,
                    color = Color(0xFF64748B),
                    maxLines = 2
                )
            }
        }
        if (selected) {
            Icon(
                imageVector = Icons.Filled.CheckCircle,
                contentDescription = "Dipilih",
                tint = brand,
                modifier = Modifier.size(24.dp)
            )
        }
    }
}
