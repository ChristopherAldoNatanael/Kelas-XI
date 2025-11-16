package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Context
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
import androidx.compose.material3.ExperimentalMaterial3Api
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
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkUtils
import com.christopheraldoo.aplikasimonitoringkelas.data.LoginRequest
import com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkConfig
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.IOException

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
            // Admin role not supported in Android app; handled via Laravel web
            "Admin" -> Intent(this, LoginActivity::class.java)
            else -> Intent(this, LoginActivity::class.java)
        }
        startActivity(intent)
    }
}

// Tambah util normalisasi role (flatten spasi, underscore, kapital, dsb)
private fun normalizeRole(raw: String?): String {
    if (raw.isNullOrBlank()) return ""
    val base = raw.trim().lowercase()
        .replace("_", "-")
        .replace(" +".toRegex(), "-") // multi spasi jadi satu hyphen
        .replace("--+".toRegex(), "-") // rapikan hyphen beruntun
    return when (base) {
        "siswa" -> "siswa"
        "kurikulum" -> "kurikulum"
        "kepala-sekolah", "kepala", "kepala-sekolah-", "kepala--sekolah", "kepala sekolah" -> "kepala-sekolah"
        "admin" -> "admin" // masih diblok aksesnya di aplikasi android
        else -> base // kembalikan base; nanti dicek lagi
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
    val roles = listOf("Siswa", "Kurikulum", "Kepala Sekolah")

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
                // Remove image entirely - just show text
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
            isError = email.isNotEmpty() && !android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches(),
            supportingText = {
                if (email.isNotEmpty() && !android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
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
                    // HAPUS validasi pilih role: server akan tentukan role
                    email.isEmpty() -> {
                        Toast.makeText(context, "Email tidak boleh kosong", Toast.LENGTH_SHORT).show()
                    }
                    !android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches() -> {
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

                        // Real API login process
                        CoroutineScope(Dispatchers.Main).launch {
                            try {
                                val loginRequest = LoginRequest(
                                    email = email,
                                    password = password
                                )

                                // Log base URL untuk debug, tapi tidak tampil ke user
                                val base = NetworkConfig.BaseUrls.getDefault(context)
                                android.util.Log.d("Login", "Base URL: $base")

                                val response = withContext(Dispatchers.IO) {
                                    RetrofitClient.createApiService(context).login(loginRequest)
                                }
                                if (response.isSuccessful && response.body()?.success == true) {
                                    val responseBody = response.body()
                                    val loginData = responseBody?.data
                                    val user = loginData?.user

                                    if (user != null && loginData != null) {
                                        val roleFromServer = user.role
                                        val normalizedRole = normalizeRole(roleFromServer)
                                        
                                        val sessionManager = SessionManager(context)
                                        sessionManager.createLoginSession(
                                            id = user.id.toLong(),
                                            name = user.nama,
                                            email = user.email,
                                            role = roleFromServer, // keep original
                                            token = loginData.token,
                                            classId = user.classId
                                        )

                                        Toast.makeText(context, "Login berhasil sebagai $roleFromServer", Toast.LENGTH_SHORT).show()

                                        when (normalizedRole) {
                                            "siswa" -> {
                                                context.startActivity(Intent(context, SiswaActivity::class.java))
                                                (context as ComponentActivity).finish()
                                            }
                                            "kurikulum" -> {
                                                context.startActivity(Intent(context, KurikulumActivity::class.java))
                                                (context as ComponentActivity).finish()
                                            }
                                            "kepala-sekolah" -> {
                                                context.startActivity(Intent(context, KepalaSekolahActivity::class.java))
                                                (context as ComponentActivity).finish()
                                            }
                                            "admin" -> {
                                                Toast.makeText(context, "Role admin gunakan web", Toast.LENGTH_SHORT).show()
                                                (context as ComponentActivity).finish()
                                            }
                                            else -> {
                                                Toast.makeText(context, "Role tidak dikenali: $roleFromServer", Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                    } else {
                                        Toast.makeText(context, "Gagal memproses data login.", Toast.LENGTH_SHORT).show()
                                    }
                                } else {
                                    val friendly = response.body()?.message ?: "Login gagal. Coba lagi."
                                    Toast.makeText(context, friendly, Toast.LENGTH_SHORT).show()
                                }
                            } catch (e: IOException) {
                                // Flip base URL and retry once
                                RetrofitClient.markConnectionFailureAndFlipBaseUrl(context)
                                
                                try {
                                    val retry = withContext(Dispatchers.IO) {
                                        RetrofitClient.createApiService(context).login(LoginRequest(email, password))
                                    }
                                    if (retry.isSuccessful && retry.body()?.success == true) {
                                        val responseBody = retry.body()
                                        val loginData = responseBody?.data
                                        val user = loginData?.user

                                        if (user != null && loginData != null) {
                                            val roleFromServer = user.role
                                            val normalizedRole = normalizeRole(roleFromServer)

                                            val sessionManager = SessionManager(context)
                                            sessionManager.createLoginSession(
                                                id = user.id.toLong(),
                                                name = user.nama,
                                                email = user.email,
                                                role = roleFromServer,
                                                token = loginData.token,
                                                classId = user.classId
                                            )
                                            
                                            Toast.makeText(context, "Login berhasil", Toast.LENGTH_SHORT).show()
                                            
                                            when (normalizedRole) {
                                                "siswa" -> context.startActivity(Intent(context, SiswaActivity::class.java))
                                                "kurikulum" -> context.startActivity(Intent(context, KurikulumActivity::class.java))
                                                "kepala-sekolah" -> context.startActivity(Intent(context, KepalaSekolahActivity::class.java))
                                                "admin" -> Toast.makeText(context, "Role admin gunakan web", Toast.LENGTH_SHORT).show()
                                                else -> Toast.makeText(context, "Role tidak dikenali: $roleFromServer", Toast.LENGTH_SHORT).show()
                                            }
                                            (context as ComponentActivity).finish()
                                        }
                                    } else {
                                        Toast.makeText(context, "Tidak bisa terhubung ke server. Coba lagi.", Toast.LENGTH_LONG).show()
                                    }
                                } catch (e2: Exception) {
                                    Toast.makeText(context, "Terjadi kesalahan jaringan. Coba lagi.", Toast.LENGTH_LONG).show()
                                }
                            } catch (e: Exception) {
                                val msg = e.localizedMessage?.lowercase() ?: ""
                                val friendly = when {
                                    msg.contains("failed to connect") || msg.contains("timeout") ->
                                        "Tidak bisa terhubung ke server. Pastikan server Laravel hidup dan jaringan sama."
                                    msg.contains("unable to resolve host") || msg.contains("unknown host") ->
                                        "Alamat server tidak ditemukan. Cek koneksi dan IP server."
                                    else -> "Terjadi kesalahan jaringan. Coba lagi."
                                }
                                Toast.makeText(context, friendly, Toast.LENGTH_LONG).show()
                            } finally {
                                isLoading = false
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
