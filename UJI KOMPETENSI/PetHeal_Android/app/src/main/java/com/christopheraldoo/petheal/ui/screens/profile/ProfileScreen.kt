package com.christopheraldoo.petheal.ui.screens.profile

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.util.MediumImage
import com.christopheraldoo.petheal.util.buildPhotoUrl
import com.christopheraldoo.petheal.util.compressImageFile
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

// ─── Brand tokens ─────────────────────────────────────────────────────────────
private val Primary       = Color(0xFF18C964)
private val PrimaryFg     = Color(0xFF052E14)
private val BgDark        = Color(0xFFF6F8F6)
private val SurfaceDark   = Color.White
private val BorderDark    = Color(0xFFE2E8F0)
private val TextPrimary   = Color(0xFF0F172A)
private val TextSecDark   = Color(0xFF64748B)

// ─── ProfileScreen ─────────────────────────────────────────────────────────────
@Composable
fun ProfileScreen(
    onNavigateBack: () -> Unit,
    onNavigateToEdit: () -> Unit,
    onLogout: () -> Unit,
    onNavigateToNotifications: () -> Unit,
    onNavigateToPrivacy: () -> Unit,
    onNavigateToHelp: () -> Unit,
    onNavigateToAbout: () -> Unit,
    onTabSelected: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = {},
    viewModel: ProfileViewModel = hiltViewModel()
) {
    val state by viewModel.profileState.collectAsState()
    var showLogoutDialog by remember { mutableStateOf(false) }
    // PHASE 7: two-step verified deletion (anti-kepencet).
    var showDeleteDialog by remember { mutableStateOf(false) }
    var deleteStep by remember { mutableStateOf(1) }
    var deleteUnderstood by remember { mutableStateOf(false) }
    var deleteConfirmText by remember { mutableStateOf("") }

    // ── Ganti foto profil (badge kamera) ──────────────────────────────────
    // Alur sama seperti form hewan: kamera/galeri → kompres background
    // (batas backend 4096KB) → upload otomatis.
    val context = LocalContext.current
    var showPhotoSheet by remember { mutableStateOf(false) }
    var pendingLaunch by remember { mutableStateOf<String?>(null) } // "camera" | "gallery"
    var previewPhotoUri by remember { mutableStateOf<Uri?>(null) }
    var isProcessingPhoto by remember { mutableStateOf(false) }
    var cameraUri by remember { mutableStateOf<Uri?>(null) }
    var cameraFile by remember { mutableStateOf<File?>(null) }
    val photoScope = rememberCoroutineScope()

    fun processAndUpload(rawFileProvider: () -> File?) {
        isProcessingPhoto = true
        photoScope.launch(Dispatchers.Default) {
            val compressed = try {
                val raw = rawFileProvider()
                if (raw != null) compressImageFile(context, raw) ?: raw else null
            } catch (_: Exception) { null }
            withContext(Dispatchers.Main) {
                isProcessingPhoto = false
                if (compressed != null && compressed.exists()) {
                    viewModel.uploadProfilePhoto(compressed)
                }
            }
        }
    }

    val galleryLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.GetContent()
    ) { uri: Uri? ->
        if (uri != null) {
            previewPhotoUri = uri
            processAndUpload { copyUriToFile(context, uri) }
        }
    }

    val cameraLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.TakePicture()
    ) { success: Boolean ->
        if (success && cameraUri != null) {
            previewPhotoUri = cameraUri
            val captured = cameraFile
            processAndUpload { captured }
        }
    }

    val cameraPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (granted) {
            val (uri, file) = createProfileImageFile(context)
            cameraUri = uri; cameraFile = file
            cameraLauncher.launch(uri)
        }
    }

    val storagePermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (granted) galleryLauncher.launch("image/*")
    }

    fun launchCamera() {
        if (ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) ==
            PackageManager.PERMISSION_GRANTED
        ) {
            val (uri, file) = createProfileImageFile(context)
            cameraUri = uri; cameraFile = file
            cameraLauncher.launch(uri)
        } else {
            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    fun launchGallery() {
        val storagePermission = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            Manifest.permission.READ_MEDIA_IMAGES
        } else {
            Manifest.permission.READ_EXTERNAL_STORAGE
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, storagePermission) ==
            PackageManager.PERMISSION_GRANTED
        ) {
            galleryLauncher.launch("image/*")
        } else {
            storagePermissionLauncher.launch(storagePermission)
        }
    }

    // Sheet harus tertutup penuh dulu sebelum kamera dibuka (pola form hewan).
    LaunchedEffect(showPhotoSheet) {
        if (!showPhotoSheet && pendingLaunch != null) {
            kotlinx.coroutines.delay(150)
            when (pendingLaunch) {
                "camera" -> launchCamera()
                "gallery" -> launchGallery()
            }
            pendingLaunch = null
        }
    }

    // Preview lokal dibersihkan setelah upload selesai (sukses pakai URL baru,
    // gagal kembali ke foto lama + snackbar error yang sudah ada).
    LaunchedEffect(state.isUploadingPhoto) {
        if (!state.isUploadingPhoto && state.error == null && state.photoMessage != null) {
            previewPhotoUri = null
        }
    }
    LaunchedEffect(state.error) {
        if (state.error != null) previewPhotoUri = null
    }

    // Logout confirmation dialog
    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            containerColor = SurfaceDark,
            titleContentColor = TextPrimary,
            textContentColor = TextSecDark,
            title = { Text("Keluar", fontWeight = FontWeight.Bold) },
            text = { Text("Yakin ingin keluar dari akun Anda?") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showLogoutDialog = false
                        viewModel.logout(onLoggedOut = onLogout)
                    }
                ) {
                    Text("Keluar", color = Color(0xFFFF6B6B), fontWeight = FontWeight.SemiBold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showLogoutDialog = false }) {
                    Text("Batal", color = Primary)
                }
            }
        )
    }

    // Delete-account dialog: step 1 = consequences + checklist,
    // step 2 = type HAPUS. Both gates must pass; nothing fires by tap alone.
    if (showDeleteDialog) {
        val busy = state.isDeleting
        AlertDialog(
            onDismissRequest = {
                if (!busy) {
                    showDeleteDialog = false
                    deleteStep = 1
                    deleteUnderstood = false
                    deleteConfirmText = ""
                }
            },
            containerColor = SurfaceDark,
            titleContentColor = TextPrimary,
            textContentColor = TextSecDark,
            title = { Text("Hapus Akun Permanen", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    if (deleteStep == 1) {
                        Text(
                            "Tindakan ini menghapus akun Anda secara permanen, " +
                                "termasuk seluruh data hewan, booking, dan riwayat medis. " +
                                "Data yang terhapus tidak dapat dikembalikan.",
                            fontSize = 13.sp,
                            lineHeight = 19.sp
                        )
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable(enabled = !busy) {
                                    deleteUnderstood = !deleteUnderstood
                                }
                        ) {
                            Checkbox(
                                checked = deleteUnderstood,
                                onCheckedChange = { deleteUnderstood = it },
                                enabled = !busy
                            )
                            Spacer(Modifier.width(8.dp))
                            Text(
                                "Saya memahami dan tetap ingin menghapus akun",
                                fontSize = 13.sp
                            )
                        }
                    } else {
                        Text(
                            "Langkah terakhir: ketik HAPUS (huruf kapital semua) " +
                                "untuk mengonfirmasi.",
                            fontSize = 13.sp,
                            lineHeight = 19.sp
                        )
                        OutlinedTextField(
                            value = deleteConfirmText,
                            onValueChange = { deleteConfirmText = it },
                            singleLine = true,
                            enabled = !busy,
                            placeholder = { Text("Ketik HAPUS") },
                            keyboardOptions = KeyboardOptions(
                                capitalization = KeyboardCapitalization.Characters,
                                keyboardType = KeyboardType.Text
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                }
            },
            confirmButton = {
                if (deleteStep == 1) {
                    TextButton(
                        enabled = deleteUnderstood && !busy,
                        onClick = { deleteStep = 2 }
                    ) {
                        Text("Lanjutkan", fontWeight = FontWeight.SemiBold)
                    }
                } else {
                    TextButton(
                        enabled = deleteConfirmText.trim() == "HAPUS" && !busy,
                        onClick = {
                            viewModel.deleteAccount(onDeleted = onLogout)
                        },
                        colors = ButtonDefaults.textButtonColors(
                            contentColor = Color(0xFFDC2626)
                        )
                    ) {
                        if (busy) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                strokeWidth = 2.dp,
                                color = Color(0xFFDC2626)
                            )
                        } else {
                            Text("Ya, Hapus Akun Saya", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            },
            dismissButton = {
                TextButton(
                    enabled = !busy,
                    onClick = {
                        showDeleteDialog = false
                        deleteStep = 1
                        deleteUnderstood = false
                        deleteConfirmText = ""
                    }
                ) {
                    Text("Batal", color = Primary)
                }
            }
        )
    }

    // ── Photo source sheet (gaya sama seperti form hewan) ───────────────
    if (showPhotoSheet) {
        ProfilePhotoSheet(
            onCamera = { pendingLaunch = "camera"; showPhotoSheet = false },
            onGallery = { pendingLaunch = "gallery"; showPhotoSheet = false },
            onDismiss = { showPhotoSheet = false }
        )
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(BgDark)
    ) {
        Column(modifier = Modifier.fillMaxSize()) {

            // ── Hero Header ──────────────────────────────────────────────────
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(SurfaceDark, BgDark)
                        )
                    )
                    .padding(top = 48.dp, bottom = 28.dp, start = 20.dp, end = 20.dp)
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    // Avatar (ketuk badge kamera untuk ganti foto)
                    val isPhotoBusy = isProcessingPhoto || state.isUploadingPhoto
                    Box(contentAlignment = Alignment.BottomEnd) {
                        Box(
                            modifier = Modifier
                                .size(96.dp)
                                .clip(CircleShape)
                                .border(3.dp, Primary, CircleShape)
                                .background(BorderDark),
                            contentAlignment = Alignment.Center
                        ) {
                            val preview = previewPhotoUri
                            val photo = buildPhotoUrl(state.user?.photo)
                            if (preview != null) {
                                MediumImage(
                                    model = preview,
                                    contentDescription = "Pratinjau foto profil",
                                    contentScale = ContentScale.Crop,
                                    modifier = Modifier.fillMaxSize().clip(CircleShape)
                                )
                            } else if (!photo.isNullOrBlank()) {
                                MediumImage(
                                    model = photo,
                                    contentDescription = "Profile Photo",
                                    contentScale = ContentScale.Crop,
                                    modifier = Modifier.fillMaxSize().clip(CircleShape)
                                )
                            } else {
                                Icon(
                                    imageVector = Icons.Filled.Person,
                                    contentDescription = null,
                                    tint = TextSecDark,
                                    modifier = Modifier.size(48.dp)
                                )
                            }
                            if (isPhotoBusy) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxSize()
                                        .clip(CircleShape)
                                        .background(Color.Black.copy(alpha = 0.45f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    CircularProgressIndicator(
                                        color = Color.White,
                                        strokeWidth = 3.dp,
                                        modifier = Modifier.size(28.dp)
                                    )
                                }
                            }
                        }
                        // Camera badge — kini fungsional.
                        Box(
                            modifier = Modifier
                                .size(32.dp)
                                .clip(CircleShape)
                                .background(if (isPhotoBusy) TextSecDark else Primary)
                                .border(2.dp, BgDark, CircleShape)
                                .clickable(enabled = !isPhotoBusy) { showPhotoSheet = true },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Filled.CameraAlt,
                                contentDescription = "Ganti foto profil",
                                tint = PrimaryFg,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }

                    Spacer(Modifier.height(12.dp))

                    // Name
                    if (state.isLoading) {
                        Box(
                            modifier = Modifier
                                .width(140.dp)
                                .height(20.dp)
                                .clip(RoundedCornerShape(4.dp))
                                .background(BorderDark)
                        )
                        Spacer(Modifier.height(8.dp))
                        Box(
                            modifier = Modifier
                                .width(180.dp)
                                .height(14.dp)
                                .clip(RoundedCornerShape(4.dp))
                                .background(BorderDark)
                        )
                    } else {
                        Text(
                            text = state.user?.name ?: "—",
                            color = TextPrimary,
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = state.user?.email ?: "",
                            color = TextSecDark,
                            fontSize = 13.sp
                        )
                    }

                    Spacer(Modifier.height(16.dp))

                    // Edit Profile button
                    Button(
                        onClick = onNavigateToEdit,
                        shape = RoundedCornerShape(18.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Primary,
                            contentColor = Color.White
                        ),
                        contentPadding = PaddingValues(horizontal = 24.dp, vertical = 10.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Edit,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(Modifier.width(6.dp))
                        Text("Ubah Profil", fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                    }

                    Spacer(Modifier.height(16.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        ProfileTrustPill(
                            modifier = Modifier.weight(1f),
                            icon = Icons.Outlined.VerifiedUser,
                            label = "Secure Account"
                        )
                        ProfileTrustPill(
                            modifier = Modifier.weight(1f),
                            icon = Icons.Outlined.SupportAgent,
                            label = "Support Ready"
                        )
                    }
                }
            }

            // ── Body ─────────────────────────────────────────────────────────
            Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp)
                .padding(bottom = 110.dp)
        ) {
                Spacer(Modifier.height(16.dp))

                // Account section
                ProfileSectionCard(title = "Informasi Profil") {
                    ProfileInfoRow(
                        icon = Icons.Outlined.Person,
                        label = "Nama Lengkap",
                        value = state.user?.name ?: "—"
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileInfoRow(
                        icon = Icons.Outlined.Email,
                        label = "Email",
                        value = state.user?.email ?: "—"
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileInfoRow(
                        icon = Icons.Outlined.Phone,
                        label = "Telepon",
                        value = state.user?.phone?.takeIf { it.isNotBlank() } ?: "Belum diisi"
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileInfoRow(
                        icon = Icons.Outlined.Shield,
                        label = "Tipe Akun",
                        value = state.user?.role?.replaceFirstChar { it.uppercaseChar() } ?: "User"
                    )
                }

                Spacer(Modifier.height(16.dp))

                // App section
                ProfileSectionCard(title = "Pengaturan Akun") {
                    ProfileActionRow(
                        icon = Icons.Outlined.Notifications,
                        label = "Notifikasi",
                        onClick = onNavigateToNotifications
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileActionRow(
                        icon = Icons.Outlined.Lock,
                        label = "Privasi & Keamanan",
                        onClick = onNavigateToPrivacy
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileActionRow(
                        icon = Icons.Outlined.HelpOutline,
                        label = "Bantuan & Dukungan",
                        onClick = onNavigateToHelp
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileActionRow(
                        icon = Icons.Outlined.Info,
                        label = "Tentang Aplikasi",
                        onClick = onNavigateToAbout
                    )
                }

                Spacer(Modifier.height(24.dp))

                // Logout button
                OutlinedButton(
                    onClick = { showLogoutDialog = true },
                    shape = RoundedCornerShape(18.dp),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Color(0xFFFF6B6B)
                    ),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFFCA5A5)),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp)
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Logout,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(Modifier.width(8.dp))
                    Text("Keluar", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                }

                Spacer(Modifier.height(12.dp))

                // PHASE 7: danger zone — verified two-step deletion.
                OutlinedButton(
                    onClick = {
                        deleteStep = 1
                        deleteUnderstood = false
                        deleteConfirmText = ""
                        showDeleteDialog = true
                    },
                    shape = RoundedCornerShape(18.dp),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Color(0xFFDC2626)
                    ),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFFECACA)),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp)
                ) {
                    Icon(
                        imageVector = Icons.Filled.DeleteForever,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(Modifier.width(8.dp))
                    Text("Hapus Akun", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                }
                Text(
                    "Menghapus seluruh data Anda secara permanen",
                    fontSize = 12.sp,
                    color = TextSecDark,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 8.dp),
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center
                )
            }
        }

        // Error snackbar (lifted above the floating nav)
        state.error?.let { err ->
            Snackbar(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(start = 16.dp, end = 16.dp, bottom = 110.dp),
                containerColor = Color(0xFFFFEBEE),
                contentColor = Color(0xFFDC2626),
                dismissAction = {
                    IconButton(onClick = { viewModel.clearError() }) {
                        Icon(Icons.Filled.Close, contentDescription = "Tutup", tint = Color(0xFFDC2626))
                    }
                }
            ) {
                Text(err)
            }
        }

        // Success snackbar untuk upload foto profil
        state.photoMessage?.let { msg ->
            Snackbar(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(start = 16.dp, end = 16.dp, bottom = 110.dp),
                containerColor = Color(0xFFE8FFF1),
                contentColor = Color(0xFF047857),
                dismissAction = {
                    IconButton(onClick = { viewModel.clearPhotoMessage() }) {
                        Icon(Icons.Filled.Close, contentDescription = "Tutup", tint = Color(0xFF047857))
                    }
                }
            ) {
                Text(msg)
            }
        }

        // ── Unified floating bottom nav (PHASE 9) ──────────────────────
        com.christopheraldoo.petheal.ui.components.PetHealFloatingBottomNav(
            selected = com.christopheraldoo.petheal.ui.components.PetHealTab.Profile,
            onSelect = onTabSelected,
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }
}

// ─── EditProfileScreen ────────────────────────────────────────────────────────
@Composable
fun EditProfileScreen(
    onNavigateBack: () -> Unit,
    onProfileUpdated: () -> Unit,
    viewModel: ProfileViewModel = hiltViewModel()
) {
    val state by viewModel.editState.collectAsState()

    // Navigate back on success
    LaunchedEffect(state.isSuccess) {
        if (state.isSuccess) {
            viewModel.clearEditSuccess()
            onProfileUpdated()
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(BgDark)
    ) {
        Column(modifier = Modifier.fillMaxSize()) {

            // ── Top Bar ──────────────────────────────────────────────────────
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(SurfaceDark)
                    .padding(top = 44.dp, start = 8.dp, end = 20.dp, bottom = 14.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    IconButton(onClick = onNavigateBack) {
                        Icon(
                            imageVector = Icons.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = TextPrimary
                        )
                    }
                    Spacer(Modifier.width(4.dp))
                    Text(
                        text = "Edit Profile",
                        color = TextPrimary,
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            // ── Form ─────────────────────────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .imePadding()
                    .padding(horizontal = 20.dp)
                    .padding(top = 28.dp, bottom = 40.dp),
                verticalArrangement = Arrangement.spacedBy(18.dp)
            ) {
                // Name field (read-only)
                EditField(
                    label = "Nama Lengkap",
                    value = state.name,
                    onValueChange = {},
                    placeholder = state.name,
                    leadingIcon = Icons.Outlined.Person,
                    readOnly = true,
                    keyboardOptions = KeyboardOptions(
                        capitalization = KeyboardCapitalization.Words
                    )
                )

                // Email field (read-only)
                EditField(
                    label = "Email",
                    value = state.email,
                    onValueChange = {},
                    placeholder = state.email,
                    leadingIcon = Icons.Outlined.Email,
                    readOnly = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email)
                )

                // Phone field
                EditField(
                    label = "Nomor Telepon",
                    value = state.phone,
                    onValueChange = viewModel::onPhoneChange,
                    placeholder = "Masukkan nomor telepon",
                    leadingIcon = Icons.Outlined.Phone,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone)
                )

                // Error message
                state.error?.let { err ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(Color(0xFFFFEBEE))
                            .padding(12.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.ErrorOutline,
                            contentDescription = null,
                            tint = Color(0xFFDC2626),
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(Modifier.width(8.dp))
                        Text(err, color = Color(0xFFDC2626), fontSize = 13.sp)
                    }
                }

                Spacer(Modifier.height(4.dp))

                // Save button
                Button(
                    onClick = viewModel::updateProfile,
                    enabled = !state.isLoading,
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Primary,
                        contentColor = Color.White,
                        disabledContainerColor = BorderDark,
                        disabledContentColor = TextSecDark
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp)
                ) {
                    if (state.isLoading) {
                        CircularProgressIndicator(
                            color = Color.White,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(20.dp)
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Filled.Save,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(Modifier.width(8.dp))
                        Text("Simpan Perubahan", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    }
                }
            }
        }
    }
}

// ─── Shared sub-composables ───────────────────────────────────────────────────

@Composable
private fun ProfileTrustPill(
    modifier: Modifier = Modifier,
    icon: ImageVector,
    label: String
) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(18.dp),
        color = Color.White,
        border = BorderStroke(1.dp, BorderDark)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.Center
        ) {
            Icon(icon, contentDescription = null, tint = Primary, modifier = Modifier.size(17.dp))
            Spacer(Modifier.width(6.dp))
            Text(label, color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun ProfileSectionCard(
    title: String,
    content: @Composable ColumnScope.() -> Unit
) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = title.uppercase(),
            color = TextSecDark,
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            letterSpacing = 1.2.sp,
            modifier = Modifier.padding(start = 4.dp, bottom = 8.dp)
        )
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(24.dp))
                .background(SurfaceDark)
                .border(1.dp, BorderDark, RoundedCornerShape(24.dp))
        ) {
            content()
        }
    }
}

