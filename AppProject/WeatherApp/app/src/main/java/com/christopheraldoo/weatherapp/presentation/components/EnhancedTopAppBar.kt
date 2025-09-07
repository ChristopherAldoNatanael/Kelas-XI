package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.ripple.rememberRipple
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.weatherapp.presentation.theme.GlassWhite
import com.christopheraldoo.weatherapp.presentation.theme.PrimaryBlue
import com.christopheraldoo.weatherapp.presentation.utils.LocalizationHelper
import kotlinx.coroutines.delay

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EnhancedTopAppBar(
    location: String,
    isRefreshing: Boolean = false,
    isFavorite: Boolean = false,
    onSearchClick: () -> Unit,
    onRefreshClick: () -> Unit,
    onFavoritesClick: () -> Unit,
    onToggleFavoriteClick: () -> Unit,
    onSettingsClick: () -> Unit
) {
    var isExpanded by remember { mutableStateOf(false) }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp)
            .animateContentSize(
                animationSpec = spring(
                    dampingRatio = Spring.DampingRatioMediumBouncy,
                    stiffness = Spring.StiffnessLow
                )
            )
            .clickable(
                indication = null,
                interactionSource = remember { MutableInteractionSource() }
            ) { isExpanded = !isExpanded },
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(
            containerColor = GlassWhite
        ),
        elevation = CardDefaults.cardElevation(
            defaultElevation = 12.dp,
            pressedElevation = 8.dp
        )
    ) {
        Column {
            // Main Header Row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Location Info with Icon
                Row(
                    modifier = Modifier.weight(1f),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    AnimatedLocationIcon()
                    
                    Spacer(modifier = Modifier.width(12.dp))
                    
                    Column {
                        Text(
                            text = LocalizationHelper.getCurrentTranslation().currentLocation,
                            style = MaterialTheme.typography.bodySmall,
                            color = Color.White.copy(alpha = 0.95f),
                            fontSize = 12.sp
                        )
                        Text(
                            text = location,
                            style = MaterialTheme.typography.titleMedium,
                            color = Color.White,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 16.sp
                        )
                    }
                }
                
                // Action Buttons
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {                    AnimatedActionButton(
                        icon = Icons.Default.Search,
                        contentDescription = "Search",
                        onClick = onSearchClick
                    )
                    
                    AnimatedActionButton(
                        icon = if (isFavorite) Icons.Default.Favorite else Icons.Default.FavoriteBorder,
                        contentDescription = if (isFavorite) "Remove from favorites" else "Add to favorites",
                        onClick = onToggleFavoriteClick,
                        tint = if (isFavorite) Color.Red else Color.White.copy(alpha = 0.95f)
                    )
                    
                    AnimatedActionButton(
                        icon = Icons.Default.Refresh,
                        contentDescription = "Refresh",
                        onClick = onRefreshClick,
                        isLoading = isRefreshing
                    )
                    
                    // Expand/Collapse Button
                    AnimatedActionButton(
                        icon = if (isExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                        contentDescription = "More options",
                        onClick = { isExpanded = !isExpanded }
                    )
                }
            }
            
            // Expanded Content
            if (isExpanded) {
                Divider(
                    color = Color.White.copy(alpha = 0.3f),
                    thickness = 1.dp,
                    modifier = Modifier.padding(horizontal = 20.dp)
                )
                
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(20.dp),
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    ExpandedActionItem(
                        icon = Icons.Default.Favorite,
                        label = LocalizationHelper.getCurrentTranslation().favorites,
                        onClick = onFavoritesClick
                    )
                    
                    ExpandedActionItem(
                        icon = Icons.Default.Schedule,
                        label = "World Clock",
                        onClick = { /* Navigate to world clock */ }
                    )
                    
                    ExpandedActionItem(
                        icon = Icons.Default.Settings,
                        label = LocalizationHelper.getCurrentTranslation().settings,
                        onClick = onSettingsClick
                    )
                    
                    ExpandedActionItem(
                        icon = Icons.Default.Info,
                        label = "About",
                        onClick = { /* Navigate to about */ }
                    )
                }
            }
        }
    }
}

@Composable
private fun AnimatedLocationIcon() {
    val pulseAnimation = rememberInfiniteTransition(label = "pulse")
    val scale by pulseAnimation.animateFloat(
        initialValue = 1f,
        targetValue = 1.1f,
        animationSpec = infiniteRepeatable(
            animation = tween(2000, easing = EaseInOutSine),
            repeatMode = RepeatMode.Reverse
        ),
        label = "location_pulse"
    )
    
    Box(
        modifier = Modifier
            .size(32.dp)
            .scale(scale)
            .background(
                brush = Brush.radialGradient(
                    colors = listOf(
                        PrimaryBlue.copy(alpha = 0.3f),
                        Color.Transparent
                    )
                ),
                shape = CircleShape
            ),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = Icons.Default.LocationOn,
            contentDescription = "Location",
            tint = Color.White,
            modifier = Modifier.size(20.dp)
        )
    }
}

@Composable
private fun AnimatedActionButton(
    icon: ImageVector,
    contentDescription: String,
    onClick: () -> Unit,
    isLoading: Boolean = false,
    tint: Color = Color.White.copy(alpha = 0.95f)
) {
    var isPressed by remember { mutableStateOf(false) }
    
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.9f else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
        label = "button_scale"
    )
    
    val rotation by animateFloatAsState(
        targetValue = if (isLoading) 360f else 0f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "button_rotation"
    )
    
    Box(
        modifier = Modifier
            .size(44.dp)
            .scale(scale)
            .clip(CircleShape)
            .background(Color.White.copy(alpha = 0.3f))
            .clickable(
                indication = rememberRipple(
                    bounded = true,
                    radius = 22.dp,
                    color = Color.White
                ),
                interactionSource = remember { MutableInteractionSource() },
                onClick = {
                    isPressed = true
                    onClick()
                }
            ),
        contentAlignment = Alignment.Center
    ) {        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            tint = tint,
            modifier = Modifier
                .size(22.dp)
                .rotate(if (isLoading && icon == Icons.Default.Refresh) rotation else 0f)
        )
    }
    
    LaunchedEffect(isPressed) {
        if (isPressed) {
            delay(100)
            isPressed = false
        }
    }
}

@Composable
private fun ExpandedActionItem(
    icon: ImageVector,
    label: String,
    onClick: () -> Unit
) {
    var isPressed by remember { mutableStateOf(false) }
    
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.95f else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
        label = "expanded_item_scale"
    )
    
    Column(
        modifier = Modifier
            .scale(scale)
            .clip(RoundedCornerShape(16.dp))
            .clickable(
                indication = rememberRipple(
                    bounded = true,
                    color = Color.White
                ),
                interactionSource = remember { MutableInteractionSource() },
                onClick = {
                    isPressed = true
                    onClick()
                }
            )
            .padding(12.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Icon(
            imageVector = icon,
            contentDescription = label,
            tint = Color.White,
            modifier = Modifier.size(24.dp)
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = Color.White.copy(alpha = 0.98f),
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium
        )
    }
    
    LaunchedEffect(isPressed) {
        if (isPressed) {
            delay(100)
            isPressed = false
        }
    }
}
