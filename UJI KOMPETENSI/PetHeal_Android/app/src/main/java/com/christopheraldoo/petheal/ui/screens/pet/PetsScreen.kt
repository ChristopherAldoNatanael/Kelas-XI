package com.christopheraldoo.petheal.ui.screens.pet

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.slideInVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.CachePolicy
import coil.request.ImageRequest
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.model.Vaccination
import com.christopheraldoo.petheal.data.model.WeightRecord
import com.christopheraldoo.petheal.ui.components.EmptyPetsState
import com.christopheraldoo.petheal.ui.components.EmptySearchState
import com.christopheraldoo.petheal.util.buildPhotoUrl
import com.christopheraldoo.petheal.util.compressImageFile
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.MediumImage
import java.io.File
import java.io.FileOutputStream
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

// ── Brand tokens ──────────────────────────────────────────────────────────────
private val PetPrimary       = Color(0xFF18C964)
private val PetBgDark        = Color(0xFFF6F8F6)
private val PetBgLight       = Color(0xFFF6F8F6)
private val PetSurfaceDark   = Color.White
private val PetSurfaceLight  = Color(0xFFFFFFFF)
private val PetMuted         = Color(0xFF61896F)

// ══════════════════════════════════════════════════════════════════════════════
//  PETS LIST SCREEN
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun PetsScreen(
    onNavigateBack: () -> Unit,
    onNavigateToPetDetail: (Int) -> Unit,
    onNavigateToAddPet: () -> Unit,
    onTabSelected: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = {},
    viewModel: PetsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val isDark = false
    val bgColor      = if (isDark) PetBgDark     else PetBgLight
    val surfaceColor = if (isDark) PetSurfaceDark else PetSurfaceLight
    val textPrimary  = if (isDark) Color.White    else Color(0xFF111813)
    val textMuted    = if (isDark) Color(0xFF94A3B8) else PetMuted
    val borderColor  = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9)

    var showSearch by remember { mutableStateOf(false) }

    val filteredPets = remember(uiState.pets, uiState.searchQuery) {
        if (uiState.searchQuery.isBlank()) uiState.pets
        else uiState.pets.filter {
            it.name?.contains(uiState.searchQuery, ignoreCase = true) == true ||
            it.species?.contains(uiState.searchQuery, ignoreCase = true) == true ||
            it.breed?.contains(uiState.searchQuery, ignoreCase = true) == true
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgColor)
    ) {
        Column(modifier = Modifier.fillMaxSize()) {

            // ── Header ─────────────────────────────────────────────────
            Surface(
                modifier = Modifier.fillMaxWidth(),
                color = surfaceColor.copy(alpha = 0.92f),
                shadowElevation = 0.dp,
                tonalElevation = 0.dp
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 8.dp)
                        .statusBarsPadding()
                        .padding(top = 8.dp, bottom = 12.dp)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Back button
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .clickable { onNavigateBack() },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.ArrowBack,
                                contentDescription = "Kembali",
                                tint = textPrimary,
                                modifier = Modifier.size(24.dp)
                            )
                        }

                        Text(
                            "Hewan Saya",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary,
                            modifier = Modifier.weight(1f),
                            textAlign = TextAlign.Center
                        )
                        // Search toggle
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .clickable {
                                    if (showSearch) viewModel.setSearchQuery("")
                                    showSearch = !showSearch
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                if (showSearch) Icons.Filled.Close else Icons.Filled.Search,
                                contentDescription = if (showSearch) "Tutup pencarian" else "Cari hewan",
                                tint = textPrimary,
                                modifier = Modifier.size(24.dp)
                            )
                        }
                        // Refresh button
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .clickable { viewModel.loadPets(forceRefresh = true) },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.Refresh,
                                contentDescription = "Segarkan",
                                tint = textPrimary,
                                modifier = Modifier.size(24.dp)
                            )
                        }
                    }

                    // Search bar
                    AnimatedVisibility(visible = showSearch) {
                        val focusManager = LocalFocusManager.current
                        OutlinedTextField(
                            value = uiState.searchQuery,
                            onValueChange = { viewModel.setSearchQuery(it) },
                            placeholder = {
                                Text("Cari hewan...", color = textMuted, fontSize = 14.sp)
                            },
                            leadingIcon = {
                                Icon(Icons.Filled.Search, null, tint = textMuted, modifier = Modifier.size(20.dp))
                            },
                            trailingIcon = {
                                if (uiState.searchQuery.isNotBlank()) {
                                    IconButton(onClick = { viewModel.setSearchQuery("") }) {
                                        Icon(Icons.Filled.Close, null, tint = textMuted, modifier = Modifier.size(18.dp))
                                    }
                                }
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(horizontal = 8.dp)
                                .padding(top = 12.dp),
                            shape = RoundedCornerShape(12.dp),
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                            keyboardActions = KeyboardActions(onSearch = { focusManager.clearFocus() }),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = PetPrimary,
                                unfocusedBorderColor = borderColor,
                                focusedContainerColor = surfaceColor,
                                unfocusedContainerColor = surfaceColor,
                                focusedTextColor = textPrimary,
                                unfocusedTextColor = textPrimary
                            )
                        )
                    }
                }
                // Bottom border line
                Divider(
                    color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9),
                    thickness = 1.dp
                )
            }

            // ── Content ────────────────────────────────────────────────
            if (uiState.isLoading) {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = PetPrimary)
                }
            } else {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(horizontal = 16.dp),
                    contentPadding = PaddingValues(top = 16.dp, bottom = 120.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Error banner
                    uiState.error?.let { errorMessage ->
                        item {
                            Card(
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = Color(0xFFFF5252).copy(alpha = 0.1f))
                            ) {
                                Row(
                                    modifier = Modifier.padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Icon(Icons.Filled.Warning, null, tint = Color(0xFFFF5252), modifier = Modifier.size(20.dp))
                                    Text(errorMessage, color = Color(0xFFFF5252), fontSize = 13.sp)
                                }
                            }
                        }
                    }

                    // Empty state
                    if (filteredPets.isEmpty() && !uiState.isLoading) {
                        item {
                            if (uiState.searchQuery.isNotBlank()) {
                                EmptySearchState(
                                    query = uiState.searchQuery,
                                    modifier = Modifier.fillMaxWidth().height(460.dp)
                                )
                            } else {
                                EmptyPetsState(
                                    onAddPet = onNavigateToAddPet,
                                    modifier = Modifier.fillMaxWidth().height(460.dp)
                                )
                            }
                        }
                    }

                    // Pet cards
                    itemsIndexed(filteredPets) { index, pet ->
                        PetListCard(
                            pet = pet,
                            isDark = isDark,
                            surfaceColor = surfaceColor,
                            textPrimary = textPrimary,
                            textMuted = textMuted,
                            isFirst = index == 0,
                            onClick = { pet.id?.let { onNavigateToPetDetail(it) } }
                        )
                    }

                    // Add New Pet card at the bottom of the list
                    item {
                        AddNewPetCard(
                            isDark = isDark,
                            surfaceColor = surfaceColor,
                            textPrimary = textPrimary,
                            textMuted = textMuted,
                            onClick = onNavigateToAddPet
                        )
                    }
                }
            }
        }

        // ── FAB ────────────────────────────────────────────────────────
        FloatingActionButton(
            onClick = onNavigateToAddPet,
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(end = 24.dp, bottom = 120.dp),
            containerColor = PetPrimary,
            contentColor = Color.White,
            shape = CircleShape,
            elevation = FloatingActionButtonDefaults.elevation(6.dp)
        ) {
            Icon(Icons.Filled.Add, contentDescription = "Tambah hewan", modifier = Modifier.size(28.dp))
        }
        // ── Bottom Nav (unified floating, PHASE 9) ─────────────────────
        com.christopheraldoo.petheal.ui.components.PetHealFloatingBottomNav(
            selected = com.christopheraldoo.petheal.ui.components.PetHealTab.Pets,
            onSelect = onTabSelected,
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }
}

