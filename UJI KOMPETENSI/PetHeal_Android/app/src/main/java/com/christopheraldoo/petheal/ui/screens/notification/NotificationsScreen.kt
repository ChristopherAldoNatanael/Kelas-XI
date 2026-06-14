package com.christopheraldoo.petheal.ui.screens.notification

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
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
import androidx.compose.material.icons.filled.Alarm
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Vaccines
import androidx.compose.material.icons.outlined.DeleteSweep
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.AppNotification
import com.christopheraldoo.petheal.ui.components.EmptyNotificationsState
import com.christopheraldoo.petheal.ui.components.SkeletonText
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.TimeUnit

private val NPrimary = Color(0xFF18C964)
private val NBg = Color(0xFFF6F8F6)
private val NSurface = Color.White
private val NBorder = Color(0xFFE2E8F0)
private val TextPrimary = Color(0xFF0F172A)
private val TextSecondary = Color(0xFF64748B)
private val NUnread = Color(0xFFEAF9F0)

@Composable
fun NotificationsScreen(
    onNavigateBack: () -> Unit,
    onOpenBooking: (Int) -> Unit = {},
    onOpenMedicalRecord: (Int) -> Unit = {},
    onOpenPet: (Int) -> Unit = {},
    onOpenDoctor: (Int) -> Unit = {},
    onOpenBookings: () -> Unit = {},
    onOpenPets: () -> Unit = {},
    viewModel: NotificationsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var showClearDialog by remember { mutableStateOf(false) }
    val unreadCount = uiState.notifications.count { !it.isRead }

    LaunchedEffect(Unit) { viewModel.markAllRead() }

    if (showClearDialog) {
        AlertDialog(
            onDismissRequest = { showClearDialog = false },
            containerColor = NSurface,
            title = { Text("Clear All Notifications", color = TextPrimary, fontWeight = FontWeight.Bold) },
            text = { Text("This will permanently delete all notifications.", color = TextSecondary, fontSize = 14.sp) },
            confirmButton = {
                TextButton(onClick = {
                    viewModel.clearAll()
                    showClearDialog = false
                }) {
                    Text("Clear", color = Color(0xFFDC2626), fontWeight = FontWeight.SemiBold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showClearDialog = false }) {
                    Text("Cancel", color = TextSecondary)
                }
            }
        )
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(NBg)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(Brush.verticalGradient(listOf(Color.White, Color(0xFFEAF9F0))))
                .padding(top = 44.dp, start = 8.dp, end = 16.dp, bottom = 18.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onNavigateBack) {
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Back", tint = TextPrimary)
                }
                Column(modifier = Modifier.weight(1f)) {
                    Text("Notification Center", color = TextPrimary, fontSize = 19.sp, fontWeight = FontWeight.Bold)
                    Text(
                        text = if (uiState.notifications.isEmpty()) "Tidak ada pembaruan baru" else "${uiState.notifications.size} notifikasi / $unreadCount belum dibaca",
                        color = TextSecondary,
                        fontSize = 12.sp
                    )
                }
                if (uiState.notifications.isNotEmpty()) {
                    IconButton(onClick = { showClearDialog = true }) {
                        Icon(Icons.Outlined.DeleteSweep, contentDescription = "Clear all", tint = TextSecondary)
                    }
                }
            }
            Spacer(Modifier.height(14.dp))
            Row(
                modifier = Modifier.padding(start = 48.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                NotificationMetric("Unread", unreadCount.toString(), NPrimary, Modifier.weight(1f))
                NotificationMetric("Total", uiState.notifications.size.toString(), Color(0xFF0EA5A5), Modifier.weight(1f))
            }
        }

        Divider(color = NBorder, thickness = 0.5.dp)

        when {
            uiState.isLoading -> {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    repeat(5) { NotificationSkeletonCard() }
                }
            }
            uiState.notifications.isEmpty() -> {
                EmptyNotificationsState()
            }
            else -> {
                LazyColumn(
                    contentPadding = PaddingValues(vertical = 14.dp, horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(items = uiState.notifications, key = { it.id }) { notif ->
                        NotificationCard(
                            notification = notif,
                            onClick = {
                                viewModel.markRead(notif.id)
                                when {
                                    notif.medicalRecordId != null -> onOpenMedicalRecord(notif.medicalRecordId)
                                    notif.bookingId != null -> onOpenBooking(notif.bookingId)
                                    notif.petId != null -> onOpenPet(notif.petId)
                                    notif.doctorId != null -> onOpenDoctor(notif.doctorId)
                                    notif.type == "booking_status" || notif.type == "booking_reminder" || notif.type == "payment_reminder" -> onOpenBookings()
                                    notif.type == "vaccination_reminder" -> onOpenPets()
                                }
                            }
                        )
                    }
                    item { Spacer(Modifier.height(80.dp)) }
                }
            }
        }
    }
}

