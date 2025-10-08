package com.christopheraldoo.aplikasimonitoringkelas

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

// Data class untuk menyimpan data user yang di-entri
data class UserData(
    val nama: String,
    val email: String,
    val role: String,
    val password: String
)

// State untuk form input
data class UserFormState(
    var nama: String = "",
    var email: String = "",
    var password: String = "",
    var selectedRole: String = "",
    var passwordVisible: Boolean = false
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EntriUser() {
    val formState = remember { UserFormState() }

    // State untuk menyimpan data yang sudah di-entri
    var savedUsers by remember { mutableStateOf(listOf<UserData>()) }
    var showDialog by remember { mutableStateOf(false) }
    
    // State untuk spinner
    var expandedRole by remember { mutableStateOf(false) }

    // Role options
    val roleOptions = listOf("Siswa", "Kurikulum", "Kepala Sekolah", "Admin")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Header
        Text(
            text = "Entri User Baru",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        )

    // Role Spinner
        Text(
            text = "Pilih Role:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedRole,
            onExpandedChange = { expandedRole = !expandedRole },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = formState.selectedRole,
                onValueChange = { formState.selectedRole = it },
                readOnly = true,
                label = { Text("Pilih Role") },
                placeholder = { Text("Pilih role untuk user baru") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedRole)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedRole,
                onDismissRequest = { expandedRole = false }
            ) {
                roleOptions.forEach { role ->
                    DropdownMenuItem(
                        text = { Text(role) },
                        onClick = {
                            formState.selectedRole = role
                            expandedRole = false
                        }
                    )
                }
            }
        }

        // Nama TextField
        OutlinedTextField(
            value = formState.nama,
            onValueChange = { formState.nama = it },
            label = { Text("Nama Lengkap") },
            placeholder = { Text("Masukkan nama lengkap user") },
            leadingIcon = {
                Icon(
                    Icons.Default.Person,
                    contentDescription = "Nama",
                    tint = MaterialTheme.colorScheme.primary
                )
            },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        )

        // Email TextField
        OutlinedTextField(
            value = formState.email,
            onValueChange = { formState.email = it },
            label = { Text("Email") },
            placeholder = { Text("Masukkan email user") },
            leadingIcon = {
                Icon(
                    Icons.Default.Email,
                    contentDescription = "Email",
                    tint = MaterialTheme.colorScheme.primary
                )
            },
            keyboardOptions = KeyboardOptions(
                keyboardType = KeyboardType.Email
            ),
            isError = formState.email.isNotEmpty() && !formState.email.isValidEmail(),
            supportingText = {
                if (formState.email.isNotEmpty() && !formState.email.isValidEmail()) {
                    Text(
                        text = "Format email tidak valid",
                        color = MaterialTheme.colorScheme.error
                    )
                }
            },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        )

        // Password TextField
        OutlinedTextField(
            value = formState.password,
            onValueChange = { formState.password = it },
            label = { Text("Password") },
            placeholder = { Text("Masukkan password user") },
            leadingIcon = {
                Icon(
                    Icons.Default.Lock,
                    contentDescription = "Password",
                    tint = MaterialTheme.colorScheme.primary
                )
            },
            trailingIcon = {
                IconButton(onClick = { formState.passwordVisible = !formState.passwordVisible }) {
                    Icon(
                        if (formState.passwordVisible) Icons.Default.Visibility else Icons.Default.VisibilityOff,
                        contentDescription = if (formState.passwordVisible) "Hide password" else "Show password"
                    )
                }
            },
            visualTransformation = if (formState.passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(
                keyboardType = KeyboardType.Password
            ),
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        )        // Save Button
        Button(
            onClick = {
                when {
                    formState.selectedRole.isEmpty() -> {
                        showDialog = true
                    }
                    formState.nama.isEmpty() -> {
                        showDialog = true
                    }
                    formState.email.isEmpty() || !formState.email.isValidEmail() -> {
                        showDialog = true
                    }
                    formState.password.isEmpty() -> {
                        showDialog = true
                    }
                    formState.password.length < 6 -> {
                        showDialog = true
                    }
                    else -> {
                        // Simpan data user baru
                        val newUser = UserData(
                            nama = formState.nama,
                            email = formState.email,
                            role = formState.selectedRole,
                            password = formState.password
                        )
                        savedUsers = savedUsers + newUser

                        // Reset form setelah simpan
                        formState.nama = ""
                        formState.email = ""
                        formState.password = ""
                        formState.selectedRole = ""
                        formState.passwordVisible = false

                        showDialog = true
                    }
                }
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = Color.White
            )
        ) {
            Text(
                text = "Simpan User",
                style = MaterialTheme.typography.titleMedium
            )
        }

        Spacer(modifier = Modifier.height(32.dp))

        // Section untuk menampilkan data yang sudah di-entri
        Text(
            text = "Data User yang Sudah Di-Entri",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = 16.dp)
        )

        if (savedUsers.isEmpty()) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant
                )
            ) {
                Text(
                    text = "Belum ada data user yang di-entri",
                    style = MaterialTheme.typography.bodyLarge,
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                    modifier = Modifier.padding(24.dp),
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        } else {
            // LazyColumn untuk cards yang scrollable
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                contentPadding = PaddingValues(bottom = 16.dp)
            ) {
                items(savedUsers) { user ->
                    UserCard(userData = user)
                }
            }
        }
    }

    // Dialog untuk notifikasi
    if (showDialog) {
        AlertDialog(
            onDismissRequest = { showDialog = false },
            title = { Text("Notifikasi") },
            text = {
                Text(
                    if (formState.selectedRole.isEmpty() || formState.nama.isEmpty() ||
                        formState.email.isEmpty() || !formState.email.isValidEmail() ||
                        formState.password.isEmpty() || formState.password.length < 6) {
                        "Mohon lengkapi semua field dengan benar sebelum menyimpan"
                    } else {
                        "User berhasil di-entri dan disimpan"
                    }
                )
            },
            confirmButton = {
                TextButton(onClick = { showDialog = false }) {
                    Text("OK")
                }
            }
        )
    }
}

