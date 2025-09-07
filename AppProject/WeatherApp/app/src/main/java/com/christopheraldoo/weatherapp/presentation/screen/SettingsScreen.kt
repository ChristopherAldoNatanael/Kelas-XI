package com.christopheraldoo.weatherapp.presentation.screen

import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.ripple.rememberRipple
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.weatherapp.data.datastore.Language
import com.christopheraldoo.weatherapp.data.datastore.ThemeMode
import com.christopheraldoo.weatherapp.presentation.components.WeatherParticleBackground
import com.christopheraldoo.weatherapp.presentation.theme.*
import com.christopheraldoo.weatherapp.presentation.utils.LocalizationHelper
import com.christopheraldoo.weatherapp.presentation.viewmodel.SettingsViewModel

@Composable
fun SettingsScreen(
    onBackClick: () -> Unit,
    viewModel: SettingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val scrollState = rememberScrollState()
    
    Box(
        modifier = Modifier.fillMaxSize()
    ) {
        // Animated background
        WeatherParticleBackground(
            weatherCondition = 1000, // Clear sky for settings
            modifier = Modifier.fillMaxSize()
        )
        
        // Background gradient
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(
                            PrimaryBlue.copy(alpha = 0.8f),
                            SecondaryBlue.copy(alpha = 0.9f),
                            TertiaryBlue
                        )
                    )
                )
        )
        
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
        ) {
            // Top App Bar
            SettingsTopAppBar(onBackClick = onBackClick)
            
            // Settings Content
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Language Settings
                SettingsCard(
                    title = LocalizationHelper.getCurrentTranslation().language,
                    icon = Icons.Default.Language
                ) {
                    LanguageSelector(
                        currentLanguage = uiState.selectedLanguage,
                        onLanguageSelected = { viewModel.updateLanguage(it) }
                    )
                }
                
                // Theme Settings
                SettingsCard(
                    title = "Theme",
                    icon = Icons.Default.Palette
                ) {
                    ThemeSelector(
                        currentTheme = uiState.selectedTheme,
                        onThemeSelected = { viewModel.updateTheme(it) }
                    )
                }
                
                // Notification Settings
                SettingsCard(
                    title = "Notifications",
                    icon = Icons.Default.Notifications
                ) {
                    NotificationSettings(
                        isEnabled = uiState.notificationsEnabled,
                        onToggle = { viewModel.toggleNotifications(it) }
                    )
                }
                
                // Units Settings
                SettingsCard(
                    title = "Units",
                    icon = Icons.Default.Thermostat
                ) {
                    UnitsSettings(
                        temperatureUnit = uiState.temperatureUnit,
                        windSpeedUnit = uiState.windSpeedUnit,
                        onTemperatureUnitChanged = { viewModel.updateTemperatureUnit(it) },
                        onWindSpeedUnitChanged = { viewModel.updateWindSpeedUnit(it) }
                    )
                }
                
                // About Section
                SettingsCard(
                    title = "About",
                    icon = Icons.Default.Info
                ) {
                    AboutSection()
                }
                
                Spacer(modifier = Modifier.height(100.dp))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SettingsTopAppBar(onBackClick: () -> Unit) {
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
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(
                onClick = onBackClick,
                modifier = Modifier
                    .clip(CircleShape)
                    .background(Color.White.copy(alpha = 0.3f))
            ) {
                Icon(
                    imageVector = Icons.Default.ArrowBack,
                    contentDescription = "Back",
                    tint = Color.White
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Text(
                text = LocalizationHelper.getCurrentTranslation().settings,
                style = MaterialTheme.typography.titleLarge,
                color = Color.White,
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SettingsCard(
    title: String,
    icon: ImageVector,
    content: @Composable () -> Unit
) {
    var isExpanded by remember { mutableStateOf(false) }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .animateContentSize(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = GlassWhite
        )
    ) {
        Column {
            // Header
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable(
                        indication = rememberRipple(),
                        interactionSource = remember { MutableInteractionSource() }
                    ) { isExpanded = !isExpanded }
                    .padding(20.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = title,
                    tint = Color.White,
                    modifier = Modifier.size(24.dp)
                )
                
                Spacer(modifier = Modifier.width(16.dp))
                
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f)
                )
                
                Icon(
                    imageVector = if (isExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                    contentDescription = "Expand",
                    tint = Color.White.copy(alpha = 0.9f)
                )
            }
            
            // Content
            if (isExpanded) {
                Divider(
                    color = Color.White.copy(alpha = 0.3f),
                    thickness = 1.dp,
                    modifier = Modifier.padding(horizontal = 20.dp)
                )
                
                Box(
                    modifier = Modifier.padding(20.dp)
                ) {
                    content()
                }
            }
        }
    }
}

@Composable
private fun LanguageSelector(
    currentLanguage: Language,
    onLanguageSelected: (Language) -> Unit
) {
    Column {
        LanguageOption(
            language = Language.ENGLISH,
            displayName = "English",
            flag = "🇺🇸",
            isSelected = currentLanguage == Language.ENGLISH,
            onClick = { onLanguageSelected(Language.ENGLISH) }
        )
        
        Spacer(modifier = Modifier.height(12.dp))
        
        LanguageOption(
            language = Language.INDONESIAN,
            displayName = "Bahasa Indonesia",
            flag = "🇮🇩",
            isSelected = currentLanguage == Language.INDONESIAN,
            onClick = { onLanguageSelected(Language.INDONESIAN) }
        )
    }
}

@Composable
private fun LanguageOption(
    language: Language,
    displayName: String,
    flag: String,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    val scale by animateFloatAsState(
        targetValue = if (isSelected) 1.05f else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
        label = "language_scale"
    )
    
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .scale(scale)
            .clip(RoundedCornerShape(16.dp))
            .background(
                if (isSelected) Color.White.copy(alpha = 0.3f)
                else Color.White.copy(alpha = 0.1f)
            )
            .clickable(
                indication = rememberRipple(),
                interactionSource = remember { MutableInteractionSource() }
            ) { onClick() }
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = flag,
            style = MaterialTheme.typography.headlineMedium,
            fontSize = 24.sp
        )
        
        Spacer(modifier = Modifier.width(16.dp))
        
        Text(
            text = displayName,
            style = MaterialTheme.typography.bodyLarge,
            color = Color.White,
            fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal,
            modifier = Modifier.weight(1f)
        )
        
        if (isSelected) {
            Icon(
                imageVector = Icons.Default.Check,
                contentDescription = "Selected",
                tint = SunnyYellow,
                modifier = Modifier.size(24.dp)
            )
        }
    }
}

