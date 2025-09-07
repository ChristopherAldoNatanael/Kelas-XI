package com.christopheraldoo.weatherapp.presentation.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.christopheraldoo.weatherapp.data.model.SearchLocation
import com.christopheraldoo.weatherapp.domain.model.WeatherResult
import com.christopheraldoo.weatherapp.presentation.theme.*
import com.christopheraldoo.weatherapp.presentation.viewmodel.WeatherViewModel
import kotlinx.coroutines.delay

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SearchOverlay(
    viewModel: WeatherViewModel,
    onDismiss: () -> Unit
) {
    val searchResults by viewModel.searchResults.collectAsState()
    var searchQuery by remember { mutableStateOf("") }
    val keyboardController = LocalSoftwareKeyboardController.current
    val focusRequester = remember { FocusRequester() }
      // Search with real-time debounce for API calls
    LaunchedEffect(searchQuery) {
        delay(300) // Reduced delay for faster search
        if (searchQuery.isNotBlank() && searchQuery.length >= 2) {
            viewModel.searchLocations(searchQuery)
        } else if (searchQuery.isBlank()) {
            viewModel.searchLocations("") // Clear results when query is empty
        }
    }
      // Auto-focus search input
    LaunchedEffect(Unit) {
        delay(100)
        focusRequester.requestFocus()
    }
      Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MigrationHelper.CommonUI.getOverlayBackground())
    ) {
        // Background clickable area (only empty space)
        Box(
            modifier = Modifier
                .fillMaxSize()
                .clickable { onDismiss() }
        )
        
        // Content area (prevents click through)
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp, vertical = 24.dp)
        ) {
            Spacer(modifier = Modifier.height(16.dp))
            
            // Simple Search Header
            SearchHeader(
                searchQuery = searchQuery,
                onSearchQueryChange = { searchQuery = it },
                onDismiss = onDismiss,
                focusRequester = focusRequester
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Search Results
            SearchResults(
                searchResults = searchResults,
                searchQuery = searchQuery,
                onLocationSelected = { location ->
                    viewModel.selectLocation(location)
                    keyboardController?.hide()
                    onDismiss()
                }
            )
        }
    }
}

@Composable
private fun SearchHeader(
    searchQuery: String,
    onSearchQueryChange: (String) -> Unit,
    onDismiss: () -> Unit,
    focusRequester: FocusRequester
) {    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(AppResources.Dimensions.cardCornerRadius),
        colors = CardDefaults.cardColors(
            containerColor = MigrationHelper.CommonUI.getTransparentCardBackground()
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = AppResources.Dimensions.cardElevation)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {            Icon(
                imageVector = Icons.Default.Search,
                contentDescription = AppResources.Strings.search,
                tint = MigrationHelper.SearchComponents.getIconColor(),
                modifier = Modifier.size(AppResources.Dimensions.iconSizeSmall)
            )
            
            Spacer(modifier = Modifier.width(AppResources.Dimensions.spacingMedium))
              OutlinedTextField(
                value = searchQuery,
                onValueChange = onSearchQueryChange,                placeholder = {
                    Text(
                        text = MigrationHelper.SearchComponents.getPlaceholderText(),
                        color = MigrationHelper.SearchComponents.getTextColor(),
                        style = MaterialTheme.typography.bodyMedium
                    )
                },                colors = OutlinedTextFieldDefaults.colors(
                    focusedTextColor = MigrationHelper.SearchComponents.getTextColor(),
                    unfocusedTextColor = MigrationHelper.SearchComponents.getTextColor(),
                    focusedContainerColor = Color.Transparent,
                    unfocusedContainerColor = Color.Transparent,
                    focusedBorderColor = MigrationHelper.SearchComponents.getBorderColor(focused = true),
                    unfocusedBorderColor = MigrationHelper.SearchComponents.getBorderColor(focused = false),
                    cursorColor = MigrationHelper.SearchComponents.getTextColor(),
                    focusedPlaceholderColor = MigrationHelper.SearchComponents.getPlaceholderColor(focused = true),
                    unfocusedPlaceholderColor = MigrationHelper.SearchComponents.getPlaceholderColor(focused = false)
                ),
                textStyle = MaterialTheme.typography.bodyMedium,
                singleLine = true,
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                keyboardActions = KeyboardActions(
                    onSearch = {
                        // Handle search action if needed
                    }                ),
                modifier = Modifier
                    .weight(1f)
                    .focusRequester(focusRequester)
            )
            
            if (searchQuery.isNotEmpty()) {
                IconButton(onClick = { onSearchQueryChange("") }) {                    Icon(
                        imageVector = Icons.Default.Clear,
                        contentDescription = AppResources.Strings.clear,
                        tint = MigrationHelper.SearchComponents.getIconColor(),
                        modifier = Modifier.size(AppResources.Dimensions.iconSizeSmall)
                    )
                }
            }
            
            IconButton(onClick = onDismiss) {                Icon(
                    imageVector = Icons.Default.Close,
                    contentDescription = AppResources.Strings.close,
                    tint = MigrationHelper.SearchComponents.getIconColor(),
                    modifier = Modifier.size(AppResources.Dimensions.iconSizeSmall)
                )
            }
        }
    }
}

