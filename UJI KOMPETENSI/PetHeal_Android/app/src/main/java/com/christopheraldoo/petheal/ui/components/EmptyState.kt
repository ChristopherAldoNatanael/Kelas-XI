package com.christopheraldoo.petheal.ui.components

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/**
 * Enhanced empty state component with icon, title, description, and optional action button.
 * Provides better UX than simple text-only empty states.
 */
@Composable
fun EmptyState(
    icon: ImageVector,
    title: String,
    description: String,
    modifier: Modifier = Modifier,
    actionText: String? = null,
    onActionClick: (() -> Unit)? = null,
    iconColor: Color = Color(0xFF2BEE6C),
    backgroundColor: Color = Color(0xFF2BEE6C).copy(alpha = 0.1f)
) {
    val animatedScale = animateFloatAsState(
        targetValue = 1f,
        animationSpec = tween(durationMillis = 600, delayMillis = 100),
        label = "empty_state_scale"
    )

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        // Animated icon
        Box(
            modifier = Modifier
                .size(120.dp)
                .clip(RoundedCornerShape(24.dp))
                .background(backgroundColor)
                .scale(animatedScale.value),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = iconColor,
                modifier = Modifier.size(56.dp)
            )
        }

        Spacer(Modifier.height(24.dp))

        Text(
            text = title,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White,
            textAlign = TextAlign.Center
        )

        Spacer(Modifier.height(8.dp))

        Text(
            text = description,
            fontSize = 14.sp,
            color = Color(0xFF9DB9A6),
            textAlign = TextAlign.Center,
            lineHeight = 20.sp,
            modifier = Modifier.padding(horizontal = 16.dp)
        )

        // Optional action button
        if (actionText != null && onActionClick != null) {
            Spacer(Modifier.height(24.dp))
            Button(
                onClick = onActionClick,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFF2BEE6C),
                    contentColor = Color(0xFF052E14)
                ),
                modifier = Modifier.fillMaxWidth().height(48.dp)
            ) {
                Icon(Icons.Filled.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(actionText, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/**
 * Specific empty state for bookings
 */
@Composable
fun EmptyBookingsState(onBookNow: () -> Unit) {
    EmptyState(
        icon = Icons.Outlined.EventBusy,
        title = "Belum Ada Booking",
        description = "Anda belum memiliki jadwal konsultasi. Buat booking pertama untuk mulai mengelola kunjungan hewan kesayangan Anda.",
        actionText = "Buat Booking",
        onActionClick = onBookNow,
        iconColor = Color(0xFF2BEE6C),
        backgroundColor = Color(0xFF2BEE6C).copy(alpha = 0.1f)
    )
}

/**
 * Specific empty state for pets
 */
@Composable
fun EmptyPetsState(onAddPet: () -> Unit) {
    EmptyState(
        icon = Icons.Outlined.Pets,
        title = "Belum Ada Hewan",
        description = "Tambahkan hewan kesayangan Anda terlebih dahulu agar jadwal, rekam medis, dan vaksinasi bisa dikelola dengan rapi.",
        actionText = "Tambah Hewan Pertama",
        onActionClick = onAddPet,
        iconColor = Color(0xFF9333EA),
        backgroundColor = Color(0xFFF3E8FF)
    )
}

/**
 * Specific empty state for notifications
 */
@Composable
fun EmptyNotificationsState() {
    EmptyState(
        icon = Icons.Outlined.NotificationsNone,
        title = "Belum Ada Notifikasi",
        description = "Saat ini belum ada pembaruan baru. Notifikasi booking, pembayaran, dan pengingat perawatan akan muncul di sini.",
        iconColor = Color(0xFF3B82F6),
        backgroundColor = Color(0xFFDBEAFE)
    )
}

/**
 * Specific empty state for medical records
 */
@Composable
fun EmptyMedicalRecordsState() {
    EmptyState(
        icon = Icons.Outlined.Description,
        title = "Belum Ada Rekam Medis",
        description = "Rekam medis akan tampil di sini setelah hewan Anda menjalani konsultasi, tindakan, atau perawatan.",
        iconColor = Color(0xFFEA580C),
        backgroundColor = Color(0xFFFFEDD5)
    )
}

/**
 * Specific empty state for search results
 */
@Composable
fun EmptySearchState(query: String) {
    EmptyState(
        icon = Icons.Outlined.SearchOff,
        title = "Hasil Tidak Ditemukan",
        description = "Belum ada data yang cocok untuk \"$query\". Coba gunakan kata kunci lain atau kurangi filter pencarian.",
        iconColor = Color(0xFF6B7280),
        backgroundColor = Color(0xFF6B7280).copy(alpha = 0.1f)
    )
}