// ── Pet List Card ─────────────────────────────────────────────────────────────
@Composable
private fun PetListCard(
    pet: Pet,
    isDark: Boolean,
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color,
    isFirst: Boolean,
    onClick: () -> Unit
) {
    val borderColor = if (isFirst) PetPrimary else PetPrimary.copy(alpha = 0.3f)
    val arrowBg     = if (isDark) Color(0xFF1E293B) else Color(0xFFF8FAFC)

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(4.dp, RoundedCornerShape(16.dp), ambientColor = Color.Black.copy(alpha = 0.05f))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {            // Avatar circle
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .clip(CircleShape)
                    .border(2.dp, borderColor, CircleShape)
                    .background(if (isDark) Color(0xFF1E2C24) else Color(0xFFE8F5E9))
            ) {
                val photoUrl = buildPhotoUrl(pet.photo)
                if (photoUrl != null) {
                    // ✅ OPTIMIZED: ThumbnailImage resizes to 100px BEFORE decode → 80% less memory!
                    ThumbnailImage(
                        model = photoUrl,
                        contentDescription = pet.name,
                        modifier = Modifier.fillMaxSize()
                    )
                } else {
                    Icon(
                        Icons.Filled.Pets, null,
                        tint = if (isFirst) PetPrimary else PetPrimary.copy(alpha = 0.6f),
                        modifier = Modifier.size(30.dp).align(Alignment.Center)
                    )
                }
            }

            // Info
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = pet.name ?: "Unknown",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = buildString {
                        if (!pet.species.isNullOrBlank()) append(pet.species)
                        if (!pet.breed.isNullOrBlank()) {
                            if (isNotEmpty()) append(" · ")
                            append(pet.breed)
                        }
                        if (pet.age != null) {
                            if (isNotEmpty()) append(" · ")
                            append("${pet.age} tahun")
                        }
                    }.ifBlank { "Belum ada detail" },
                    fontSize = 13.sp,
                    color = textMuted,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }

            // Chevron
            Box(
                modifier = Modifier
                    .size(32.dp)
                    .clip(CircleShape)
                    .background(arrowBg),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.ChevronRight, null,
                    tint = textMuted,
                    modifier = Modifier.size(20.dp)
                )
            }
        }
    }
}

