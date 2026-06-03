package com.christopheraldoo.petheal.ui.components

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
 * Enhanced error state component with icon, title, message, and recovery actions.
 * Provides better UX than simple text-only error messages.
 */
@Composable
fun ErrorState(
    title: String,
    message: String,
    modifier: Modifier = Modifier,
    icon: ImageVector = Icons.Outlined.ErrorOutline,
    iconColor: Color = Color(0xFFEF4444),
    backgroundColor: Color = Color(0xFFEF4444).copy(alpha = 0.1f),
    primaryActionText: String? = null,
    onPrimaryActionClick: (() -> Unit)? = null,
    secondaryActionText: String? = null,
    onSecondaryActionClick: (() -> Unit)? = null
) {
    val animatedScale = animateFloatAsState(
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
        // Error icon with animation
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(backgroundColor)
                .scale(animatedScale.value),
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
            color = Color.White,
            textAlign = TextAlign.Center
        )

        Spacer(Modifier.height(8.dp))

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 8.dp),
            colors = CardDefaults.cardColors(
                containerColor = Color(0xFF3A1C1C)
            ),
            shape = RoundedCornerShape(12.dp)
        ) {
            Text(
                text = message,
                fontSize = 14.sp,
                color = Color(0xFFFF9999),
                textAlign = TextAlign.Center,
                lineHeight = 20.sp,
                modifier = Modifier.padding(16.dp)
            )
        }

        Spacer(Modifier.height(24.dp))

        // Primary action button
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

        // Secondary action button
        if (secondaryActionText != null && onSecondaryActionClick != null) {
            OutlinedButton(
                onClick = onSecondaryActionClick,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = Color(0xFF9DB9A6)
                ),
                border = androidx.compose.foundation.BorderStroke(
                    1.dp,
                    Color(0xFF2E4536)
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

/**
 * Network error state with retry action.
 */
@Composable
fun NetworkErrorState(
    onRetry: () -> Unit,
    onGoBack: () -> Unit
) {
    ErrorState(
        title = "Connection Issue",
        message = "Unable to connect to the server. Please check your internet connection and try again.",
        icon = Icons.Outlined.WifiOff,
        primaryActionText = "Try Again",
        onPrimaryActionClick = onRetry,
        secondaryActionText = "Go Back",
        onSecondaryActionClick = onGoBack,
        iconColor = Color(0xFFF59E0B),
        backgroundColor = Color(0xFFF59E0B).copy(alpha = 0.1f)
    )
}

/**
 * Generic server error state.
 */
@Composable
fun ServerErrorState(
    errorMessage: String = "Something went wrong on our end. Please try again later.",
    onRetry: (() -> Unit)? = null,
    onGoBack: (() -> Unit)? = null
) {
    ErrorState(
        title = "Server Error",
        message = errorMessage,
        icon = Icons.Outlined.CloudOff,
        primaryActionText = if (onRetry != null) "Try Again" else null,
        onPrimaryActionClick = onRetry,
        secondaryActionText = if (onGoBack != null) "Go Back" else null,
        onSecondaryActionClick = onGoBack,
        iconColor = Color(0xFFEF4444),
        backgroundColor = Color(0xFFEF4444).copy(alpha = 0.1f)
    )
}

/**
 * Permission denied error state.
 */
@Composable
fun PermissionErrorState(
    onGrantPermission: () -> Unit,
    onDismiss: () -> Unit
) {
    ErrorState(
        title = "Permission Required",
        message = "This feature requires permission to access your device's resources. Please grant the necessary permissions to continue.",
        icon = Icons.Filled.Lock,
        primaryActionText = "Grant Permission",
        onPrimaryActionClick = onGrantPermission,
        secondaryActionText = "Maybe Later",
        onSecondaryActionClick = onDismiss,
        iconColor = Color(0xFF3B82F6),
        backgroundColor = Color(0xFF3B82F6).copy(alpha = 0.1f)
    )
}
