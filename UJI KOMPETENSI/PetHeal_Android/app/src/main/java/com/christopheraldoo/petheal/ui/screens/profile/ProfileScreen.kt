package com.christopheraldoo.petheal.ui.screens.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.util.MediumImage
import com.christopheraldoo.petheal.util.buildPhotoUrl

// ─── Brand tokens ─────────────────────────────────────────────────────────────
private val Primary       = Color(0xFF2BEE6C)
private val PrimaryFg     = Color(0xFF052E14)
private val BgDark        = Color(0xFF102216)
private val SurfaceDark   = Color(0xFF1C2E22)
private val BorderDark    = Color(0xFF2E4536)
private val TextSecDark   = Color(0xFF9DB9A6)

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
    viewModel: ProfileViewModel = hiltViewModel()
) {
    val state by viewModel.profileState.collectAsState()
    var showLogoutDialog by remember { mutableStateOf(false) }

    // Logout confirmation dialog
    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            containerColor = SurfaceDark,
            titleContentColor = Color.White,
            textContentColor = TextSecDark,
            title = { Text("Log Out", fontWeight = FontWeight.Bold) },
            text = { Text("Are you sure you want to log out of your account?") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showLogoutDialog = false
                        viewModel.logout()
                        onLogout()
                    }
                ) {
                    Text("Log Out", color = Color(0xFFFF6B6B), fontWeight = FontWeight.SemiBold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showLogoutDialog = false }) {
                    Text("Cancel", color = Primary)
                }
            }
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
                    // Avatar
                    Box(contentAlignment = Alignment.BottomEnd) {
                        Box(
                            modifier = Modifier
                                .size(96.dp)
                                .clip(CircleShape)
                                .border(3.dp, Primary, CircleShape)
                                .background(BorderDark),
                            contentAlignment = Alignment.Center
                        ) {
                            val photo = buildPhotoUrl(state.user?.photo)
                            if (!photo.isNullOrBlank()) {
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
                        }
                        // Camera badge
                        Box(
                            modifier = Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(Primary)
                                .border(2.dp, BgDark, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Filled.CameraAlt,
                                contentDescription = null,
                                tint = PrimaryFg,
                                modifier = Modifier.size(14.dp)
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
                            color = Color.White,
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
                        shape = RoundedCornerShape(50),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Primary,
                            contentColor = PrimaryFg
                        ),
                        contentPadding = PaddingValues(horizontal = 24.dp, vertical = 10.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Edit,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(Modifier.width(6.dp))
                        Text("Edit Profile", fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                    }
                }
            }

            // ── Body ─────────────────────────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 20.dp)
                    .padding(bottom = 32.dp)
            ) {
                Spacer(Modifier.height(16.dp))

                // Account section
                ProfileSectionCard(title = "Account") {
                    ProfileInfoRow(
                        icon = Icons.Outlined.Person,
                        label = "Full Name",
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
                        label = "Phone",
                        value = if (state.user?.phone.isNullOrBlank()) "Not set" else state.user!!.phone!!
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileInfoRow(
                        icon = Icons.Outlined.Shield,
                        label = "Account Type",
                        value = state.user?.role?.replaceFirstChar { it.uppercaseChar() } ?: "User"
                    )
                }

                Spacer(Modifier.height(16.dp))

                // App section
                ProfileSectionCard(title = "App") {
                    ProfileActionRow(
                        icon = Icons.Outlined.Notifications,
                        label = "Notifications",
                        onClick = onNavigateToNotifications
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileActionRow(
                        icon = Icons.Outlined.Lock,
                        label = "Privacy & Security",
                        onClick = onNavigateToPrivacy
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileActionRow(
                        icon = Icons.Outlined.HelpOutline,
                        label = "Help & Support",
                        onClick = onNavigateToHelp
                    )
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    ProfileActionRow(
                        icon = Icons.Outlined.Info,
                        label = "About PetHeal",
                        onClick = onNavigateToAbout
                    )
                }

                Spacer(Modifier.height(24.dp))

                // Logout button
                OutlinedButton(
                    onClick = { showLogoutDialog = true },
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Color(0xFFFF6B6B)
                    ),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFF6B3333)),
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
                    Text("Log Out", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                }
            }
        }

        // Error snackbar
        state.error?.let { err ->
            Snackbar(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(16.dp),
                containerColor = Color(0xFF3A1C1C),
                contentColor = Color(0xFFFF9999),
                dismissAction = {
                    IconButton(onClick = { viewModel.clearError() }) {
                        Icon(Icons.Filled.Close, contentDescription = null, tint = Color(0xFFFF9999))
                    }
                }
            ) {
                Text(err)
            }
        }
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
                            tint = Color.White
                        )
                    }
                    Spacer(Modifier.width(4.dp))
                    Text(
                        text = "Edit Profile",
                        color = Color.White,
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
                    .padding(horizontal = 20.dp)
                    .padding(top = 28.dp, bottom = 40.dp),
                verticalArrangement = Arrangement.spacedBy(18.dp)
            ) {
                // Name field (read-only)
                EditField(
                    label = "Full Name",
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
                    label = "Phone Number",
                    value = state.phone,
                    onValueChange = viewModel::onPhoneChange,
                    placeholder = "Enter your phone number",
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
                            .background(Color(0xFF3A1C1C))
                            .padding(12.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.ErrorOutline,
                            contentDescription = null,
                            tint = Color(0xFFFF9999),
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(Modifier.width(8.dp))
                        Text(err, color = Color(0xFFFF9999), fontSize = 13.sp)
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
                        contentColor = PrimaryFg,
                        disabledContainerColor = BorderDark,
                        disabledContentColor = TextSecDark
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp)
                ) {
                    if (state.isLoading) {
                        CircularProgressIndicator(
                            color = PrimaryFg,
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
                        Text("Save Changes", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    }
                }
            }
        }
    }
}

// ─── Shared sub-composables ───────────────────────────────────────────────────

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
                .clip(RoundedCornerShape(16.dp))
                .background(SurfaceDark)
                .border(1.dp, BorderDark, RoundedCornerShape(16.dp))
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
                .clip(RoundedCornerShape(10.dp))
                .background(BorderDark),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = Primary, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(label, color = TextSecDark, fontSize = 11.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(2.dp))
            Text(value, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Medium)
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
                .clip(RoundedCornerShape(10.dp))
                .background(BorderDark),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = Primary, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(label, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
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
                focusedTextColor = Color.White,
                unfocusedTextColor = Color.White,
                focusedBorderColor = Primary,
                unfocusedBorderColor = BorderDark,
                cursorColor = Primary,
                focusedContainerColor = SurfaceDark,
                unfocusedContainerColor = SurfaceDark,
                disabledTextColor = if (readOnly) TextSecDark else Color.White,
                disabledBorderColor = BorderDark,
                disabledLeadingIconColor = TextSecDark
            ),
            modifier = Modifier.fillMaxWidth()
        )
    }
}