// ── Add New Pet Card ──────────────────────────────────────────────────────────
@Composable
private fun AddNewPetCard(
    isDark: Boolean,
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(4.dp, RoundedCornerShape(16.dp), ambientColor = Color.Black.copy(0.03f))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor.copy(alpha = 0.6f)),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(64.dp)
                    .clip(CircleShape)
                    .border(2.dp, if (isDark) Color(0xFF374151) else Color(0xFFD1D5DB), CircleShape, )
                    .background(if (isDark) Color(0xFF1F2937) else Color(0xFFF9FAFB)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.Pets, null,
                    tint = if (isDark) Color(0xFF6B7280) else Color(0xFF9CA3AF),
                    modifier = Modifier.size(28.dp)
                )
            }

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    "Tambah Hewan Baru",
                    fontSize = 17.sp, fontWeight = FontWeight.Bold,
                    color = textPrimary
                )
                Text(
                    "Daftarkan sahabat baru Anda",
                    fontSize = 13.sp, color = textMuted
                )
            }

            Box(
                modifier = Modifier
                    .size(32.dp)
                    .clip(CircleShape)
                    .background(if (isDark) Color(0xFF1F2937) else Color(0xFFF8FAFC)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.Add, null,
                    tint = textMuted,
                    modifier = Modifier.size(20.dp)
                )
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  PET DETAIL SCREEN - Updated UI
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun PetDetailScreen(
    petId: Int,
    onNavigateBack: () -> Unit,
    onNavigateToEdit: () -> Unit,
    onNavigateToMedicalRecords: () -> Unit,
    viewModel: PetsViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()
    val isDark = false
    val bgColor      = if (isDark) PetBgDark     else PetBgLight
    val surfaceColor = if (isDark) PetSurfaceDark else PetSurfaceLight
    val textPrimary  = if (isDark) Color.White    else Color(0xFF111813)
    val textMuted    = if (isDark) Color(0xFF94A3B8) else PetMuted

    LaunchedEffect(petId) { viewModel.loadPetDetail(petId) }
    LaunchedEffect(state.isDeleted) { if (state.isDeleted) onNavigateBack() }

    var showDeleteDialog by remember { mutableStateOf(false) }
    var showWeightDialog by remember { mutableStateOf(false) }
    var showVaccinationDialog by remember { mutableStateOf(false) }

    if (showWeightDialog) {
        AddWeightDialog(
            isLoading = state.isHealthActionLoading,
            onDismiss = { showWeightDialog = false },
            onSubmit = { weight, notes ->
                viewModel.addWeightRecord(petId, weight, notes)
                showWeightDialog = false
            }
        )
    }

    if (showVaccinationDialog) {
        AddVaccinationDialog(
            isLoading = state.isHealthActionLoading,
            onDismiss = { showVaccinationDialog = false },
            onSubmit = { name, date, nextDue, vet, notes ->
                viewModel.addVaccination(petId, name, date, nextDue, vet, notes)
                showVaccinationDialog = false
            }
        )
    }

    if (showDeleteDialog) {
        AlertDialog(
            onDismissRequest = { showDeleteDialog = false },
            title = { Text("Hapus Hewan", fontWeight = FontWeight.Bold) },
            text = { Text("Yakin ingin menghapus ${state.pet?.name}? Tindakan ini tidak dapat dibatalkan.") },
            confirmButton = {
                TextButton(onClick = { viewModel.deletePet(petId); showDeleteDialog = false },
                    colors = ButtonDefaults.textButtonColors(contentColor = Color(0xFFEF4444))
                ) { Text("Hapus", fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteDialog = false }) { Text("Batal") }
            },
            containerColor = surfaceColor
        )
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgColor)
    ) {
        if (state.isLoading) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = PetPrimary)
            }
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(bottom = 24.dp)
            ) {
                val pet = state.pet

                // ── Top Navigation Bar ───────────────────────────────────────────
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    color = Color.Transparent,
                    shadowElevation = 0.dp
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp)
                            .statusBarsPadding()
                            .padding(top = 8.dp, bottom = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Back button
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .background(if (isDark) Color.White.copy(alpha = 0.1f) else Color.Black.copy(alpha = 0.05f))
                                .clickable { onNavigateBack() },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.ArrowBackIosNew,
                                contentDescription = "Kembali",
                                tint = textPrimary,
                                modifier = Modifier.size(18.dp)
                            )
                        }

                        Text(
                            "Profil Hewan",
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = textPrimary
                        )

                        // Edit shortcut (mirrors the Edit button below)
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .background(if (isDark) Color.White.copy(alpha = 0.1f) else Color.Black.copy(alpha = 0.05f))
                                .clickable { onNavigateToEdit() },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.Edit,
                                contentDescription = "Ubah hewan",
                                tint = textPrimary,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                    }
                }

                // ── Hero Section ───────────────────────────────────────────────
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    // Pet Photo with checkmark badge
                    Box(contentAlignment = Alignment.BottomEnd) {                        Box(
                            modifier = Modifier
                                .size(128.dp)
                                .clip(CircleShape)
                                .background(if (isDark) Color(0xFF1E2C24) else Color(0xFFE8F5E9))
                                .border(4.dp, PetPrimary.copy(alpha = 0.2f), CircleShape)
                        ) {
                            val photoUrl = buildPhotoUrl(pet?.photo)
                            if (photoUrl != null) {
                                // ✅ OPTIMIZED: MediumImage resizes to 400px BEFORE decode
                                MediumImage(
                                    model = photoUrl,
                                    contentDescription = pet?.name,
                                    modifier = Modifier.fillMaxSize()
                                )
                            } else {
                                Icon(
                                    Icons.Filled.Pets, null,
                                    tint = PetPrimary.copy(alpha = 0.6f),
                                    modifier = Modifier
                                        .size(50.dp)
                                        .align(Alignment.Center)
                                )
                            }
                        }
                        // Checkmark badge
                        Box(
                            modifier = Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(PetPrimary),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.Check,
                                contentDescription = "Healthy",
                                tint = Color(0xFFF6F8F6),
                                modifier = Modifier.size(18.dp)
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Pet Name
                    Text(
                        pet?.name ?: "–",
                        fontSize = 28.sp,
                        fontWeight = FontWeight.ExtraBold,
                        color = textPrimary
                    )

                    // Health Status — derived from the most recent medical record
                    val healthLabel = remember(state.medicalRecords) {
                        val latest = state.medicalRecords.firstOrNull()
                        if (latest == null) "Belum ada kunjungan" else "Pemeriksaan terakhir"
                    }
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(CircleShape)
                                .background(PetPrimary)
                        )
                        Text(
                            healthLabel,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = PetPrimary
                        )
                    }

                    // Species, Gender, Age
                    Text(
                        buildString {
                            if (!pet?.species.isNullOrBlank()) append(pet?.species)
                            if (!pet?.gender.isNullOrBlank()) {
                                if (isNotEmpty()) append(" • ")
                                append(pet?.gender)
                            }
                            if (pet?.age != null) {
                                if (isNotEmpty()) append(" • ")
                                append("${pet.age} years old")
                            }
                        }.ifBlank { "No details" },
                        fontSize = 14.sp,
                        color = textMuted
                    )
                }

                Spacer(modifier = Modifier.height(24.dp))

                // ── Action Buttons ───────────────────────────────────────────────
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Edit Profile Button
                    Button(
                        onClick = onNavigateToEdit,
                        modifier = Modifier
                            .weight(1f)
                            .height(52.dp),
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = PetPrimary,
                            contentColor = Color(0xFF111813)
                        )
                    ) {
                        Icon(Icons.Filled.Edit, null, modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Edit Profil", fontWeight = FontWeight.Bold)
                    }

                    // History Button
                    OutlinedButton(
                        onClick = onNavigateToMedicalRecords,
                        modifier = Modifier
                            .weight(1f)
                            .height(52.dp),
                        shape = RoundedCornerShape(14.dp),
                        border = BorderStroke(1.dp, PetPrimary.copy(alpha = 0.3f)),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = PetPrimary)
                    ) {
                        Icon(Icons.Filled.History, null, modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Riwayat", fontWeight = FontWeight.Bold)
                    }
                }

                Spacer(modifier = Modifier.height(24.dp))

                // ── Quick Stats Cards ───────────────────────────────────────────
                val lastVisitLabel = remember(state.medicalRecords, pet?.updatedAt) {
                    val latest = state.medicalRecords.firstOrNull()?.createdAt
                        ?: pet?.updatedAt
                    if (latest.isNullOrBlank()) "–"
                    else latest.take(10)
                }
                val vaccinationCount = state.vaccinations.size
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Weight Card
                    StatCard(
                        label = "Berat",
                        value = pet?.weight?.let { "${it} kg" } ?: "–",
                        icon = Icons.Filled.FitnessCenter,
                        modifier = Modifier.weight(1f),
                        surfaceColor = surfaceColor,
                        textPrimary = textPrimary,
                        textMuted = textMuted
                    )
                    // Vaccination count
                    StatCard(
                        label = "Vaksin",
                        value = vaccinationCount.toString(),
                        icon = Icons.Filled.Vaccines,
                        modifier = Modifier.weight(1f),
                        surfaceColor = surfaceColor,
                        textPrimary = textPrimary,
                        textMuted = textMuted
                    )
                    // Last Visit Card
                    StatCard(
                        label = "Kunjungan",
                        value = lastVisitLabel,
                        icon = Icons.Filled.CalendarMonth,
                        modifier = Modifier.weight(1f),
                        surfaceColor = surfaceColor,
                        textPrimary = textPrimary,
                        textMuted = textMuted
                    )
                }

                Spacer(modifier = Modifier.height(24.dp))

                // ── Health Records Section ───────────────────────────────────────
                Column(
                    modifier = Modifier.padding(horizontal = 16.dp)
                ) {
                    HealthTrackingSection(
                        weightRecords = state.weightRecords,
                        vaccinations = state.vaccinations,
                        upcomingVaccinations = state.upcomingVaccinations,
                        currentWeight = state.pet?.weight,
                        weightTrend = state.weightChange?.trend,
                        onAddWeight = { showWeightDialog = true },
                        onAddVaccination = { showVaccinationDialog = true },
                        // PHASE 7: delete affordances (declared endpoints).
                        onDeleteWeight = { recordId ->
                            viewModel.deleteWeightRecord(petId, recordId)
                        },
                        onDeleteVaccination = { vaccinationId ->
                            viewModel.deleteVaccination(petId, vaccinationId)
                        },
                        surfaceColor = surfaceColor,
                        textPrimary = textPrimary,
                        textMuted = textMuted,
                        modifier = Modifier.padding(bottom = 24.dp)
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                            Text(
                                "Medical Records",
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )
                            Text(
                                "Recent diagnosis, treatment, and follow-up history",
                                fontSize = 12.sp,
                                color = textMuted
                            )
                        }
                        TextButton(onClick = onNavigateToMedicalRecords) {
                            Text(
                                "View All",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = PetPrimary
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(12.dp))

                    // Show real medical records from database
                    val medicalRecords = state.medicalRecords
                    
                    if (medicalRecords.isEmpty()) {
                        // Empty state - no medical records yet
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = surfaceColor),
                            elevation = CardDefaults.cardElevation(0.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(24.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Icon(
                                    Icons.Filled.MedicalServices,
                                    contentDescription = null,
                                    tint = textMuted,
                                    modifier = Modifier.size(40.dp)
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    "No medical records yet",
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = textPrimary
                                )
                                Text(
                                    "Records will appear here after your pet completes a consultation or treatment.",
                                    fontSize = 13.sp,
                                    color = textMuted,
                                    textAlign = TextAlign.Center
                                )
                            }
                        }
                    } else {
                        // Display actual medical records from database
                        medicalRecords.forEachIndexed { index, record ->
                            if (index > 0) {
                                Spacer(modifier = Modifier.height(12.dp))
                            }
                            
                            val recordTitle = record.diagnosis ?: "General medical review"
                            val recordSubtitle = buildString {
                                record.treatment?.takeIf { it.isNotBlank() }?.let { append(it) }
                                record.medicine?.takeIf { it.isNotBlank() }?.let {
                                    if (isNotEmpty()) append(" • ")
                                    append(it)
                                }
                            }.ifBlank { "Tap to review the clinical summary and treatment notes." }

                            val recordMeta = buildString {
                                append(formatMedicalRecordDate(record.createdAt))
                                if (!record.nextVisitDate.isNullOrBlank()) {
                                    append(" • Follow-up ")
                                    append(record.nextVisitDate)
                                }
                            }

                            val isActive = !record.nextVisitDate.isNullOrBlank()

                            HealthRecordCard(
                                icon = Icons.Filled.LocalHospital,
                                title = recordTitle,
                                subtitle = recordSubtitle,
                                meta = recordMeta,
                                isActive = isActive,
                                showChevron = true,
                                surfaceColor = surfaceColor,
                                textPrimary = textPrimary,
                                textMuted = textMuted,
                                onClick = onNavigateToMedicalRecords
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(24.dp))

                // ── Delete Button ───────────────────────────────────────────────
                Button(
                    onClick = { showDeleteDialog = true },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp)
                        .height(52.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color(0xFFEF4444).copy(alpha = 0.1f),
                        contentColor = Color(0xFFEF4444)
                    )
                ) {
                    Icon(Icons.Filled.Delete, null, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Remove Pet", fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun HealthTrackingSection(
    weightRecords: List<WeightRecord>,
    vaccinations: List<Vaccination>,
    upcomingVaccinations: List<Vaccination>,
    currentWeight: Double?,
    weightTrend: String?,
    onAddWeight: () -> Unit,
    onAddVaccination: () -> Unit,
    onDeleteWeight: (Int) -> Unit = {},
    onDeleteVaccination: (Int) -> Unit = {},
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                "Health Tracking",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = textPrimary
            )
            Text(
                "Weight history and vaccination reminders in one place",
                fontSize = 12.sp,
                color = textMuted
            )
        }

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(22.dp),
            colors = CardDefaults.cardColors(containerColor = surfaceColor),
            elevation = CardDefaults.cardElevation(0.dp)
        ) {
            Column(modifier = Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(42.dp)
                            .clip(RoundedCornerShape(14.dp))
                            .background(PetPrimary.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Filled.FitnessCenter, null, tint = PetPrimary, modifier = Modifier.size(22.dp))
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text("Weight Tracker", fontWeight = FontWeight.Bold, color = textPrimary)
                        Text(
                            currentWeight?.let { "Current weight: $it kg" } ?: "No weight record saved yet",
                            fontSize = 13.sp,
                            color = textMuted
                        )
                    }
                    TextButton(onClick = onAddWeight) {
                        Icon(Icons.Filled.Add, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Tambah")
                    }
                }

                weightTrend?.let {
                    Text(
                        text = "Trend: ${it.replaceFirstChar { c -> c.uppercaseChar() }}",
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(PetPrimary.copy(alpha = 0.12f))
                            .padding(horizontal = 10.dp, vertical = 6.dp),
                        color = PetPrimary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                }

                if (weightRecords.isEmpty()) {
                    Text("Start recording weight so growth, recovery, and health changes are easier to monitor over time.", fontSize = 13.sp, lineHeight = 20.sp, color = textMuted)
                } else {
                    weightRecords.take(3).forEach { record ->
                        HealthMiniRow(
                            title = "${record.weight ?: 0.0} kg",
                            subtitle = listOfNotNull(record.recordedAt, record.notes).joinToString(" • ").ifBlank { "Weight record" },
                            textPrimary = textPrimary,
                            textMuted = textMuted,
                            onDelete = record.id?.let { id -> { onDeleteWeight(id) } }
                        )
                    }
                }
            }
        }

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(22.dp),
            colors = CardDefaults.cardColors(containerColor = surfaceColor),
            elevation = CardDefaults.cardElevation(0.dp)
        ) {
            Column(modifier = Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(42.dp)
                            .clip(RoundedCornerShape(14.dp))
                            .background(PetPrimary.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Filled.MedicalServices, null, tint = PetPrimary, modifier = Modifier.size(22.dp))
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text("Vaksinasi", fontWeight = FontWeight.Bold, color = textPrimary)
                        Text(
                            if (upcomingVaccinations.isNotEmpty()) "${upcomingVaccinations.size} follow-up reminder${if (upcomingVaccinations.size > 1) "s" else ""} coming up" else "No upcoming vaccination due",
                            fontSize = 13.sp,
                            color = textMuted
                        )
                    }
                    TextButton(onClick = onAddVaccination) {
                        Icon(Icons.Filled.Add, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Tambah")
                    }
                }

                val preview = upcomingVaccinations.ifEmpty { vaccinations }.take(3)
                if (preview.isEmpty()) {
                    Text("Save vaccination history here so reminders and medical timelines stay complete.", fontSize = 13.sp, lineHeight = 20.sp, color = textMuted)
                } else {
                    preview.forEach { vaccine ->
                        HealthMiniRow(
                            title = vaccine.vaccineName ?: "Vaksinasi",
                            subtitle = buildString {
                                vaccine.dateAdministered?.let { append("Diberikan $it") }
                                vaccine.nextDueDate?.let {
                                    if (isNotEmpty()) append(" • ")
                                    append("Jatuh tempo $it")
                                }
                            }.ifBlank { vaccine.veterinarian ?: "Catatan vaksinasi" },
                            textPrimary = textPrimary,
                            textMuted = textMuted,
                            onDelete = vaccine.id?.let { id -> { onDeleteVaccination(id) } }
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun HealthMiniRow(
    title: String,
    subtitle: String,
    textPrimary: Color,
    textMuted: Color,
    // PHASE 7: optional delete (wires the declared DELETE endpoints).
    onDelete: (() -> Unit)? = null
) {
    var showDeleteConfirm by remember { mutableStateOf(false) }
    if (showDeleteConfirm) {
        AlertDialog(
            onDismissRequest = { showDeleteConfirm = false },
            title = { Text("Hapus Data?", fontWeight = FontWeight.Bold) },
            text = { Text("Data \"$title\" akan dihapus permanen dan tidak bisa dikembalikan.") },
            confirmButton = {
                TextButton(
                    onClick = { showDeleteConfirm = false; onDelete?.invoke() },
                    colors = ButtonDefaults.textButtonColors(contentColor = Color(0xFFEF4444))
                ) { Text("Hapus", fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteConfirm = false }) { Text("Batal") }
            },
            containerColor = Color.White
        )
    }
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(PetPrimary.copy(alpha = 0.06f))
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(8.dp)
                .clip(CircleShape)
                .background(PetPrimary)
        )
        Spacer(Modifier.width(10.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = textPrimary)
            Text(subtitle, fontSize = 12.sp, color = textMuted, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
        if (onDelete != null) {
            IconButton(onClick = { showDeleteConfirm = true }, modifier = Modifier.size(40.dp)) {
                Icon(
                    imageVector = Icons.Filled.DeleteOutline,
                    contentDescription = "Hapus $title",
                    tint = Color(0xFFEF4444),
                    modifier = Modifier.size(20.dp)
                )
            }
        }
    }
}

@Composable
private fun AddWeightDialog(
    isLoading: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (weight: Double, notes: String?) -> Unit
) {
    var weight by remember { mutableStateOf("") }
    var notes by remember { mutableStateOf("") }
    val parsedWeight = weight.toDoubleOrNull()

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color.White,
        shape = RoundedCornerShape(24.dp),
        title = {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Catat Berat", fontWeight = FontWeight.Bold, color = Color(0xFF111813))
                Text(
                    "Simpan berat terbaru agar progres hewan Anda tetap terpantau dengan baik.",
                    fontSize = 13.sp,
                    color = PetMuted,
                    lineHeight = 18.sp
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                HealthDialogHintCard(
                    icon = Icons.Filled.MonitorWeight,
                    title = "Catatan singkat",
                    body = "Gunakan kilogram agar konsisten. Tambahkan catatan hanya bila hasil timbangan ini perlu konteks ekstra."
                )
                HealthDialogField(
                    value = weight,
                    onValueChange = { weight = it },
                    label = "Berat (kg)",
                    placeholder = "Contoh: 5,8",
                    keyboardType = KeyboardType.Decimal
                )
                HealthDialogField(
                    value = notes,
                    onValueChange = { notes = it },
                    label = "Catatan",
                    placeholder = "Observasi opsional setelah menimbang",
                    minLines = 3,
                    singleLine = false
                )
            }
        },
        confirmButton = {
            Button(
                enabled = !isLoading && parsedWeight != null && parsedWeight > 0.0,
                onClick = { parsedWeight?.let { onSubmit(it, notes.trim().ifBlank { null }) } },
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(containerColor = PetPrimary, contentColor = Color(0xFFF6F8F6))
            ) { Text(if (isLoading) "Menyimpan..." else "Simpan Berat") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Tutup") } }
    )
}

@Composable
private fun AddVaccinationDialog(
    isLoading: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (name: String, date: String, nextDue: String?, veterinarian: String?, notes: String?) -> Unit
) {
    val context = LocalContext.current
    val today = remember { SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date()) }
    var name by remember { mutableStateOf("") }
    var dateAdministered by remember { mutableStateOf(today) }
    var nextDueDate by remember { mutableStateOf("") }
    var veterinarian by remember { mutableStateOf("") }
    var notes by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color.White,
        shape = RoundedCornerShape(24.dp),
        title = {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Tambah Vaksinasi", fontWeight = FontWeight.Bold, color = Color(0xFF111813))
                Text(
                    "Catat detail vaksin dan jadwal tindak lanjut (jika ada).",
                    fontSize = 13.sp,
                    color = PetMuted,
                    lineHeight = 18.sp
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                HealthDialogHintCard(
                    icon = Icons.Filled.Vaccines,
                    title = "Pengingat",
                    body = "Gunakan tanggal pemberian untuk vaksin hari ini. Tambahkan tanggal jatuh tempo bila vaksin perlu lanjutan."
                )
                HealthDialogField(
                    value = name,
                    onValueChange = { name = it },
                    label = "Nama vaksin",
                    placeholder = "Contoh: Rabies Booster"
                )
                HealthDialogDateField(
                    value = dateAdministered,
                    label = "Tanggal pemberian",
                    placeholder = "Pilih tanggal",
                    onClick = {
                        showPetDatePicker(context, dateAdministered.ifBlank { today }) {
                            dateAdministered = it
                        }
                    }
                )
                HealthDialogDateField(
                    value = nextDueDate,
                    label = "Jatuh tempo berikutnya",
                    placeholder = "Tanggal lanjutan (opsional)",
                    onClick = {
                        showPetDatePicker(context, nextDueDate.ifBlank { dateAdministered }) {
                            nextDueDate = it
                        }
                    },
                    onClear = {
                        nextDueDate = ""
                    }
                )
                HealthDialogField(
                    value = veterinarian,
                    onValueChange = { veterinarian = it },
                    label = "Dokter hewan",
                    placeholder = "Nama dokter atau klinik (opsional)"
                )
                HealthDialogField(
                    value = notes,
                    onValueChange = { notes = it },
                    label = "Catatan",
                    placeholder = "Catatan pemulihan atau observasi (opsional)",
                    minLines = 3,
                    singleLine = false
                )
            }
        },
        confirmButton = {
            Button(
                enabled = !isLoading && name.isNotBlank() && dateAdministered.isNotBlank(),
                onClick = {
                    onSubmit(
                        name.trim(),
                        dateAdministered.trim(),
                        nextDueDate.trim().ifBlank { null },
                        veterinarian.trim().ifBlank { null },
                        notes.trim().ifBlank { null }
                    )
                },
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(containerColor = PetPrimary, contentColor = Color(0xFFF6F8F6))
            ) { Text(if (isLoading) "Menyimpan..." else "Simpan Vaksinasi") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Tutup") } }
    )
}

@Composable
private fun HealthDialogHintCard(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    body: String
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(PetPrimary.copy(alpha = 0.08f))
            .border(1.dp, PetPrimary.copy(alpha = 0.14f), RoundedCornerShape(16.dp))
            .padding(14.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.Top
    ) {
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(PetPrimary.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = PetPrimary, modifier = Modifier.size(18.dp))
        }
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(title, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = Color(0xFF111813))
            Text(body, fontSize = 12.sp, color = PetMuted, lineHeight = 18.sp)
        }
    }
}

@Composable
private fun HealthDialogField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    placeholder: String,
    keyboardType: KeyboardType = KeyboardType.Text,
    minLines: Int = 1,
    singleLine: Boolean = true
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        placeholder = { Text(placeholder) },
        modifier = Modifier.fillMaxWidth(),
        minLines = minLines,
        singleLine = singleLine,
        shape = RoundedCornerShape(16.dp),
        keyboardOptions = KeyboardOptions(
            keyboardType = keyboardType,
            imeAction = if (singleLine) ImeAction.Done else ImeAction.Default
        ),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = PetPrimary,
            unfocusedBorderColor = Color(0xFFE2E8F0),
            focusedContainerColor = Color.White,
            unfocusedContainerColor = Color.White
        )
    )
}

