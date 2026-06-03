package com.christopheraldoo.petheal.ui.components

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

/**
 * Shimmer effect modifier for skeleton loading states.
 * Provides visual feedback during data loading instead of just showing a spinner.
 */
fun Modifier.shimmerEffect(isVisible: Boolean = true): Modifier = composed {
    if (!isVisible) {
        return@composed this
    }

    val transition = rememberInfiniteTransition(label = "shimmer")
    val translateAnim by transition.animateFloat(
        initialValue = -1000f,
        targetValue = 1000f,
        animationSpec = infiniteRepeatable(
            animation = tween(durationMillis = 1200),
            repeatMode = RepeatMode.Restart
        ),
        label = "shimmer_translate"
    )

    val shimmerColors = listOf(
        Color(0xFFE2E8F0).copy(alpha = 0.3f),
        Color(0xFFCBD5E1).copy(alpha = 0.5f),
        Color(0xFFE2E8F0).copy(alpha = 0.3f)
    )

    val brush = Brush.linearGradient(
        colors = shimmerColors,
        start = Offset(x = translateAnim, y = translateAnim),
        end = Offset(x = translateAnim + 500f, y = translateAnim + 500f)
    )

    this.background(brush)
}

/**
 * Skeleton card component that mimics the shape of actual content.
 */
@Composable
fun SkeletonCard(
    modifier: Modifier = Modifier,
    isVisible: Boolean = true
) {
    if (!isVisible) return

    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = Color(0xFF1C2E22)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .shimmerEffect(true)
        )
    }
}

/**
 * Circular skeleton avatar.
 */
@Composable
fun SkeletonAvatar(
    modifier: Modifier = Modifier,
    size: androidx.compose.ui.unit.Dp = 48.dp
) {
    Box(
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(Color(0xFF1C2E22))
            .shimmerEffect(true)
    )
}

/**
 * Skeleton text line that mimics text appearance.
 */
@Composable
fun SkeletonText(
    modifier: Modifier = Modifier,
    lines: Int = 1,
    maxWidth: androidx.compose.ui.unit.Dp = androidx.compose.ui.unit.Dp.Unspecified
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        repeat(lines) { index ->
            val widthFraction = when {
                lines == 1 -> 0.7f
                index == lines - 1 -> 0.5f
                else -> 1f
            }
            Box(
                modifier = Modifier
                    .fillMaxWidth(widthFraction)
                    .height(16.dp)
                    .clip(RoundedCornerShape(4.dp))
                    .background(Color(0xFF1C2E22))
                    .shimmerEffect(true)
            )
        }
    }
}

/**
 * Skeleton booking card that matches the actual booking card layout.
 */
@Composable
fun SkeletonBookingCard() {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .height(180.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color(0xFF1C2E22)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Top row with pet icon and status
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    SkeletonAvatar(size = 36.dp)
                    SkeletonText(modifier = Modifier.width(120.dp))
                }
                Box(
                    modifier = Modifier
                        .width(80.dp)
                        .height(24.dp)
                        .clip(RoundedCornerShape(20.dp))
                        .background(Color(0xFF1C2E22))
                        .shimmerEffect(true)
                )
            }

            // Middle section with doctor info and date
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    SkeletonAvatar(size = 44.dp)
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        SkeletonText(modifier = Modifier.width(100.dp))
                        SkeletonText(modifier = Modifier.width(80.dp))
                    }
                }
                Column(
                    horizontalAlignment = androidx.compose.ui.Alignment.End,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    SkeletonText(modifier = Modifier.width(90.dp))
                    SkeletonText(modifier = Modifier.width(90.dp))
                }
            }

            // Bottom action buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(42.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color(0xFF1C2E22))
                        .shimmerEffect(true)
                )
                Box(
                    modifier = Modifier
                        .size(42.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(Color(0xFF1C2E22))
                        .shimmerEffect(true)
                )
            }
        }
    }
}

/**
 * Skeleton list of booking cards for loading states.
 */
@Composable
fun SkeletonBookingList(count: Int = 3) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        repeat(count) {
            SkeletonBookingCard()
        }
    }
}

/**
 * Skeleton doctor card.
 */
@Composable
fun SkeletonDoctorCard() {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .height(120.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color(0xFF1C2E22)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            SkeletonAvatar(size = 72.dp)
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                SkeletonText(lines = 1, maxWidth = 150.dp)
                SkeletonText(lines = 1, maxWidth = 120.dp)
                SkeletonText(lines = 1, maxWidth = 100.dp)
            }
        }
    }
}

/**
 * Skeleton doctor list.
 */
@Composable
fun SkeletonDoctorList(count: Int = 4) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        repeat(count) {
            SkeletonDoctorCard()
        }
    }
}
