package com.christopheraldoo.petheal.ui.components

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.WifiOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
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

@Composable
fun ErrorState(
    title: String,
    message: String,
    modifier: Modifier = Modifier,
    icon: ImageVector = Icons.Outlined.ErrorOutline,
    iconColor: Color = Color(0xFFEF4444),
    backgroundColor: Color = Color(0xFFEF4444).copy(alpha = 0.10f),
    primaryActionText: String? = null,
    onPrimaryActionClick: (() -> Unit)? = null,
    secondaryActionText: String? = null,
    onSecondaryActionClick: (() -> Unit)? = null
) {
    val animatedScale by animateFloatAsState(
        targetValue = 1f,
        animationSpec = tween(durationMillis = 400),
        label = "error_scale"
    )

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(32.dp)
            .verticalScroll(rememberScrollState()),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(backgroundColor)
                .scale(animatedScale),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = iconColor,
                modifier = Modifier.size(48.dp)
            )
        }

        Spacer(Modifier.height(24.dp))

        Text(
            text = title,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Color(0xFF0F172A),
            textAlign = TextAlign.Center
        )

        Spacer(Modifier.height(8.dp))

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 8.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            shape = RoundedCornerShape(12.dp)
        ) {
            Text(
                text = message,
                fontSize = 14.sp,
                color = Color(0xFF475569),
                textAlign = TextAlign.Center,
                lineHeight = 20.sp,
                modifier = Modifier.padding(16.dp)
            )
        }

        Spacer(Modifier.height(24.dp))

        if (primaryActionText != null && onPrimaryActionClick != null) {
            Button(
                onClick = onPrimaryActionClick,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = iconColor,
                    contentColor = Color.White
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(48.dp)
            ) {
                Icon(Icons.Filled.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(primaryActionText, fontWeight = FontWeight.SemiBold)
            }
            Spacer(Modifier.height(12.dp))
        }

        if (secondaryActionText != null && onSecondaryActionClick != null) {
            OutlinedButton(
                onClick = onSecondaryActionClick,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = Color(0xFF0F172A)
                ),
                border = androidx.compose.foundation.BorderStroke(
                    1.dp,
                    Color(0xFFCBD5E1)
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(48.dp)
            ) {
                Icon(Icons.Filled.ArrowBack, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(secondaryActionText, fontWeight = FontWeight.Medium)
            }
        }
    }
}

@Composable
fun NetworkErrorState(
    onRetry: () -> Unit,
    onGoBack: () -> Unit
) {
    ErrorState(
        title = "Koneksi Bermasalah",
        message = "Aplikasi belum bisa terhubung ke server. Periksa koneksi internet Anda lalu coba lagi.",
        icon = Icons.Outlined.WifiOff,
        primaryActionText = "Coba Lagi",
        onPrimaryActionClick = onRetry,
        secondaryActionText = "Kembali",
        onSecondaryActionClick = onGoBack,
        iconColor = Color(0xFFF59E0B),
        backgroundColor = Color(0xFFF59E0B).copy(alpha = 0.10f)
    )
}

@Composable
fun ServerErrorState(
    errorMessage: String = "Terjadi kendala pada server. Silakan coba beberapa saat lagi.",
    onRetry: (() -> Unit)? = null,
    onGoBack: (() -> Unit)? = null
) {
    ErrorState(
        title = "Server Bermasalah",
        message = errorMessage,
        icon = Icons.Outlined.CloudOff,
        primaryActionText = if (onRetry != null) "Coba Lagi" else null,
        onPrimaryActionClick = onRetry,
        secondaryActionText = if (onGoBack != null) "Kembali" else null,
        onSecondaryActionClick = onGoBack,
        iconColor = Color(0xFFEF4444),
        backgroundColor = Color(0xFFEF4444).copy(alpha = 0.10f)
    )
}

@Composable
fun PermissionErrorState(
    onGrantPermission: () -> Unit,
    onDismiss: () -> Unit
) {
    ErrorState(
        title = "Izin Dibutuhkan",
        message = "Fitur ini memerlukan izin akses perangkat agar dapat berjalan dengan benar. Berikan izin yang diminta untuk melanjutkan.",
        icon = Icons.Filled.Lock,
        primaryActionText = "Berikan Izin",
        onPrimaryActionClick = onGrantPermission,
        secondaryActionText = "Nanti Saja",
        onSecondaryActionClick = onDismiss,
        iconColor = Color(0xFF3B82F6),
        backgroundColor = Color(0xFF3B82F6).copy(alpha = 0.10f)
    )
}