@Composable
private fun HealthDialogDateField(
    value: String,
    label: String,
    placeholder: String,
    onClick: () -> Unit,
    onClear: (() -> Unit)? = null
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(label, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = PetMuted)
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .clickable(onClick = onClick),
            shape = RoundedCornerShape(16.dp),
            color = Color.White,
            border = BorderStroke(1.dp, Color(0xFFE2E8F0))
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 14.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    modifier = Modifier.weight(1f),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Icon(Icons.Filled.CalendarMonth, contentDescription = null, tint = PetPrimary, modifier = Modifier.size(18.dp))
                    Text(
                        text = value.ifBlank { placeholder },
                        fontSize = 14.sp,
                        color = if (value.isBlank()) PetMuted else Color(0xFF111813)
                    )
                }
                if (onClear != null && value.isNotBlank()) {
                    TextButton(onClick = onClear, contentPadding = PaddingValues(0.dp)) {
                        Text("Hapus", color = PetMuted)
                    }
                }
            }
        }
    }
}

private fun showPetDatePicker(
    context: Context,
    initialDate: String,
    onDateSelected: (String) -> Unit
) {
    val calendar = Calendar.getInstance()
    initialDate.split("-").takeIf { it.size == 3 }?.let { parts ->
        runCatching {
            calendar.set(parts[0].toInt(), parts[1].toInt() - 1, parts[2].toInt())
        }
    }

    android.app.DatePickerDialog(
        context,
        { _, year, month, dayOfMonth ->
            onDateSelected(String.format(Locale.US, "%04d-%02d-%02d", year, month + 1, dayOfMonth))
        },
        calendar.get(Calendar.YEAR),
        calendar.get(Calendar.MONTH),
        calendar.get(Calendar.DAY_OF_MONTH)
    ).show()
}