@Composable
private fun ProfileInfoRow(
    icon: ImageVector,
    label: String,
    value: String
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 14.dp)
    ) {
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(RoundedCornerShape(14.dp))
                .background(Primary.copy(alpha = 0.10f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = Primary, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(label, color = TextSecDark, fontSize = 11.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(2.dp))
            Text(value, color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun ProfileActionRow(
    icon: ImageVector,
    label: String,
    onClick: () -> Unit
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 14.dp)
    ) {
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(RoundedCornerShape(14.dp))
                .background(Primary.copy(alpha = 0.10f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = Primary, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(label, color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
        Icon(
            imageVector = Icons.Filled.ChevronRight,
            contentDescription = null,
            tint = TextSecDark,
            modifier = Modifier.size(20.dp)
        )
    }
}

@Composable
private fun EditField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    leadingIcon: ImageVector,
    keyboardOptions: KeyboardOptions = KeyboardOptions.Default,
    readOnly: Boolean = false
) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = label,
            color = TextSecDark,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(start = 2.dp, bottom = 6.dp)
        )
        OutlinedTextField(
            value = value,
            onValueChange = onValueChange,
            placeholder = { Text(placeholder, color = TextSecDark, fontSize = 14.sp) },
            leadingIcon = {
                Icon(leadingIcon, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(18.dp))
            },
            keyboardOptions = keyboardOptions,
            readOnly = readOnly,
            enabled = !readOnly,
            singleLine = true,
            shape = RoundedCornerShape(14.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary,
                focusedBorderColor = Primary,
                unfocusedBorderColor = BorderDark,
                cursorColor = Primary,
                focusedContainerColor = SurfaceDark,
                unfocusedContainerColor = SurfaceDark,
                disabledTextColor = if (readOnly) TextSecDark else TextPrimary,
                disabledBorderColor = BorderDark,
                disabledLeadingIconColor = TextSecDark
            ),
            modifier = Modifier.fillMaxWidth()
        )
    }
}

// ── Helper: file sementara untuk kamera (pola sama seperti form hewan) ─────
private fun createProfileImageFile(context: Context): Pair<Uri, File> {
    val timeStamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
    val dir = File(context.getExternalFilesDir("Pictures"), "").also { it.mkdirs() }
    val file = File(dir, "PROFILE_${timeStamp}.jpg")
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    return Pair(uri, file)
}

// ── Helper: salin URI galeri ke file cache (kompresi dilakukan terpisah) ───
private fun copyUriToFile(context: Context, uri: Uri): File? {
    return try {
        val timeStamp = SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())
        val cacheFile = File(context.cacheDir, "profile_pick_${timeStamp}.jpg")
        context.contentResolver.openInputStream(uri)?.use { input ->
            FileOutputStream(cacheFile).use { output -> input.copyTo(output) }
        }
        cacheFile
    } catch (_: Exception) { null }
}