@Composable
private fun SearchResults(
    searchResults: WeatherResult<List<SearchLocation>>,
    searchQuery: String,
    onLocationSelected: (String) -> Unit
) {    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(AppResources.Dimensions.cardCornerRadius),
        colors = CardDefaults.cardColors(
            containerColor = MigrationHelper.CommonUI.getTransparentCardBackground()
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = AppResources.Dimensions.cardElevation)
    ) {
        when (val result = searchResults) {
            is WeatherResult.Loading -> {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(200.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {                        CircularProgressIndicator(
                            color = MigrationHelper.SearchComponents.getTextColor(),
                            strokeWidth = 3.dp,
                            modifier = Modifier.size(AppResources.Dimensions.iconSizeMedium)
                        )
                        Spacer(modifier = Modifier.height(AppResources.Dimensions.spacingMedium))
                        Text(
                            text = AppResources.Strings.searching,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MigrationHelper.SearchComponents.getTextColor()
                        )
                    }
                }
            }
            
            is WeatherResult.Success -> {
                if (searchQuery.isBlank()) {
                    PopularCities(onLocationSelected = onLocationSelected)
                } else if (result.data.isEmpty()) {
                    EmptyResults(query = searchQuery)
                } else {
                    SearchResultsList(
                        locations = result.data,
                        onLocationSelected = onLocationSelected
                    )
                }
            }
            
            is WeatherResult.Error -> {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(200.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {                        Icon(
                            imageVector = Icons.Default.ErrorOutline,
                            contentDescription = AppResources.Strings.error,
                            tint = MigrationHelper.SearchComponents.getIconColor(),
                            modifier = Modifier.size(AppResources.Dimensions.iconSizeLarge)
                        )
                        Spacer(modifier = Modifier.height(AppResources.Dimensions.spacingMedium))
                        Text(
                            text = AppResources.Strings.error_occurred,
                            style = MaterialTheme.typography.titleSmall,
                            color = MigrationHelper.SearchComponents.getTextColor(),
                            fontWeight = FontWeight.SemiBold
                        )
                        Text(
                            text = result.message,
                            style = MaterialTheme.typography.bodySmall,
                            color = MigrationHelper.SearchComponents.getSecondaryTextColor(),
                            textAlign = TextAlign.Center
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun PopularCities(onLocationSelected: (String) -> Unit) {
    val popularCities = listOf(
        "Jakarta, Indonesia",
        "New York, USA", 
        "London, UK",
        "Tokyo, Japan",
        "Paris, France",
        "Sydney, Australia",
        "Dubai, UAE",
        "Singapore, Singapore"
    )
    
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(20.dp)
    ) {        Text(
            text = AppResources.Strings.popular_cities,
            style = MaterialTheme.typography.titleMedium,
            color = MigrationHelper.SearchComponents.getTextColor(),
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = AppResources.Dimensions.spacingMedium)
        )
          LazyColumn(
            modifier = Modifier.heightIn(max = 300.dp),
            verticalArrangement = Arrangement.spacedBy(AppResources.Dimensions.spacingSmall)
        ) {            items(popularCities) { city ->
                Surface(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { onLocationSelected(city) },
                    shape = RoundedCornerShape(AppResources.Dimensions.cardCornerRadiusSmall),
                    color = MigrationHelper.CommonUI.getGlassEffect()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {                        Text(
                            text = city,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MigrationHelper.SearchComponents.getTextColor(),
                            modifier = Modifier.weight(1f)
                        )
                        Icon(
                            imageVector = Icons.Default.ChevronRight,
                            contentDescription = AppResources.Strings.select,
                            tint = MigrationHelper.SearchComponents.getSecondaryTextColor(),
                            modifier = Modifier.size(AppResources.Dimensions.iconSizeSmall)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun EmptyResults(query: String) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(40.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {        Icon(
            imageVector = Icons.Default.LocationOff,
            contentDescription = AppResources.Strings.no_results,
            tint = MigrationHelper.SearchComponents.getIconColor(),
            modifier = Modifier.size(AppResources.Dimensions.iconSizeXLarge)
        )
        
        Spacer(modifier = Modifier.height(AppResources.Dimensions.spacingLarge))
        
        Text(
            text = AppResources.Strings.no_results_for(query),
            style = MaterialTheme.typography.titleMedium,
            color = MigrationHelper.SearchComponents.getTextColor(),
            fontWeight = FontWeight.SemiBold,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(AppResources.Dimensions.spacingSmall))
        
        Text(
            text = AppResources.Strings.try_different_keyword,
            style = MaterialTheme.typography.bodyMedium,
            color = MigrationHelper.SearchComponents.getSecondaryTextColor(),
            textAlign = TextAlign.Center
        )
    }
}

@Composable
private fun SearchResultsList(
    locations: List<SearchLocation>,
    onLocationSelected: (String) -> Unit
) {
    LazyColumn(
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(max = 400.dp)
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(AppResources.Dimensions.spacingSmall)
    ) {
        items(locations) { location ->
            SearchResultItem(
                location = location,
                onClick = {
                    onLocationSelected("${location.name}, ${location.country}")
                }
            )
        }
    }
}

@Composable
private fun SearchResultItem(
    location: SearchLocation,
    onClick: () -> Unit
) {    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(AppResources.Dimensions.cardCornerRadiusSmall),
        color = MigrationHelper.CommonUI.getGlassEffect()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {                Text(
                    text = location.name,
                    style = MaterialTheme.typography.titleSmall,
                    color = MigrationHelper.SearchComponents.getTextColor(),
                    fontWeight = FontWeight.SemiBold
                )
                
                Text(
                    text = buildString {
                        if (location.region.isNotEmpty() && location.region != location.name) {
                            append(location.region)
                            append(", ")
                        }
                        append(location.country)
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = MigrationHelper.SearchComponents.getSecondaryTextColor()
                )
            }
              Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = AppResources.Strings.select_location,
                tint = MigrationHelper.SearchComponents.getSecondaryTextColor(),
                modifier = Modifier.size(AppResources.Dimensions.iconSizeSmall)
            )
        }
    }
}