@Composable
private fun ThemeSelector(
    currentTheme: ThemeMode,
    onThemeSelected: (ThemeMode) -> Unit
) {
    Column {
        ThemeOption(
            theme = ThemeMode.SYSTEM,
            displayName = "System Default",
            icon = Icons.Default.Smartphone,
            isSelected = currentTheme == ThemeMode.SYSTEM,
            onClick = { onThemeSelected(ThemeMode.SYSTEM) }
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        ThemeOption(
            theme = ThemeMode.LIGHT,
            displayName = "Light Mode",
            icon = Icons.Default.LightMode,
            isSelected = currentTheme == ThemeMode.LIGHT,
            onClick = { onThemeSelected(ThemeMode.LIGHT) }
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        ThemeOption(
            theme = ThemeMode.DARK,
            displayName = "Dark Mode",
            icon = Icons.Default.DarkMode,
            isSelected = currentTheme == ThemeMode.DARK,
            onClick = { onThemeSelected(ThemeMode.DARK) }
        )
    }
}

@Composable
private fun ThemeOption(
    theme: ThemeMode,
    displayName: String,
    icon: ImageVector,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(
                if (isSelected) Color.White.copy(alpha = 0.2f)
                else Color.White.copy(alpha = 0.05f)
            )
            .clickable(
                indication = rememberRipple(),
                interactionSource = remember { MutableInteractionSource() }
            ) { onClick() }
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = icon,
            contentDescription = displayName,
            tint = Color.White,
            modifier = Modifier.size(20.dp)
        )
        
        Spacer(modifier = Modifier.width(12.dp))
        
        Text(
            text = displayName,
            style = MaterialTheme.typography.bodyMedium,
            color = Color.White,
            modifier = Modifier.weight(1f)
        )
        
        RadioButton(
            selected = isSelected,
            onClick = onClick,
            colors = RadioButtonDefaults.colors(
                selectedColor = SunnyYellow,
                unselectedColor = Color.White.copy(alpha = 0.6f)
            )
        )
    }
}