// ── Bottom sheet sumber foto (gaya sama seperti form hewan) ─────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProfilePhotoSheet(
    onCamera: () -> Unit,
    onGallery: () -> Unit,
    onDismiss: () -> Unit
) {
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        containerColor = SurfaceDark
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp)
                .padding(bottom = 40.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text(
                "Ganti Foto Profil",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = TextPrimary,
                modifier = Modifier.padding(bottom = 4.dp)
            )
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(BgDark)
                    .clickable(onClick = onCamera)
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(Primary.copy(alpha = 0.15f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(Icons.Filled.CameraAlt, null, tint = Primary, modifier = Modifier.size(22.dp))
                }
                Column {
                    Text("Ambil Foto", fontWeight = FontWeight.SemiBold, color = TextPrimary, fontSize = 15.sp)
                    Text("Gunakan kamera", color = TextSecDark, fontSize = 12.sp)
                }
            }
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(14.dp))
                    .background(BgDark)
                    .clickable(onClick = onGallery)
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(Primary.copy(alpha = 0.15f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(Icons.Filled.PhotoLibrary, null, tint = Primary, modifier = Modifier.size(22.dp))
                }
                Column {
                    Text("Pilih dari Galeri", fontWeight = FontWeight.SemiBold, color = TextPrimary, fontSize = 15.sp)
                    Text("Ambil dari foto Anda", color = TextSecDark, fontSize = 12.sp)
                }
            }
        }
    }
}