@Composable
private fun StatCard(
    label: String,
    value: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    modifier: Modifier = Modifier,
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color
) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(icon, null, tint = PetPrimary, modifier = Modifier.size(22.dp))
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                value,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = textPrimary
            )
            Text(
                label,
                fontSize = 10.sp,
                fontWeight = FontWeight.SemiBold,
                color = textMuted
            )
        }
    }
}

@Composable
private fun HealthRecordCard(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    subtitle: String,
    meta: String,
    isActive: Boolean,
    modifier: Modifier = Modifier,
    showChevron: Boolean = false,
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color,
    onClick: (() -> Unit)? = null
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .then(if (onClick != null) Modifier.clickable { onClick() } else Modifier),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(50.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(PetPrimary.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, null, tint = PetPrimary, modifier = Modifier.size(24.dp))
            }

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary
                )
                Text(
                    subtitle,
                    fontSize = 13.sp,
                    color = textMuted,
                    lineHeight = 18.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    meta,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Medium,
                    color = textMuted
                )
            }

            if (isActive) {
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(PetPrimary.copy(alpha = 0.2f))
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        "Follow-up",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = PetPrimary
                    )
                }
            } else if (showChevron) {
                Icon(
                    Icons.Filled.ChevronRight,
                    null,
                    tint = textMuted,
                    modifier = Modifier.size(24.dp)
                )
            } else {
                Icon(
                    Icons.Filled.Verified,
                    null,
                    tint = PetPrimary,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

private fun formatMedicalRecordDate(value: String?): String {
    if (value.isNullOrBlank()) return "Recorded recently"
    return try {
        "Recorded ${value.take(10)}"
    } catch (_: Exception) {
        "Recorded recently"
    }
}

@Composable
private fun PetStatCard(
    label: String,
    value: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor),
        elevation = CardDefaults.cardElevation(2.dp)
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Icon(icon, null, tint = PetPrimary, modifier = Modifier.size(22.dp))
            Text(value, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = textPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(label, fontSize = 11.sp, color = textMuted)
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  ADD PET SCREEN
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun AddPetScreen(
    onNavigateBack: () -> Unit,
    onPetAdded: () -> Unit,
    viewModel: PetsViewModel = hiltViewModel()
) {
    val state by viewModel.addEditState.collectAsState()
    // PHASE 7: confirm success explicitly instead of silently popping back.
    var showSuccessDialog by remember { mutableStateOf(false) }
    var formKey by remember { mutableStateOf(0) }
    LaunchedEffect(state.isSuccess) {
        if (state.isSuccess) showSuccessDialog = true
    }

    if (showSuccessDialog) {
        val petName = state.createdPetName.orEmpty()
        AlertDialog(
            onDismissRequest = { /* force an explicit choice */ },
            icon = {
                Box(
                    modifier = Modifier
                        .size(56.dp)
                        .clip(CircleShape)
                        .background(PetPrimary.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.CheckCircle,
                        contentDescription = null,
                        tint = PetPrimary,
                        modifier = Modifier.size(30.dp)
                    )
                }
            },
            title = {
                Text(
                    "Hewan Berhasil Ditambahkan",
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            },
            text = {
                Text(
                    if (petName.isNotBlank()) {
                        "$petName sudah masuk ke daftar My Pets dan siap untuk dibuatkan janji konsultasi."
                    } else {
                        "Data hewan baru sudah masuk ke daftar My Pets."
                    },
                    fontSize = 14.sp,
                    lineHeight = 20.sp,
                    textAlign = TextAlign.Center,
                    color = PetMuted,
                    modifier = Modifier.fillMaxWidth()
                )
            },
            confirmButton = {
                Button(
                    onClick = {
                        showSuccessDialog = false
                        viewModel.clearAddEditState()
                        onPetAdded()
                    },
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = PetPrimary,
                        contentColor = Color(0xFFF6F8F6)
                    )
                ) {
                    Text("Lihat Daftar", fontWeight = FontWeight.SemiBold)
                }
            },
            dismissButton = {
                TextButton(
                    onClick = {
                        showSuccessDialog = false
                        viewModel.clearAddEditState()
                        formKey++
                    }
                ) {
                    Text("Tambah Lagi", color = PetPrimary, fontWeight = FontWeight.SemiBold)
                }
            }
        )
    }

    key(formKey) {
        PetFormScreen(
            title = "Tambah Hewan Baru",
            submitLabel = "Simpan Profil Hewan",
            isLoading = state.isLoading,
            error = state.error,
            onNavigateBack = onNavigateBack,
            onSubmit = { name, species, breed, gender, dateOfBirth, age, weight, photoFile ->
                viewModel.addPet(name, species, breed, gender, dateOfBirth, age, weight, photoFile)
            }
        )
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  EDIT PET SCREEN
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun EditPetScreen(
    petId: Int,
    onNavigateBack: () -> Unit,
    onPetUpdated: () -> Unit,
    viewModel: PetsViewModel = hiltViewModel()
) {
    val state       by viewModel.addEditState.collectAsState()
    val detailState by viewModel.detailState.collectAsState()

    LaunchedEffect(petId) { viewModel.loadPetDetail(petId) }
    LaunchedEffect(state.isSuccess) { if (state.isSuccess) { viewModel.clearAddEditState(); onPetUpdated() } }

    PetFormScreen(
        title = "Edit Hewan",
        submitLabel = "Simpan Perubahan",
        isLoading = state.isLoading,
        error = state.error,
        initialName        = detailState.pet?.name ?: "",
        initialSpecies     = detailState.pet?.species ?: "",
        initialBreed       = detailState.pet?.breed ?: "",
        initialGender      = detailState.pet?.gender?.let {
            it.replaceFirstChar { c -> c.uppercaseChar() }
        } ?: "Male",
        initialDateOfBirth = detailState.pet?.dateOfBirth ?: "",
        initialAge         = detailState.pet?.age?.toString() ?: "",
        initialWeight      = detailState.pet?.weight?.toString() ?: "",
        existingPhotoUrl   = detailState.pet?.photo,
        onNavigateBack = onNavigateBack,
        onSubmit = { name, species, breed, gender, dateOfBirth, age, weight, photoFile ->
            viewModel.editPet(petId, name, species, breed, gender, dateOfBirth, age, weight, photoFile)
        }
    )
}

// ── Helper: create a temp file URI for the camera ────────────────────────────
private fun createImageUri(context: Context): Pair<Uri, File> {
    val timeStamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
    val dir = File(context.getExternalFilesDir("Pictures"), "").also { it.mkdirs() }
    val file = File(dir, "PET_${timeStamp}.jpg")
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    return Pair(uri, file)
}

// ── Helper: copy a gallery URI into a local cache file ───────────────────────
private fun uriToFile(context: Context, uri: Uri): File? {
    return try {
        val timeStamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
        val cacheFile = File(context.cacheDir, "pet_pick_${timeStamp}.jpg")
        context.contentResolver.openInputStream(uri)?.use { input ->
            FileOutputStream(cacheFile).use { output -> input.copyTo(output) }
        }
        cacheFile
    } catch (e: Exception) { null }
}

// ── Shared Form Screen ────────────────────────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PetFormScreen(
    title: String,
    submitLabel: String,
    isLoading: Boolean,
    error: String?,
    initialName: String = "",
    initialSpecies: String = "",
    initialBreed: String = "",
    initialGender: String = "Male",
    initialDateOfBirth: String = "",
    initialAge: String = "",
    initialWeight: String = "",
    existingPhotoUrl: String? = null,
    onNavigateBack: () -> Unit,
    onSubmit: (name: String, species: String, breed: String?, gender: String?,
               dateOfBirth: String?, age: Int?, weight: Double?, photoFile: File?) -> Unit
) {    val context      = LocalContext.current
    val isDark       = false
    val bgColor      = if (isDark) PetBgDark     else PetBgLight
    val surfaceColor = if (isDark) Color.White else Color.White
    val textPrimary  = if (isDark) Color.White    else Color(0xFF111813)
    val textMuted    = if (isDark) Color(0xFF94A3B8) else Color(0xFF61896F)
    val borderColor  = if (isDark) Color(0x33FFFFFF) else Color(0xFFE2E8F0)
    val focusManager = LocalFocusManager.current

    var name          by remember(initialName)        { mutableStateOf(initialName) }
    var species       by remember(initialSpecies)     { mutableStateOf(initialSpecies) }
    // PHASE 7: "Other" reveals a free-text species field. If an existing
    // record already holds a custom value, restore it into that field.
    val speciesOptions = listOf("Dog", "Cat", "Bird", "Rabbit", "Other")
    var customSpecies by remember(initialSpecies) {
        mutableStateOf(
            if (initialSpecies.isNotBlank() && initialSpecies !in speciesOptions) initialSpecies else ""
        )
    }
    // Normalize legacy/custom values to the Other branch (edit flow).
    LaunchedEffect(initialSpecies) {
        if (initialSpecies.isNotBlank() && initialSpecies !in speciesOptions) {
            species = "Other"
        }
    }
    var breed         by remember(initialBreed)       { mutableStateOf(initialBreed) }
    var gender        by remember(initialGender)      { mutableStateOf(initialGender) }
    var dateOfBirth   by remember(initialDateOfBirth) { mutableStateOf(initialDateOfBirth) }
    var weight        by remember(initialWeight)      { mutableStateOf(initialWeight) }
    var expandSpecies by remember { mutableStateOf(false) }
    var showDatePicker by remember { mutableStateOf(false) }    // ── Photo state ───────────────────────────────────────────────────────
    var selectedPhotoUri  by remember { mutableStateOf<Uri?>(null) }
    var selectedPhotoFile by remember { mutableStateOf<File?>(null) }
    var showPhotoSheet    by remember { mutableStateOf(false) }

    // Temp URI/file for camera capture (created before launching camera)
    var cameraUri  by remember { mutableStateOf<Uri?>(null) }
    var cameraFile by remember { mutableStateOf<File?>(null) }

    // Pending launch action — executed AFTER ModalBottomSheet fully dismisses
    // so the camera Activity can open cleanly (no conflict with sheet animation)
    var pendingLaunch by remember { mutableStateOf<String?>(null) } // "camera" | "gallery"

    // ── Permission state ──────────────────────────────────────────────────
    var pendingAction by remember { mutableStateOf<String?>(null) } // "camera" or "gallery"

    // Kompresi + copy file itu I/O berat — wajib di background agar UI tidak
    // freeze/ANR (decode foto 12MP di main thread bisa bikin jank).
    val photoScope = rememberCoroutineScope()
    var isProcessingPhoto by remember { mutableStateOf(false) }

    /** Salin (galeri) / ambil (kamera) lalu kompres di background. */
    fun processPickedPhoto(rawFileProvider: () -> File?) {
        isProcessingPhoto = true
        photoScope.launch(Dispatchers.Default) {
            val result = try {
                val raw = rawFileProvider()
                if (raw != null) compressImageFile(context, raw) ?: raw else null
            } catch (_: Exception) { null }
            withContext(Dispatchers.Main) {
                selectedPhotoFile = result
                isProcessingPhoto = false
            }
        }
    }

    // ── Activity Result Launchers ─────────────────────────────────────────

    // Gallery picker
    val galleryLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.GetContent()
    ) { uri: Uri? ->
        if (uri != null) {
            selectedPhotoUri = uri
            // Kompres sebelum upload: file galeri bisa > 4MB (batas backend 4096KB).
            processPickedPhoto { uriToFile(context, uri) }
        }
    }

    // Camera capture
    val cameraLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.TakePicture()
    ) { success: Boolean ->
        if (success && cameraUri != null) {
            selectedPhotoUri = cameraUri
            // Foto kamera resolusi penuh (8-20MB) wajib dikompres agar
            // tidak ditolak backend ("must not be greater than 4096 kilobytes").
            val captured = cameraFile
            processPickedPhoto { captured }
        }
    }

    // Camera permission
    val cameraPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (granted) {
            val (uri, file) = createImageUri(context)
            cameraUri = uri; cameraFile = file
            cameraLauncher.launch(uri)
        }
        pendingAction = null
    }    // Storage permission (for gallery on Android < 13)
    val storagePermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (granted) galleryLauncher.launch("image/*")
        pendingAction = null
    }

    // Local functions for camera and gallery
    fun launchCamera() {
        if (ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA)
            == PackageManager.PERMISSION_GRANTED) {
            val (uri, file) = createImageUri(context)
            cameraUri = uri; cameraFile = file
            cameraLauncher.launch(uri)
        } else {
            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    fun launchGallery() {
        val storagePermission = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
            Manifest.permission.READ_MEDIA_IMAGES
        else
            Manifest.permission.READ_EXTERNAL_STORAGE

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, storagePermission) == PackageManager.PERMISSION_GRANTED) {
            galleryLauncher.launch("image/*")
        } else {
            storagePermissionLauncher.launch(storagePermission)
        }
    }

    // Without this delay, the camera Activity launch conflicts with the
    // sheet's exit animation and silently fails on many devices.
    LaunchedEffect(showPhotoSheet) {
        if (!showPhotoSheet && pendingLaunch != null) {
            // One frame is enough for the sheet to fully detach
            kotlinx.coroutines.delay(150)
            when (pendingLaunch) {
                "camera"  -> launchCamera()
                "gallery" -> launchGallery()
            }
            pendingLaunch = null
        }
    }

    // ── Native DatePickerDialog ───────────────────────────────────────────
    if (showDatePicker) {
        val calendar = Calendar.getInstance()
        // Pre-fill picker with existing value if valid (YYYY-MM-DD)
        if (dateOfBirth.matches(Regex("\\d{4}-\\d{2}-\\d{2}"))) {
            runCatching {
                val parts = dateOfBirth.split("-")
                calendar.set(parts[0].toInt(), parts[1].toInt() - 1, parts[2].toInt())
            }
        }
        DisposableEffect(Unit) {
            val dialog = android.app.DatePickerDialog(
                context,
                { _, year, month, day ->
                    dateOfBirth = "%04d-%02d-%02d".format(year, month + 1, day)
                    showDatePicker = false
                },
                calendar.get(Calendar.YEAR),
                calendar.get(Calendar.MONTH),
                calendar.get(Calendar.DAY_OF_MONTH)
            ).apply {
                datePicker.maxDate = System.currentTimeMillis()
                setOnCancelListener { showDatePicker = false }
                setOnDismissListener { showDatePicker = false }
                show()
            }
            onDispose { dialog.dismiss() }
        }
    }

    // ── Photo source bottom sheet ─────────────────────────────────────────
    if (showPhotoSheet) {
        ModalBottomSheet(
            onDismissRequest = { showPhotoSheet = false },
            containerColor = surfaceColor,
            tonalElevation = 0.dp
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(bottom = 40.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    "Select Photo",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = textPrimary,
                    modifier = Modifier.padding(bottom = 4.dp)
                )                // Camera option
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(if (isDark) Color(0xFF1E3526) else Color(0xFFEFF4F0))
                        .clickable {
                            pendingLaunch = "camera"
                            showPhotoSheet = false   // LaunchedEffect fires launchCamera() after dismiss
                        }
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(PetPrimary.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Filled.CameraAlt, null, tint = PetPrimary, modifier = Modifier.size(22.dp))
                    }
                    Column {
                        Text("Ambil Foto", fontWeight = FontWeight.SemiBold, color = textPrimary, fontSize = 15.sp)
                        Text("Gunakan kamera", color = textMuted, fontSize = 12.sp)
                    }
                }                // Gallery option
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(if (isDark) Color(0xFF1E3526) else Color(0xFFEFF4F0))
                        .clickable {
                            pendingLaunch = "gallery"
                            showPhotoSheet = false   // LaunchedEffect fires launchGallery() after dismiss
                        }
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(PetPrimary.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Filled.PhotoLibrary, null, tint = PetPrimary, modifier = Modifier.size(22.dp))
                    }
                    Column {
                        Text("Pilih dari Galeri", fontWeight = FontWeight.SemiBold, color = textPrimary, fontSize = 15.sp)
                        Text("Ambil dari foto Anda", color = textMuted, fontSize = 12.sp)
                    }
                }
                // Remove photo (if one is set)
                if (selectedPhotoUri != null || buildPhotoUrl(existingPhotoUrl) != null) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(14.dp))
                            .background(Color(0xFFEF4444).copy(alpha = 0.08f))
                            .clickable {
                                showPhotoSheet = false
                                selectedPhotoUri = null
                                selectedPhotoFile = null
                            }
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(44.dp)
                                .clip(CircleShape)
                                .background(Color(0xFFEF4444).copy(alpha = 0.1f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.Delete, null, tint = Color(0xFFEF4444), modifier = Modifier.size(22.dp))
                        }
                        Text("Hapus Foto", fontWeight = FontWeight.SemiBold, color = Color(0xFFEF4444), fontSize = 15.sp)
                    }
                }
            }
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(bgColor)) {
        Column(modifier = Modifier.fillMaxSize()) {

            // ── Top Navigation Bar ──────────────────────────────────────────
            Surface(
                color = surfaceColor.copy(alpha = 0.92f),
                shadowElevation = 0.dp,
                tonalElevation = 0.dp
            ) {
                Column(modifier = Modifier.fillMaxWidth()) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp)
                            .statusBarsPadding()
                            .padding(top = 8.dp, bottom = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .background(PetPrimary.copy(alpha = 0.1f))
                                .clickable(onClick = onNavigateBack),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.ArrowBackIosNew, "Back", tint = PetPrimary, modifier = Modifier.size(18.dp))
                        }
                        Text(title, fontSize = 17.sp, fontWeight = FontWeight.Bold,
                            color = textPrimary, modifier = Modifier.weight(1f), textAlign = TextAlign.Center)
                        Box(modifier = Modifier.size(40.dp))
                    }
                    Divider(color = borderColor, thickness = 1.dp)
                }
            }

            // ── Scrollable Content ──────────────────────────────────────────
            Column(
                modifier = Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState())
                    .padding(bottom = 100.dp)
            ) {
                // ── Photo Upload Area ─────────────────────────────────────
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 24.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Box(
                        modifier = Modifier
                            .size(220.dp)
                            .clip(RoundedCornerShape(24.dp))
                            .background(if (isDark) Color(0xFF1E3526) else Color(0xFFEFF4F0))
                            .border(
                                width = if (selectedPhotoUri != null) 3.dp else 2.dp,
                                color = if (selectedPhotoUri != null) PetPrimary else PetPrimary.copy(alpha = 0.4f),
                                shape = RoundedCornerShape(24.dp)
                            )
                            .clickable { showPhotoSheet = true },
                        contentAlignment = Alignment.Center                    ) {
                        // Show: newly selected local URI > existing remote photo > nothing
                        val existingFullUrl = buildPhotoUrl(existingPhotoUrl)
                        val hasPhoto = selectedPhotoUri != null || existingFullUrl != null
                        var photoFailed by remember(selectedPhotoUri, existingFullUrl) { mutableStateOf(false) }

                        if (hasPhoto && !photoFailed) {
                            // If user picked a new photo, show local URI; otherwise show remote URL.
                            // Decode dibatasi 600px (preview hanya 220dp) agar tidak makan memori.
                            val imageModel: Any = selectedPhotoUri ?: existingFullUrl.orEmpty()
                            AsyncImage(
                                model = ImageRequest.Builder(context)
                                    .data(imageModel)
                                    .memoryCachePolicy(CachePolicy.ENABLED)
                                    .diskCachePolicy(CachePolicy.ENABLED)
                                    .size(600)
                                    .crossfade(200)
                                    .build(),
                                contentDescription = "Foto hewan",
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(RoundedCornerShape(24.dp)),
                                contentScale = ContentScale.Crop,
                                onError = { photoFailed = true }
                            )
                            // Edit overlay badge
                            Box(
                                modifier = Modifier
                                    .align(Alignment.BottomEnd)
                                    .padding(10.dp)
                                    .size(36.dp)
                                    .clip(CircleShape)
                                    .background(PetPrimary),
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(Icons.Filled.Edit, null, tint = Color(0xFFF6F8F6), modifier = Modifier.size(18.dp))
                            }
                        } else {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(64.dp)
                                        .clip(CircleShape)
                                        .background(PetPrimary.copy(alpha = 0.2f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(Icons.Filled.CameraAlt, null, tint = PetPrimary, modifier = Modifier.size(32.dp))
                                }
                                Text("Unggah Foto Hewan", fontSize = 13.sp,
                                    fontWeight = FontWeight.SemiBold, color = textMuted)
                                Text("Kamera atau Galeri", fontSize = 11.sp, color = textMuted.copy(alpha = 0.7f))
                            }
                        }
                        // Overlay saat foto sedang diproses di background.
                        if (isProcessingPhoto) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(RoundedCornerShape(24.dp))
                                    .background(Color.Black.copy(alpha = 0.35f)),
                                contentAlignment = Alignment.Center
                            ) {
                                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                    CircularProgressIndicator(
                                        color = Color.White,
                                        strokeWidth = 3.dp,
                                        modifier = Modifier.size(36.dp)
                                    )
                                    Spacer(Modifier.height(8.dp))
                                    Text(
                                        "Memproses foto...",
                                        color = Color.White,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Medium
                                    )
                                }
                            }
                        }
                    }
                }

                // Peringatan dini bila file masih melebihi batas backend (4096KB)
                // — kompresi otomatis di atas seharusnya sudah mencegahnya.
                val photoSizeKB = remember(selectedPhotoFile) {
                    selectedPhotoFile?.takeIf { it.exists() }?.length()?.div(1024) ?: 0L
                }
                if (photoSizeKB > 4096) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 24.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .background(Color(0xFFEF4444).copy(alpha = 0.1f))
                            .padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(Icons.Filled.Warning, null, tint = Color(0xFFEF4444), modifier = Modifier.size(18.dp))
                        Text(
                            "Ukuran foto terlalu besar (${photoSizeKB / 1024}MB). Pilih foto lain atau ulangi pengambilan gambar.",
                            color = Color(0xFFEF4444), fontSize = 13.sp
                        )
                    }
                }

                // ── Form Fields ───────────────────────────────────────────
                Column(
                    modifier = Modifier.padding(horizontal = 24.dp),
                    verticalArrangement = Arrangement.spacedBy(20.dp)
                ) {
                    // Error
                    if (error != null) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .background(Color(0xFFEF4444).copy(alpha = 0.1f))
                                .padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(Icons.Filled.Warning, null, tint = Color(0xFFEF4444), modifier = Modifier.size(18.dp))
                            Text(error, color = Color(0xFFEF4444), fontSize = 13.sp)
                        }
                    }

                    // Pet Name
                    FormSection(label = "PET NAME") {
                        PetTextField(value = name, onValueChange = { name = it },
                            placeholder = "e.g. Buddy",
                            surfaceColor = surfaceColor, textPrimary = textPrimary,
                            textMuted = textMuted, borderColor = borderColor, imeAction = ImeAction.Next)
                    }

                    // Species & Gender Row
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                        // Species Dropdown
                        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("SPECIES", fontSize = 11.sp, fontWeight = FontWeight.Bold,
                                color = textMuted, letterSpacing = 0.8.sp)
                            Box {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(12.dp))
                                        .border(1.dp, borderColor, RoundedCornerShape(12.dp))
                                        .background(surfaceColor)
                                        .clickable { expandSpecies = true }
                                        .padding(horizontal = 16.dp, vertical = 14.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    // PHASE 7: show the typed value once Other is specified.
                                    val speciesLabel = when {
                                        species == "Other" && customSpecies.isNotBlank() -> customSpecies
                                        species == "Other" -> "Other…"
                                        species.isBlank() -> "Select"
                                        else -> species
                                    }
                                    Text(speciesLabel, fontSize = 14.sp,
                                        color = if (species.isBlank()) textMuted else textPrimary,
                                        maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    Icon(Icons.Filled.ExpandMore, null, tint = textMuted, modifier = Modifier.size(20.dp))
                                }
                                DropdownMenu(expanded = expandSpecies,
                                    onDismissRequest = { expandSpecies = false },
                                    modifier = Modifier.background(surfaceColor)) {
                                    speciesOptions.forEach { option ->
                                        DropdownMenuItem(
                                            text = { Text(option, color = textPrimary, fontSize = 14.sp) },
                                            onClick = { species = option; expandSpecies = false }
                                        )
                                    }
                                }
                            }
                            // PHASE 7: free-text species for "Other" (e.g. Hamster,
                            // Iguana). Sent as-is; backend accepts any string ≤100.
                            AnimatedVisibility(
                                visible = species == "Other",
                                enter = fadeIn(animationSpec = tween(200)) +
                                    slideInVertically(animationSpec = tween(200)) { it / 3 },
                            ) {
                                PetTextField(
                                    value = customSpecies,
                                    onValueChange = { customSpecies = it.take(100) },
                                    placeholder = "Tulis jenis hewan…",
                                    surfaceColor = surfaceColor,
                                    textPrimary = textPrimary,
                                    textMuted = textMuted,
                                    borderColor = borderColor,
                                    imeAction = ImeAction.Next
                                )
                            }
                        }

                        // Gender Toggle
                        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("GENDER", fontSize = 11.sp, fontWeight = FontWeight.Bold,
                                color = textMuted, letterSpacing = 0.8.sp)
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(12.dp))
                                    .border(1.dp, borderColor, RoundedCornerShape(12.dp))
                                    .background(surfaceColor)
                                    .padding(4.dp),
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                listOf("Male", "Female").forEach { g ->
                                    val isSelected = gender == g
                                    Box(
                                        modifier = Modifier
                                            .weight(1f)
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(if (isSelected) PetPrimary else Color.Transparent)
                                            .clickable { gender = g }
                                            .padding(vertical = 8.dp),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text(g, fontSize = 12.sp, fontWeight = FontWeight.Bold,
                                            color = if (isSelected) Color(0xFFF6F8F6) else textMuted)
                                    }
                                }
                            }
                        }
                    }

                    // Breed
                    FormSection(label = "BREED") {
                        PetTextField(value = breed, onValueChange = { breed = it },
                            placeholder = "e.g. Golden Retriever",
                            surfaceColor = surfaceColor, textPrimary = textPrimary,
                            textMuted = textMuted, borderColor = borderColor, imeAction = ImeAction.Next)
                    }

                    // Weight
                    FormSection(label = "WEIGHT (KG)") {
                        PetTextField(
                            value = weight,
                            onValueChange = { weight = it },
                            placeholder = "e.g. 5.5",
                            surfaceColor = surfaceColor,
                            textPrimary = textPrimary,
                            textMuted = textMuted,
                            borderColor = borderColor,
                            keyboardType = KeyboardType.Decimal,
                            imeAction = ImeAction.Next
                        )
                    }

                    // Date of Birth — tappable calendar picker
                    FormSection(label = "DATE OF BIRTH") {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .border(1.dp, borderColor, RoundedCornerShape(12.dp))
                                .background(surfaceColor)
                                .clickable { showDatePicker = true }
                                .padding(horizontal = 16.dp, vertical = 15.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = if (dateOfBirth.isBlank()) "Select date of birth"
                                       else {
                                           // Pretty-print: "15 Jan 2022"
                                           runCatching {
                                               val parts = dateOfBirth.split("-")
                                               val months = listOf("","Jan","Feb","Mar","Apr","May","Jun",
                                                   "Jul","Aug","Sep","Oct","Nov","Dec")
                                               "${parts[2].toInt()} ${months[parts[1].toInt()]} ${parts[0]}"
                                           }.getOrDefault(dateOfBirth)
                                       },
                                fontSize = 14.sp,
                                color = if (dateOfBirth.isBlank()) textMuted else textPrimary
                            )
                            Icon(
                                Icons.Filled.CalendarMonth,
                                contentDescription = "Pick date",
                                tint = PetPrimary,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                    }

                    // Info box
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(16.dp))
                            .background(PetPrimary.copy(alpha = 0.05f))
                            .border(1.dp, PetPrimary.copy(alpha = 0.2f), RoundedCornerShape(16.dp))
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Icon(Icons.Filled.Info, null, tint = PetPrimary, modifier = Modifier.size(20.dp))
                        Text("Adding accurate info helps our AI provide better health recommendations for your pet.",
                            fontSize = 12.sp, color = textMuted, lineHeight = 18.sp, modifier = Modifier.weight(1f))
                    }
                }
            }
        }

        // ── Fixed Bottom Action Bar ─────────────────────────────────────────
        Box(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .background(bgColor.copy(alpha = 0.95f))
                .border(width = 1.dp, color = borderColor, shape = RoundedCornerShape(topStart = 0.dp, topEnd = 0.dp))
                .padding(horizontal = 24.dp, vertical = 20.dp)
        ) {
            // PHASE 7: "Other" submits the typed value; blank custom blocks.
            val effectiveSpecies =
                if (species == "Other") customSpecies.trim() else species.trim()
            val isSpeciesValid = effectiveSpecies.isNotBlank()
            val isPhotoTooLarge = (selectedPhotoFile?.takeIf { it.exists() }?.length() ?: 0L) > 4096L * 1024L
            Button(
                onClick = {
                    if (isPhotoTooLarge || isProcessingPhoto) return@Button
                    focusManager.clearFocus()
                    onSubmit(
                        name.trim(),
                        effectiveSpecies,
                        breed.trim().ifBlank { null },
                        gender.lowercase(),
                        dateOfBirth.trim().ifBlank { null },
                        null, // age
                        weight.trim().ifBlank { null }?.toDoubleOrNull(),
                        selectedPhotoFile
                    )
                },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(16.dp),
                enabled = !isLoading && name.isNotBlank() && isSpeciesValid && !isPhotoTooLarge && !isProcessingPhoto,
                colors = ButtonDefaults.buttonColors(
                    containerColor = PetPrimary,
                    contentColor = Color(0xFFF6F8F6),
                    disabledContainerColor = PetPrimary.copy(alpha = 0.4f),
                    disabledContentColor = Color(0xFFF6F8F6).copy(alpha = 0.5f)
                ),
                elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
            ) {
                if (isLoading) {
                    CircularProgressIndicator(modifier = Modifier.size(22.dp),
                        color = Color(0xFFF6F8F6), strokeWidth = 2.5.dp)
                } else {
                    Icon(Icons.Filled.Pets, null, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(submitLabel, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun FormSection(
    label: String,
    content: @Composable () -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            label,
            fontSize = 11.sp,
            fontWeight = FontWeight.Bold,
            color = if (false) Color(0xFF94A3B8) else Color(0xFF61896F),
            letterSpacing = 0.8.sp
        )
        content()
    }
}

@Composable
private fun PetTextField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    surfaceColor: Color,
    textPrimary: Color,
    textMuted: Color,
    borderColor: Color,
    keyboardType: KeyboardType = KeyboardType.Text,
    imeAction: ImeAction = ImeAction.Next,
    onImeAction: (() -> Unit)? = null,
    trailingIcon: (@Composable () -> Unit)? = null
) {
    val focusManager = LocalFocusManager.current
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        placeholder = { Text(placeholder, color = textMuted, fontSize = 14.sp) },
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        singleLine = true,
        trailingIcon = trailingIcon,
        keyboardOptions = KeyboardOptions(
            keyboardType = keyboardType,
            imeAction = imeAction,
            capitalization = if (keyboardType == KeyboardType.Text) KeyboardCapitalization.Words else KeyboardCapitalization.None
        ),
        keyboardActions = KeyboardActions(
            onNext = { onImeAction?.invoke() ?: focusManager.moveFocus(FocusDirection.Down) },
            onDone = { onImeAction?.invoke() ?: focusManager.clearFocus() }
        ),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor   = PetPrimary,
            unfocusedBorderColor = borderColor,
            focusedContainerColor   = surfaceColor,
            unfocusedContainerColor = surfaceColor,
            focusedTextColor   = textPrimary,
            unfocusedTextColor = textPrimary,
            cursorColor = PetPrimary        )
    )
}

// PHASE 9: bottom navigation terpusat di
// ui.components.PetHealFloatingBottomNav (5 tab Indonesia, floating).

