package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.christopheraldoo.petheal.data.model.ClinicCard
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.Success
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.util.DistanceFormatter

/**
 * Kartu klinik untuk discovery. Memakai token desain PetHeal (Surface/Outline/
 * Primary/PetHealRadius) — TIDAK ada hex baru di sini.
 *
 * Aturan tampilan data:
 *  - jarak hanya bila backend mengirim `distanceKm` (lokasi pengguna ada),
 *  - rating hanya bila ada ulasan nyata → selain itu "Belum ada rating",
 *  - status operasional "Buka"/"Tutup" hanya bila jam klinik terisi,
 *    selain itu tidak ditampilkan (tidak menebak).
 */
@Composable
fun ClinicDiscoveryCard(
    clinic: ClinicCard,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    showDistance: Boolean = true
) {
    Surface(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        color = SurfaceColor,
        shape = RoundedCornerShape(PetHealRadius.xl),
        border = BorderStroke(1.dp, Outline)
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.Top
        ) {
            ClinicAvatar(clinic = clinic)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    text = clinic.name.orEmpty(),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    ClinicRating(clinic)
                    if (showDistance && clinic.distanceKm != null) {
                        Spacer(Modifier.width(10.dp))
                        Icon(
                            Icons.Filled.LocationOn,
                            contentDescription = null,
                            tint = TextTertiary,
                            modifier = Modifier.size(13.dp)
                        )
                        Spacer(Modifier.width(2.dp))
                        Text(
                            text = clinic.distanceText
                                ?: DistanceFormatter.format(clinic.distanceKm),
                            fontSize = 12.sp,
                            color = TextSecondary
                        )
                    }
                }

                Spacer(Modifier.height(4.dp))
                Text(
                    text = clinic.address.orEmpty(),
                    fontSize = 12.sp,
                    color = TextSecondary,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )

                val serviceCount = clinic.servicesCount ?: 0
                val doctorCount = clinic.doctorsCount ?: 0
                if (serviceCount > 0 || doctorCount > 0 || clinic.operatingStatus == "open") {
                    Spacer(Modifier.height(8.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        if (clinic.operatingStatus == "open") {
                            OpenBadge(isOpen = true)
                        } else if (clinic.operatingStatus == "closed") {
                            OpenBadge(isOpen = false)
                        }
                        if (serviceCount > 0) {
                            MetaChip("$serviceCount layanan")
                        }
                        if (doctorCount > 0) {
                            MetaChip("$doctorCount dokter")
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun ClinicAvatar(clinic: ClinicCard, size: Int = 56) {
    val shape = RoundedCornerShape(PetHealRadius.md)
    if (!clinic.logoUrl.isNullOrBlank()) {
        AsyncImage(
            model = clinic.logoUrl,
            contentDescription = "Logo ${clinic.name}",
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .size(size.dp)
                .clip(shape)
        )
    } else {
        Surface(
            modifier = Modifier.size(size.dp),
            shape = shape,
            color = Primary.copy(alpha = 0.12f)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth().height(size.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Filled.LocalHospital,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size((size * 0.45f).dp)
                )
            }
        }
    }
}

@Composable
fun ClinicRating(clinic: ClinicCard) {
    val rating = clinic.rating
    if (rating != null && rating > 0.0) {
        Icon(
            Icons.Filled.Star,
            contentDescription = "Rating",
            tint = com.christopheraldoo.petheal.ui.theme.Star,
            modifier = Modifier.size(13.dp)
        )
        Spacer(Modifier.width(3.dp))
        Text(
            text = String.format(java.util.Locale.US, "%.1f", rating),
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface
        )
        val count = clinic.reviewsCount ?: 0
        if (count > 0) {
            Spacer(Modifier.width(2.dp))
            Text(text = "($count)", fontSize = 11.sp, color = TextTertiary)
        }
    } else {
        Text(text = "Belum ada rating", fontSize = 11.sp, color = TextTertiary)
    }
}

@Composable
private fun OpenBadge(isOpen: Boolean) {
    Surface(
        shape = RoundedCornerShape(PetHealRadius.pill),
        color = if (isOpen) SuccessSoft else Color(0xFFFEE2E2),
    ) {
        Text(
            text = if (isOpen) "Buka" else "Tutup",
            fontSize = 10.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isOpen) Success else Color(0xFFB91C1C),
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
        )
    }
}

@Composable
private fun MetaChip(text: String) {
    Surface(
        shape = RoundedCornerShape(PetHealRadius.pill),
        color = Color.Transparent,
        border = BorderStroke(1.dp, Outline)
    ) {
        Text(
            text = text,
            fontSize = 10.sp,
            color = TextSecondary,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
        )
    }
}
