package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.HelpOutline
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.util.ClinicContact
import com.christopheraldoo.petheal.util.normalizeWhatsAppNumber
import com.christopheraldoo.petheal.util.openClinicEmail
import com.christopheraldoo.petheal.util.openClinicPhone
import com.christopheraldoo.petheal.util.openWhatsApp

/**
 * Aksi kontak klinik yang dipakai bersama oleh sheet Bantuan dan halaman
 * Bantuan & Dukungan. Tampilan, state, dan perilakunya satu sumber sehingga
 * pengalaman di seluruh aplikasi konsisten.
 *
 * WhatsApp hanya aktif bila nomor klinik valid; telepon & email mengikuti
 * data yang diisi admin di Clinic Profile.
 */
@Composable
fun ClinicContactActions(
    contact: ClinicContact?,
    message: String,
    isLoading: Boolean,
    error: String?,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    showTips: Boolean = false
) {
    val context = LocalContext.current
    val whatsAppNumber = normalizeWhatsAppNumber(contact?.phone)
    val email = contact?.email?.trim()?.takeIf { it.isNotBlank() }
    val clinicName = contact?.clinicName?.trim()?.takeIf { it.isNotBlank() }

    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        when {
            isLoading && contact == null -> LoadingContact()
            contact == null -> ContactLoadError(
                message = error ?: "Kontak klinik belum bisa dimuat.",
                onRetry = onRetry
            )
            else -> {
                WhatsAppOption(
                    phone = whatsAppNumber,
                    onClick = {
                        openWhatsApp(context, whatsAppNumber, message)
                    }
                )

                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    ContactShortcut(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Filled.Phone,
                        title = "Telepon",
                        subtitle = whatsAppNumber?.let { "+$it" } ?: "Belum diatur",
                        enabled = whatsAppNumber != null,
                        onClick = { openClinicPhone(context, whatsAppNumber) }
                    )
                    ContactShortcut(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Filled.Email,
                        title = "Email",
                        subtitle = email ?: "Belum diatur",
                        enabled = email != null,
                        onClick = {
                            openClinicEmail(
                                context = context,
                                address = email,
                                subject = "Bantuan" + (clinicName?.let { " - $it" } ?: "")
                            )
                        }
                    )
                }

                if (whatsAppNumber == null) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        color = InfoSoft
                    ) {
                        Text(
                            "Nomor WhatsApp klinik belum diatur. Admin dapat mengisinya di " +
                                "web admin → Clinic Profile → Telepon.",
                            modifier = Modifier.padding(12.dp),
                            fontSize = 11.sp,
                            lineHeight = 16.sp,
                            color = InfoStrong
                        )
                    }
                }

                if (error != null) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        color = ErrorSoft
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(
                                "Kontak terbaru gagal dimuat. Nomor di atas mungkin sudah lama.",
                                modifier = Modifier.weight(1f),
                                fontSize = 11.sp,
                                lineHeight = 16.sp,
                                color = ErrorStrong
                            )
                            TextButton(onClick = onRetry) {
                                Icon(
                                    Icons.Filled.Refresh,
                                    contentDescription = null,
                                    tint = ErrorStrong,
                                    modifier = Modifier.size(14.dp)
                                )
                                Spacer(Modifier.width(4.dp))
                                Text(
                                    "Muat ulang",
                                    color = ErrorStrong,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }
                    }
                }
            }
        }

        if (showTips) {
            BookingHelpTips()
        }
    }
}

@Composable
private fun BookingHelpTips() {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        color = SurfaceMuted
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Icon(
                    Icons.Filled.HelpOutline,
                    contentDescription = null,
                    tint = TextTertiary,
                    modifier = Modifier.size(16.dp)
                )
                Text(
                    "CARA BOOKING",
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.sp,
                    color = TextTertiary
                )
            }
            HelpTip("Pilih hewan dan layanan, lalu tanggal serta jam konsultasi.")
            HelpTip("Jam yang sudah lewat tidak bisa dipilih; pilih jam berikutnya atau besok.")
            HelpTip("Selesaikan pembayaran agar booking langsung dikonfirmasi klinik.")
        }
    }
}

@Composable
private fun WhatsAppOption(
    phone: String?,
    onClick: () -> Unit
) {
    val enabled = phone != null
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(enabled = enabled, onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        color = if (enabled) Primary else SurfaceMuted,
        border = if (enabled) null else BorderStroke(1.dp, Outline)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .background(
                        color = if (enabled) Color.White.copy(alpha = 0.18f) else OutlineStrong,
                        shape = RoundedCornerShape(12.dp)
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.Chat,
                    contentDescription = null,
                    tint = if (enabled) OnPrimaryStrong else TextTertiary,
                    modifier = Modifier.size(22.dp)
                )
            }
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    "Chat WhatsApp Admin",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (enabled) OnPrimaryStrong else TextSecondary
                )
                Text(
                    phone?.let { "+$it · pesan sudah terisi otomatis" }
                        ?: "Nomor WhatsApp belum tersedia",
                    fontSize = 11.sp,
                    color = if (enabled) OnPrimaryStrong.copy(alpha = 0.8f) else TextTertiary
                )
            }
        }
    }
}

@Composable
private fun ContactShortcut(
    modifier: Modifier,
    icon: ImageVector,
    title: String,
    subtitle: String,
    enabled: Boolean,
    onClick: () -> Unit
) {
    Surface(
        modifier = modifier.clickable(enabled = enabled, onClick = onClick),
        shape = RoundedCornerShape(14.dp),
        color = SurfaceColor,
        border = BorderStroke(1.dp, Outline)
    ) {
        Column(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = if (enabled) Primary else TextTertiary,
                modifier = Modifier.size(18.dp)
            )
            Text(
                title,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = if (enabled) OnBackground else TextTertiary
            )
            Text(
                subtitle,
                fontSize = 10.sp,
                color = TextTertiary,
                maxLines = 1
            )
        }
    }
}

@Composable
private fun LoadingContact() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 18.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        CircularProgressIndicator(
            color = Primary,
            strokeWidth = 2.dp,
            modifier = Modifier.size(20.dp)
        )
        Text(
            "Memuat kontak klinik...",
            fontSize = 12.sp,
            color = TextSecondary
        )
    }
}

@Composable
private fun ContactLoadError(message: String, onRetry: () -> Unit) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = ErrorSoft
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Text(
                "Kontak klinik belum bisa dimuat",
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                color = ErrorStrong
            )
            Text(
                message,
                fontSize = 11.sp,
                lineHeight = 16.sp,
                color = TextSecondary
            )
            TextButton(
                onClick = onRetry,
                contentPadding = PaddingValues(0.dp)
            ) {
                Icon(
                    Icons.Filled.Refresh,
                    contentDescription = null,
                    tint = ErrorStrong,
                    modifier = Modifier.size(15.dp)
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    "Coba lagi",
                    color = ErrorStrong,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@Composable
private fun HelpTip(text: String) {
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Box(
            modifier = Modifier
                .padding(top = 5.dp)
                .size(5.dp)
                .background(Primary, RoundedCornerShape(50))
        )
        Text(
            text,
            fontSize = 11.sp,
            lineHeight = 16.sp,
            color = TextSecondary
        )
    }
}
