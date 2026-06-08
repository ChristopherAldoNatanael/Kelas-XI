package com.christopheraldoo.petheal.ui.screens.notification

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.AppNotification
import java.text.SimpleDateFormat
import java.util.*
import java.util.concurrent.TimeUnit

// ── Colour tokens (match app-wide dark theme) ─────────────────────────────────
private val NPrimary       = Color(0xFF2BEE6C)
private val NPrimaryFg     = Color(0xFF052E14)
private val NBgDark        = Color(0xFFF6F8F6)
private val NSurfaceDark   = Color.White
private val NBorderDark    = Color(0xFFE2E8F0)
private val TextPrimary    = Color(0xFF0F172A)
private val NTextSec       = Color(0xFF64748B)
private val NUnread        = Color(0xFFE8F5E9)    // subtle green tint for unread row

@Composable
fun NotificationsScreen(
    onNavigateBack: () -> Unit,
    viewModel: NotificationsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var showClearDialog by remember { mutableStateOf(false) }

    // Mark all as read when screen is first opened
    LaunchedEffect(Unit) { viewModel.markAllRead() }

    if (showClearDialog) {
        AlertDialog(
            onDismissRequest = { showClearDialog = false },
            containerColor   = NSurfaceDark,
            title = {
                Text("Clear All Notifications",
                    color = TextPrimary, fontWeight = FontWeight.Bold)
            },
            text = {
                Text("This will permanently delete all notifications.",
                    color = NTextSec, fontSize = 14.sp)
            },
            confirmButton = {
                TextButton(onClick = {
                    viewModel.clearAll()
                    showClearDialog = false
                }) {
                    Text("Clear", color = Color(0xFFFF6B6B), fontWeight = FontWeight.SemiBold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showClearDialog = false }) {
                    Text("Cancel", color = NTextSec)
                }
            }
        )
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(NBgDark)
    ) {
        // ── Top bar ───────────────────────────────────────────────────────────
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(NSurfaceDark)
                .padding(top = 44.dp, start = 8.dp, end = 16.dp, bottom = 14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(onClick = onNavigateBack) {
                Icon(Icons.Filled.ArrowBack, contentDescription = "Back", tint = TextPrimary)
            }
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Notifications",
                    color = TextPrimary,
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold
                )
                if (uiState.notifications.isNotEmpty()) {
                    Text(
                        text = "${uiState.notifications.size} notification${if (uiState.notifications.size != 1) "s" else ""}",
                        color = NTextSec,
                        fontSize = 12.sp
                    )
                }
            }
            // Clear all button — only show when there are notifications
            if (uiState.notifications.isNotEmpty()) {
                IconButton(onClick = { showClearDialog = true }) {
                    Icon(
                        Icons.Outlined.DeleteSweep,
                        contentDescription = "Clear all",
                        tint = NTextSec
                    )
                }
            }
        }

        Divider(color = NBorderDark, thickness = 0.5.dp)

        // ── Body ──────────────────────────────────────────────────────────────
        when {
            uiState.isLoading -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = NPrimary)
                }
            }
            uiState.notifications.isEmpty() -> {
                EmptyNotificationsState()
            }
            else -> {
                LazyColumn(
                    contentPadding = PaddingValues(vertical = 12.dp, horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(
                        items = uiState.notifications,
                        key   = { it.id }
                    ) { notif ->
                        NotificationCard(
                            notification = notif,
                            onClick      = { viewModel.markRead(notif.id) }
                        )
                    }
                    // Bottom spacer for nav bar clearance
                    item { Spacer(Modifier.height(80.dp)) }
                }
            }
        }
    }
}

// ── Notification card ─────────────────────────────────────────────────────────

