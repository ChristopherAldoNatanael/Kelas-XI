package com.christopheraldoo.weatherapp.presentation.screen

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.weatherapp.data.database.entity.FavoriteLocationEntity
import com.christopheraldoo.weatherapp.domain.model.WeatherResult
import com.christopheraldoo.weatherapp.presentation.components.WeatherParticleBackground
import com.christopheraldoo.weatherapp.presentation.theme.*
import com.christopheraldoo.weatherapp.presentation.viewmodel.WeatherViewModel
import com.christopheraldoo.weatherapp.utils.WeatherUtils
import kotlinx.coroutines.delay

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FavoritesScreen(
    onLocationSelected: (String) -> Unit,
    onBackPressed: () -> Unit,
    viewModel: WeatherViewModel = hiltViewModel()
) {
    val favoriteLocations by viewModel.favoriteLocations.collectAsState()
    val weatherState by viewModel.weatherState.collectAsState()
    
    var selectedLocationForDeletion by remember { mutableStateOf<FavoriteLocationEntity?>(null) }
    var showDeleteDialog by remember { mutableStateOf(false) }
    
    // Animation for the screen
    val slideIn by animateFloatAsState(
        targetValue = 1f,
        animationSpec = tween(800, easing = FastOutSlowInEasing),
        label = "screen_slide_in"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
    ) {
        // Animated background
        WeatherParticleBackground(
            weatherCondition = when (val state = weatherState) {
                is WeatherResult.Success -> state.data.current.condition.code
                else -> 1000
            }
        )
        
        // Gradient overlay
        Box(
            modifier = Modifier
                .fillMaxSize()                .background(
                    brush = WeatherUtils.getWeatherGradient(1000),
                    alpha = 0.8f
                )
        )
        
        Column(
            modifier = Modifier
                .fillMaxSize()
                .alpha(slideIn)
        ) {
            // Top App Bar
            FavoritesTopAppBar(
                onBackPressed = onBackPressed,
                onClearAllClicked = {
                    // TODO: Implement clear all favorites
                }
            )
            
            // Content
            when (val locations = favoriteLocations) {
                is WeatherResult.Loading -> {
                    LoadingFavoritesState()
                }
                is WeatherResult.Success -> {
                    if (locations.data.isEmpty()) {
                        EmptyFavoritesState()
                    } else {
                        FavoriteLocationsList(
                            locations = locations.data,
                            onLocationClick = { location ->
                                onLocationSelected("${location.locationName}, ${location.country}")
                            },
                            onLocationDelete = { location ->
                                selectedLocationForDeletion = location
                                showDeleteDialog = true
                            }
                        )
                    }
                }
                is WeatherResult.Error -> {
                    ErrorFavoritesState(
                        message = locations.message,
                        onRetryClick = {
                            viewModel.loadFavoriteLocations()
                        }
                    )
                }
            }
        }
        
        // Delete confirmation dialog
        if (showDeleteDialog && selectedLocationForDeletion != null) {
            DeleteLocationDialog(
                location = selectedLocationForDeletion!!,
                onConfirm = {
                    viewModel.removeFavoriteLocation(
                        selectedLocationForDeletion!!.locationName,
                        selectedLocationForDeletion!!.country
                    )
                    showDeleteDialog = false
                    selectedLocationForDeletion = null
                },
                onDismiss = {
                    showDeleteDialog = false
                    selectedLocationForDeletion = null
                }
            )
        }
    }
}

@Composable
private fun FavoritesTopAppBar(
    onBackPressed: () -> Unit,
    onClearAllClicked: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = GlassWhite
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(
                    onClick = onBackPressed,
                    modifier = Modifier.size(32.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.ArrowBack,
                        contentDescription = "Back",
                        tint = Color.White,
                        modifier = Modifier.size(24.dp)
                    )
                }
                
                Spacer(modifier = Modifier.width(8.dp))
                
                Text(
                    text = "Lokasi Favorit",
                    style = MaterialTheme.typography.headlineSmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
            }
            
            IconButton(
                onClick = onClearAllClicked,
                modifier = Modifier.size(32.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.ClearAll,
                    contentDescription = "Clear All",
                    tint = Color(0xFFE8E8E8),
                    modifier = Modifier.size(20.dp)
                )
            }
        }
    }
}