@Composable
private fun NotificationSettings(
    isEnabled: Boolean,
    onToggle: (Boolean) -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(
            modifier = Modifier.weight(1f)
        ) {
            Text(
                text = "Weather Alerts",
                style = MaterialTheme.typography.bodyLarge,
                color = Color.White,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = "Get notified about severe weather conditions",
                style = MaterialTheme.typography.bodySmall,
                color = Color.White.copy(alpha = 0.8f)
            )
        }
        
        Switch(
            checked = isEnabled,
            onCheckedChange = onToggle,
            colors = SwitchDefaults.colors(
                checkedThumbColor = SunnyYellow,
                checkedTrackColor = SunnyYellow.copy(alpha = 0.3f),
                uncheckedThumbColor = Color.White,
                uncheckedTrackColor = Color.White.copy(alpha = 0.3f)
            )
        )
    }
}

@Composable
private fun UnitsSettings(
    temperatureUnit: String,
    windSpeedUnit: String,
    onTemperatureUnitChanged: (String) -> Unit,
    onWindSpeedUnitChanged: (String) -> Unit
) {
    Column {
        Text(
            text = "Temperature",
            style = MaterialTheme.typography.bodyMedium,
            color = Color.White,
            fontWeight = FontWeight.Medium
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Row {
            FilterChip(
                onClick = { onTemperatureUnitChanged("C") },
                label = { Text("Celsius") },
                selected = temperatureUnit == "C",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = SunnyYellow.copy(alpha = 0.3f),
                    selectedLabelColor = Color.White
                )
            )
            
            Spacer(modifier = Modifier.width(8.dp))
            
            FilterChip(
                onClick = { onTemperatureUnitChanged("F") },
                label = { Text("Fahrenheit") },
                selected = temperatureUnit == "F",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = SunnyYellow.copy(alpha = 0.3f),
                    selectedLabelColor = Color.White
                )
            )
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Text(
            text = "Wind Speed",
            style = MaterialTheme.typography.bodyMedium,
            color = Color.White,
            fontWeight = FontWeight.Medium
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Row {
            FilterChip(
                onClick = { onWindSpeedUnitChanged("km/h") },
                label = { Text("km/h") },
                selected = windSpeedUnit == "km/h",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = SunnyYellow.copy(alpha = 0.3f),
                    selectedLabelColor = Color.White
                )
            )
            
            Spacer(modifier = Modifier.width(8.dp))
            
            FilterChip(
                onClick = { onWindSpeedUnitChanged("mph") },
                label = { Text("mph") },
                selected = windSpeedUnit == "mph",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = SunnyYellow.copy(alpha = 0.3f),
                    selectedLabelColor = Color.White
                )
            )
        }
    }
}

@Composable
private fun AboutSection() {
    Column {
        Text(
            text = "Weather Matters",
            style = MaterialTheme.typography.titleMedium,
            color = Color.White,
            fontWeight = FontWeight.Bold
        )
        
        Spacer(modifier = Modifier.height(4.dp))
        
        Text(
            text = "Version 1.0.0",
            style = MaterialTheme.typography.bodySmall,
            color = Color.White.copy(alpha = 0.8f)
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Text(
            text = "A beautiful, cinematic weather app built with love using Jetpack Compose and modern Android development practices.",
            style = MaterialTheme.typography.bodyMedium,
            color = Color.White.copy(alpha = 0.9f),
            lineHeight = 20.sp
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Row(
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            OutlinedButton(
                onClick = { /* Open privacy policy */ },
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = Color.White
                )
            ) {
                Text("Privacy Policy")
            }
            
            OutlinedButton(
                onClick = { /* Open terms */ },
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = Color.White
                )
            ) {
                Text("Terms of Service")
            }
        }
    }
}