@Composable
private fun NotificationCard(
    notification: AppNotification,
    onClick: () -> Unit
) {
    val bgColor by animateColorAsState(
        targetValue = if (notification.isRead) NSurfaceDark else NUnread,
        animationSpec = tween(300),
        label = "notif_bg"
    )

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(bgColor)
            .border(
                width = 1.dp,
                color = if (notification.isRead) NBorderDark else NPrimary.copy(alpha = 0.35f),
                shape = RoundedCornerShape(14.dp)
            )
            .clickable(onClick = onClick)
            .padding(14.dp),
        verticalAlignment = Alignment.Top
    ) {
        // Icon badge
        Box(
            modifier = Modifier
                .size(42.dp)
                .clip(CircleShape)
                .background(notificationIconBg(notification.type)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = notificationIcon(notification.type),
                contentDescription = null,
                tint   = notificationIconTint(notification.type),
                modifier = Modifier.size(22.dp)
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
                    text     = notification.title,
                    color    = TextPrimary,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text     = formatTimestamp(notification.timestamp),
                    color    = NTextSec,
                    fontSize = 11.sp
                )
            }

            Spacer(Modifier.height(4.dp))

            Text(
                text     = notification.body,
                color    = NTextSec,
                fontSize = 13.sp,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis,
                lineHeight = 18.sp
            )

            // Unread dot
            if (!notification.isRead) {
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(6.dp)
                            .clip(CircleShape)
                            .background(NPrimary)
                    )
                    Spacer(Modifier.width(5.dp))
                    Text("New", color = NPrimary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                }
            }
        }
    }
}

// ── Empty state ───────────────────────────────────────────────────────────────

@Composable
private fun EmptyNotificationsState() {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(80.dp)
                    .clip(CircleShape)
                    .background(NSurfaceDark)
                    .border(1.dp, NBorderDark, CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Outlined.NotificationsNone,
                    contentDescription = null,
                    tint     = NTextSec,
                    modifier = Modifier.size(40.dp)
                )
            }
            Spacer(Modifier.height(20.dp))
            Text(
                text       = "No notifications yet",
                color      = TextPrimary,
                fontSize   = 17.sp,
                fontWeight = FontWeight.SemiBold
            )
            Spacer(Modifier.height(8.dp))
            Text(
                text      = "You'll see booking updates, appointment\nreminders, and more here.",
                color     = NTextSec,
                fontSize  = 13.sp,
                textAlign = TextAlign.Center,
                lineHeight = 19.sp
            )
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

private fun notificationIcon(type: String): ImageVector = when (type) {
    "booking_status"       -> Icons.Filled.EventAvailable
    "booking_reminder"     -> Icons.Filled.Alarm
    "vaccination_reminder" -> Icons.Filled.Vaccines
    else                   -> Icons.Filled.Notifications
}

private fun notificationIconBg(type: String): Color = when (type) {
    "booking_status"       -> Color(0xFFE8F5E9)
    "booking_reminder"     -> Color(0xFFEFF6FF)
    "vaccination_reminder" -> Color(0xFFF5F3FF)
    else                   -> Color.White
}

private fun notificationIconTint(type: String): Color = when (type) {
    "booking_status"       -> Color(0xFF2BEE6C)
    "booking_reminder"     -> Color(0xFF4FC3F7)
    "vaccination_reminder" -> Color(0xFFCE93D8)
    else                   -> Color(0xFF64748B)
}

private fun formatTimestamp(epochMs: Long): String {
    val now  = System.currentTimeMillis()
    val diff = now - epochMs

    return when {
        diff < TimeUnit.MINUTES.toMillis(1)  -> "Just now"
        diff < TimeUnit.HOURS.toMillis(1)    -> "${TimeUnit.MILLISECONDS.toMinutes(diff)}m ago"
        diff < TimeUnit.HOURS.toMillis(24)   -> "${TimeUnit.MILLISECONDS.toHours(diff)}h ago"
        diff < TimeUnit.DAYS.toMillis(7)     -> "${TimeUnit.MILLISECONDS.toDays(diff)}d ago"
        else -> {
            val sdf = SimpleDateFormat("dd MMM", Locale.getDefault())
            sdf.format(Date(epochMs))
        }
    }
}
