package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.util.ClinicContact

/**
 * Sheet bantuan klinik yang dipakai di seluruh aplikasi.
 *
 * Sumber kontak & perilaku tombol ada di [ClinicContactActions] supaya sheet
 * ini dan halaman Bantuan & Dukungan selalu identik. Nomor diambil dari
 * Clinic Profile (`clinics.phone`), bukan setting terpisah di aplikasi.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClinicHelpSheet(
    contact: ClinicContact?,
    message: String,
    isLoading: Boolean,
    error: String?,
    onRetry: () -> Unit,
    onDismiss: () -> Unit,
    subtitle: String? = null
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val clinicName = contact?.clinicName?.trim()?.takeIf { it.isNotBlank() }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Background,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
        dragHandle = {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 10.dp, bottom = 4.dp),
                contentAlignment = Alignment.Center
            ) {
                Box(
                    Modifier
                        .width(40.dp)
                        .height(4.dp)
                        .background(OutlineStrong, RoundedCornerShape(2.dp))
                )
            }
        }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .verticalScroll(rememberScrollState())
                .padding(start = 20.dp, end = 20.dp, bottom = 24.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(
                    "Butuh bantuan?",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
                Text(
                    subtitle ?: clinicName?.let { "Hubungi admin $it langsung dari sini." }
                        ?: "Hubungi admin klinik langsung dari sini.",
                    fontSize = 12.sp,
                    color = TextSecondary,
                    lineHeight = 17.sp
                )
            }

            ClinicContactActions(
                contact = contact,
                message = message,
                isLoading = isLoading,
                error = error,
                onRetry = onRetry,
                showTips = true
            )
        }
    }
}