@Composable
private fun NotificationMetric(label: String, value: String, accent: Color, modifier: Modifier = Modifier) {
    Surface(modifier = modifier, shape = RoundedCornerShape(18.dp), color = Color.White) {
        Column(Modifier.padding(horizontal = 14.dp, vertical = 12.dp)) {
            Text(value, color = TextPrimary, fontSize = 20.sp, fontWeight = FontWeight.Bold)
            Text(label, color = accent, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun NotificationCard(notification: AppNotification, onClick: () -> Unit) {
    val bgColor by animateColorAsState(
        targetValue = if (notification.isRead) NSurface else NUnread,
        animationSpec = tween(300),
        label = "notif_bg"
    )

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(bgColor)
            .border(
                width = 1.dp,
                color = if (notification.isRead) NBorder else NPrimary.copy(alpha = 0.35f),
                shape = RoundedCornerShape(20.dp)
            )
            .clickable(onClick = onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.Top
    ) {
        Box(
            modifier = Modifier
                .size(46.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(notificationIconBg(notification.type)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = notificationIcon(notification.type),
                contentDescription = null,
                tint = notificationIconTint(notification.type),
                modifier = Modifier.size(24.dp)
            )
        }

        Spacer(Modifier.width(12.dp))

        Column(modifier = Modifier.weight(1f)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = notification.title,
                    color = TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
                Spacer(Modifier.width(8.dp))
                Text(formatTimestamp(notification.timestamp), color = TextSecondary, fontSize = 11.sp)
            }

            Spacer(Modifier.height(5.dp))

            Text(
                text = notification.body,
                color = TextSecondary,
                fontSize = 13.sp,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis,
                lineHeight = 19.sp
            )

            if (!notification.isRead) {
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(7.dp)
                            .clip(CircleShape)
                            .background(NPrimary)
                    )
                    Spacer(Modifier.width(6.dp))
                    Text("New update", color = NPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun NotificationSkeletonCard() {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .height(96.dp),
        shape = RoundedCornerShape(20.dp),
        color = Color.White
    ) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(44.dp).clip(RoundedCornerShape(16.dp)).background(NBorder))
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                SkeletonText(lines = 1)
                SkeletonText(lines = 2)
            }
        }
    }
}

private fun notificationIcon(type: String): ImageVector = when (type) {
    "booking_status" -> Icons.Filled.EventAvailable
    "booking_reminder" -> Icons.Filled.Alarm
    "vaccination_reminder" -> Icons.Filled.Vaccines
    else -> Icons.Filled.Notifications
}

private fun notificationIconBg(type: String): Color = when (type) {
    "booking_status" -> Color(0xFFEAF9F0)
    "booking_reminder" -> Color(0xFFEFF6FF)
    "vaccination_reminder" -> Color(0xFFF5F3FF)
    else -> Color.White
}

private fun notificationIconTint(type: String): Color = when (type) {
    "booking_status" -> Color(0xFF18C964)
    "booking_reminder" -> Color(0xFF0EA5E9)
    "vaccination_reminder" -> Color(0xFF8B5CF6)
    else -> Color(0xFF64748B)
}

private fun formatTimestamp(epochMs: Long): String {
    val now = System.currentTimeMillis()
    val diff = now - epochMs

    return when {
        diff < TimeUnit.MINUTES.toMillis(1) -> "Just now"
        diff < TimeUnit.HOURS.toMillis(1) -> "${TimeUnit.MILLISECONDS.toMinutes(diff)}m ago"
        diff < TimeUnit.HOURS.toMillis(24) -> "${TimeUnit.MILLISECONDS.toHours(diff)}h ago"
        diff < TimeUnit.DAYS.toMillis(7) -> "${TimeUnit.MILLISECONDS.toDays(diff)}d ago"
        else -> SimpleDateFormat("dd MMM", Locale.getDefault()).format(Date(epochMs))
    }
}