@Composable
private fun LoadingFavoritesState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            CircularProgressIndicator(
                color = Color.White,
                strokeWidth = 4.dp,
                modifier = Modifier.size(48.dp)            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = "Memuat lokasi favorit...",
                style = MaterialTheme.typography.bodyLarge,
                color = Color.White.copy(alpha = 0.95f)
            )
        }
    }
}

@Composable
private fun EmptyFavoritesState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {            Icon(
                imageVector = Icons.Default.FavoriteBorder,
                contentDescription = "No favorites",
                tint = Color.White.copy(alpha = 0.85f),
                modifier = Modifier.size(80.dp)
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Belum Ada Lokasi Favorit",
                style = MaterialTheme.typography.headlineSmall,
                color = Color.White,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = "Tambahkan lokasi ke favorit dengan menekan ikon hati di halaman cuaca",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.White.copy(alpha = 0.8f),
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
private fun ErrorFavoritesState(
    message: String,
    onRetryClick: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {
            Icon(
                imageVector = Icons.Default.ErrorOutline,
                contentDescription = "Error",
                tint = Color.White.copy(alpha = 0.6f),
                modifier = Modifier.size(64.dp)
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = "Terjadi Kesalahan",
                style = MaterialTheme.typography.headlineSmall,
                color = Color.White,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                color = Color.White.copy(alpha = 0.8f),
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = onRetryClick,
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color.White.copy(alpha = 0.2f)
                )
            ) {
                Text(
                    text = "Coba Lagi",
                    color = Color.White
                )
            }
        }
    }
}

@Composable
private fun FavoriteLocationsList(
    locations: List<FavoriteLocationEntity>,
    onLocationClick: (FavoriteLocationEntity) -> Unit,
    onLocationDelete: (FavoriteLocationEntity) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        itemsIndexed(locations) { index, location ->
            FavoriteLocationItem(
                location = location,
                isFirst = index == 0,
                onClick = { onLocationClick(location) },
                onDeleteClick = { onLocationDelete(location) }
            )
        }
        
        // Add padding at the bottom
        item {
            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
private fun FavoriteLocationItem(
    location: FavoriteLocationEntity,
    isFirst: Boolean,
    onClick: () -> Unit,
    onDeleteClick: () -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    val animatedHeight by animateDpAsState(
        targetValue = if (expanded) 120.dp else 80.dp,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
        label = "item_height"
    )
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .height(animatedHeight)
            .clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = if (location.isCurrentLocation) {
                GlassWhite.copy(alpha = 0.9f)
            } else {
                GlassWhite
            }
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 6.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = location.locationName,
                        style = MaterialTheme.typography.titleMedium,
                        color = Color.White,
                        fontWeight = FontWeight.Bold
                    )
                    
                    if (location.isCurrentLocation) {
                        Spacer(modifier = Modifier.width(8.dp))
                        Icon(
                            imageVector = Icons.Default.LocationOn,
                            contentDescription = "Current Location",
                            tint = Color.White,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
                
                Text(
                    text = location.country,
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f)
                )
                
                if (expanded) {
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = "Lat: ${String.format("%.2f", location.latitude)}, Lon: ${String.format("%.2f", location.longitude)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                    Text(
                        text = "Timezone: ${location.timezoneId}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                }
            }
            
            Row {
                IconButton(
                    onClick = { expanded = !expanded },
                    modifier = Modifier.size(32.dp)
                ) {
                    Icon(
                        imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                        contentDescription = if (expanded) "Show less" else "Show more",
                        tint = Color.White.copy(alpha = 0.7f),
                        modifier = Modifier.size(20.dp)
                    )
                }
                
                if (!location.isCurrentLocation) {
                    IconButton(
                        onClick = onDeleteClick,
                        modifier = Modifier.size(32.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Delete,
                            contentDescription = "Delete",
                            tint = Color.White.copy(alpha = 0.7f),
                            modifier = Modifier.size(18.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DeleteLocationDialog(
    location: FavoriteLocationEntity,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = GlassDark,
        title = {
            Text(
                text = "Hapus dari Favorit",
                color = Color.White,
                fontWeight = FontWeight.Bold
            )
        },
        text = {
            Text(
                text = "Apakah Anda yakin ingin menghapus ${location.locationName} dari daftar favorit?",
                color = Color.White.copy(alpha = 0.9f)
            )
        },
        confirmButton = {
            TextButton(
                onClick = onConfirm
            ) {
                Text(
                    text = "Hapus",
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
            }
        },
        dismissButton = {
            TextButton(
                onClick = onDismiss
            ) {
                Text(
                    text = "Batal",
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }
    )
}
