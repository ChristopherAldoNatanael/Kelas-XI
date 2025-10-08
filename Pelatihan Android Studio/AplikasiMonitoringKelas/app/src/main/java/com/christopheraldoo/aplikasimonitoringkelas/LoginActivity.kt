package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
class LoginActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        setContent {
            MaterialTheme {
                LoginScreen()
            }
        }
    }
    
    private fun navigateBasedOnRole(role: String) {
        val intent = when (role) {
            "Siswa" -> Intent(this, SiswaActivity::class.java)
            "Kurikulum" -> Intent(this, KurikulumActivity::class.java)
            "Kepala Sekolah" -> Intent(this, KepalaSekolahActivity::class.java)
            "Admin" -> Intent(this, AdminActivity::class.java)
            else -> Intent(this, LoginActivity::class.java)
        }
        startActivity(intent)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LoginScreen() {
    val context = LocalContext.current
    val scrollState = rememberScrollState()

    // State variables
    var selectedRole by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var isLoading by remember { mutableStateOf(false) }

    // Role options
    val roles = listOf("Siswa", "Kurikulum", "Kepala Sekolah", "Admin")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp)
            .verticalScroll(scrollState),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {        // School Logo 
        Card(
            modifier = Modifier
                .size(120.dp)
                .padding(bottom = 32.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center
            ) {
                // Using a placeholder image. Replace the R.drawable.school_logo with your actual logo
                Image(
                    painter = painterResource(id = R.drawable.school_logo),
                    contentDescription = "School Logo",
                    modifier = Modifier.fillMaxSize()
                )
            }
        }

        Text(
            text = "Selamat Datang",
            style = MaterialTheme.typography.headlineMedium,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        Text(
            text = "Sistem Monitoring Kelas",
            style = MaterialTheme.typography.titleMedium,
            color = Color.Gray,
            modifier = Modifier.padding(bottom = 32.dp)
        )

        // Role Spinner
        var expanded by remember { mutableStateOf(false) }

        ExposedDropdownMenuBox(
            expanded = expanded,
            onExpandedChange = { expanded = !expanded },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedRole,
                onValueChange = { selectedRole = it },
                readOnly = true,
                label = { Text("Pilih Role") },
                placeholder = { Text("Pilih role Anda") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expanded,
                onDismissRequest = { expanded = false }
            ) {
                roles.forEach { role ->
                    DropdownMenuItem(
                        text = { Text(role) },
                        onClick = {
                            selectedRole = role
                            expanded = false
                        }
                    )
                }
            }
        }

        // Email TextField
        OutlinedTextField(
            value = email,
            onValueChange = { email = it },
            label = { Text("Email") },
            placeholder = { Text("Masukkan email Anda") },
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
            isError = email.isNotEmpty() && !email.isValidEmail(),
            supportingText = {
                if (email.isNotEmpty() && !email.isValidEmail()) {
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
            value = password,
            onValueChange = { password = it },
            label = { Text("Password") },
            placeholder = { Text("Masukkan password Anda") },
            leadingIcon = {
                Icon(
                    Icons.Default.Lock,
                    contentDescription = "Password",
                    tint = MaterialTheme.colorScheme.primary
                )
            },
            trailingIcon = {
                IconButton(onClick = { passwordVisible = !passwordVisible }) {
                    Icon(
                        if (passwordVisible) Icons.Default.Visibility else Icons.Default.VisibilityOff,
                        contentDescription = if (passwordVisible) "Hide password" else "Show password"
                    )
                }
            },
            visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(
                keyboardType = KeyboardType.Password
            ),
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        )

        // Login Button
        Button(
            onClick = {
                when {
                    selectedRole.isEmpty() -> {
                        Toast.makeText(context, "Pilih role terlebih dahulu", Toast.LENGTH_SHORT).show()
                    }
                    email.isEmpty() -> {
                        Toast.makeText(context, "Email tidak boleh kosong", Toast.LENGTH_SHORT).show()
                    }
                    !email.isValidEmail() -> {
                        Toast.makeText(context, "Format email tidak valid", Toast.LENGTH_SHORT).show()
                    }
                    password.isEmpty() -> {
                        Toast.makeText(context, "Password tidak boleh kosong", Toast.LENGTH_SHORT).show()
                    }
                    password.length < 6 -> {
                        Toast.makeText(context, "Password minimal 6 karakter", Toast.LENGTH_SHORT).show()
                    }
                    else -> {
                        isLoading = true
                        // Simulate login process
                        Toast.makeText(context, "Login berhasil sebagai $selectedRole", Toast.LENGTH_SHORT).show()
                        isLoading = false                        // Navigate to appropriate activity based on role
                        when (selectedRole) {
                            "Siswa" -> {
                                context.startActivity(Intent(context, SiswaActivity::class.java))
                                (context as ComponentActivity).finish()
                            }
                            "Kurikulum" -> {
                                context.startActivity(Intent(context, KurikulumActivity::class.java))
                                (context as ComponentActivity).finish()
                            }
                            "Kepala Sekolah" -> {
                                context.startActivity(Intent(context, KepalaSekolahActivity::class.java))
                                (context as ComponentActivity).finish()
                            }
                            "Admin" -> {
                                context.startActivity(Intent(context, AdminActivity::class.java))
                                (context as ComponentActivity).finish()
                            }
                        }
                    }
                }
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            enabled = !isLoading,
            colors = ButtonDefaults.buttonColors(
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = Color.White
            )
        ) {
            if (isLoading) {
                CircularProgressIndicator(
                    color = Color.White,
                    modifier = Modifier.size(24.dp)
                )
            } else {
                Text(
                    text = "Login",
                    style = MaterialTheme.typography.titleMedium
                )
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Forgot Password Text
        TextButton(onClick = {
            Toast.makeText(context, "Fitur lupa password akan segera hadir", Toast.LENGTH_SHORT).show()
        }) {
            Text(
                text = "Lupa Password?",
                color = MaterialTheme.colorScheme.primary
            )
        }
    }
}