@Composable
fun UserCard(userData: UserData) {
    Card(
        modifier = Modifier
            .fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            // Header dengan nama dan role
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = userData.nama,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )

                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer
                    )
                ) {
                    Text(
                        text = userData.role,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                }
            }

            Divider(
                modifier = Modifier.padding(vertical = 8.dp),
                color = MaterialTheme.colorScheme.outlineVariant
            )

            // Email
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Default.Email,
                    contentDescription = "Email",
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier
                        .size(20.dp)
                        .padding(end = 8.dp)
                )
                Text(
                    text = userData.email,
                    style = MaterialTheme.typography.bodyLarge
                )
            }

            // Password (disamarkan)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Default.Lock,
                    contentDescription = "Password",
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier
                        .size(20.dp)
                        .padding(end = 8.dp)
                )
                Text(
                    text = "••••••••", // Password ditampilkan sebagai titik-titik
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.Medium
                )
            }

            // Action buttons
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 12.dp),
                horizontalArrangement = Arrangement.End
            ) {
                TextButton(onClick = { /* Edit action */ }) {
                    Icon(
                        Icons.Default.Edit,
                        contentDescription = "Edit",
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Edit")
                }

                Spacer(modifier = Modifier.width(8.dp))

                TextButton(onClick = { /* Delete action */ }) {
                    Icon(
                        Icons.Default.Delete,
                        contentDescription = "Delete",
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Hapus")
                }
            }
        }
    }
}

// Preview untuk testing
@androidx.compose.ui.tooling.preview.Preview(showBackground = true)
@Composable
fun EntriUserPreview() {
    MaterialTheme {
        EntriUser()
    }
}
