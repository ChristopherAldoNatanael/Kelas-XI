package com.christopheraldoo.petheal.ui.screens.auth

import android.app.Activity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.BuildConfig
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import com.google.android.gms.common.api.ApiException
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await

// ── Brand colors ──────────────────────────────────────────────────────────────
internal val AuthPrimary        = Color(0xFF2BEE6C)
internal val AuthBgDark         = Color(0xFFF6F8F6)
internal val AuthBgLight        = Color(0xFFF6F8F6)
internal val AuthSurfaceDark    = Color.White
internal val AuthBorderDark     = Color(0xFF3B5443)
internal val AuthTextSecondary  = Color(0xFF9DB9A6)

@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    onNavigateToRegister: () -> Unit,
    viewModel: LoginViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val authProvider by viewModel.authProvider.collectAsState(initial = null)
    val isDark = false
    val bgColor = if (isDark) AuthBgDark else AuthBgLight
    val focusManager = LocalFocusManager.current
    val context = LocalContext.current

    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var showForgotPasswordDialog by remember { mutableStateOf(false) }
    var forgotPasswordEmail by remember { mutableStateOf("") }
    var forgotPasswordMessage by remember { mutableStateOf<String?>(null) }
    var isForgotPasswordLoading by remember { mutableStateOf(false) }

    // Google Sign-In client
    val googleSignInClient = remember {
        val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
            .requestIdToken(BuildConfig.GOOGLE_CLIENT_ID)
            .requestEmail()
            .build()
        GoogleSignIn.getClient(context, gso)
    }

    // Google Sign-In launcher
    val googleLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val task = GoogleSignIn.getSignedInAccountFromIntent(result.data)
            try {
                val account = task.getResult(ApiException::class.java)
                // Force sign out first to ensure fresh account picker next time
                googleSignInClient.signOut()
                // Get the OAuth2 ID token and exchange it for a Firebase ID token
                account.idToken?.let { oauthToken ->
                    CoroutineScope(Dispatchers.Main).launch {
                        try {
                            val firebaseAuth = FirebaseAuth.getInstance()
                            val credential = com.google.firebase.auth.GoogleAuthProvider.getCredential(oauthToken, null)
                            val authResult = firebaseAuth.signInWithCredential(credential).await()
                            val firebaseIdToken = authResult.user?.getIdToken(true)?.await()?.token
                            if (firebaseIdToken != null) {
                                viewModel.loginWithGoogleIdToken(firebaseIdToken)
                            } else {
                                viewModel.setError("Token Firebase tidak berhasil diambil")
                            }
                        } catch (e: Exception) {
                            viewModel.setError("Masuk dengan Google gagal: ${e.message}")
                        }
                    }
                } ?: viewModel.setError("Akun Google tidak mengembalikan ID token. Periksa konfigurasi Google Sign-In di Firebase.")
            } catch (e: ApiException) {
                viewModel.setError("Terjadi kendala saat masuk dengan Google (kode ${e.statusCode})")
            }
        }
    }

    LaunchedEffect(uiState.isSuccess) {
        if (uiState.isSuccess) onLoginSuccess()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFFFFFFFF), Color(0xFFF2FBF5), bgColor)
                )
            )
    ) {
        Box(
            modifier = Modifier
                .size(260.dp)
                .offset(x = 210.dp, y = (-90).dp)
                .clip(RoundedCornerShape(90.dp))
                .background(AuthPrimary.copy(alpha = 0.12f))
        )
        Box(
            modifier = Modifier
                .size(180.dp)
                .offset(x = (-70).dp, y = 120.dp)
                .clip(RoundedCornerShape(64.dp))
                .background(Color(0xFF0EA5A5).copy(alpha = 0.08f))
        )
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp)
                .padding(top = 20.dp, bottom = 28.dp)
        ) {

            // ── Top app bar ───────────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 32.dp, top = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(modifier = Modifier.size(48.dp))
                Text(
                    text = "Akun PetHeal",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else Color(0xFF0F172A)
                )
                Box(modifier = Modifier.size(48.dp))
            }

            // ── Branding ──────────────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(28.dp))
                    .background(
                        Brush.linearGradient(
                            listOf(Color.White, Color(0xFFEFFAF3))
                        )
                    )
                    .padding(horizontal = 24.dp, vertical = 28.dp)
                    .padding(bottom = 4.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Box(
                    modifier = Modifier
                        .size(76.dp)
                        .background(AuthPrimary.copy(alpha = 0.16f), RoundedCornerShape(24.dp)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Filled.Pets, contentDescription = null,
                        tint = AuthPrimary, modifier = Modifier.size(32.dp)
                    )
                }
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Selamat Datang Kembali",
                    fontSize = 25.sp, fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else Color(0xFF0F172A)
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Kelola booking, pembayaran, dan riwayat kesehatan hewan dalam satu tempat.",
                    fontSize = 14.sp,
                    lineHeight = 20.sp,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Center
                )
            }

            Spacer(modifier = Modifier.height(28.dp))

            // ── Email ─────────────────────────────────────────────────
            AuthFieldLabel("Alamat Email", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = email,
                onValueChange = { email = it },
                placeholder = "nama@email.com",
                leadingIcon = {
                    Icon(Icons.Filled.Email, null,
                        tint = AuthTextSecondary, modifier = Modifier.size(20.dp))
                },
                trailingIcon = if (email.contains("@") && email.contains(".")) {
                    { Icon(Icons.Filled.CheckCircle, null,
                        tint = AuthPrimary, modifier = Modifier.size(20.dp)) }
                } else null,
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
                keyboardActions = KeyboardActions(
                    onNext = { focusManager.moveFocus(FocusDirection.Down) }),
                isDark = isDark
            )

            Spacer(modifier = Modifier.height(20.dp))

            // ── Password ──────────────────────────────────────────────
            AuthFieldLabel("Kata Sandi", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = password,
                onValueChange = { password = it },
                placeholder = "••••••••",
                leadingIcon = {
                    Icon(Icons.Filled.Lock, null,
                        tint = AuthTextSecondary, modifier = Modifier.size(20.dp))
                },
                trailingIcon = {
                    IconButton(
                        onClick = { passwordVisible = !passwordVisible },
                        modifier = Modifier.size(40.dp)
                    ) {
                        Icon(
                            imageVector = if (passwordVisible) Icons.Filled.Visibility
                                          else Icons.Filled.VisibilityOff,
                            contentDescription = null,
                            tint = AuthTextSecondary,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                },
                visualTransformation = if (passwordVisible) VisualTransformation.None
                                       else PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus() }),
                isDark = isDark
            )

            // ── Forgot password ───────────────────────────────────────
            if (authProvider != PreferencesManager.AUTH_PROVIDER_GOOGLE) {
                Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterEnd) {
                    TextButton(
                        onClick = {
                            forgotPasswordEmail = email.trim()
                            forgotPasswordMessage = null
                            showForgotPasswordDialog = true
                        }
                    ) {
                        Text(
                            "Lupa Kata Sandi?",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AuthPrimary
                        )
                    }
                }

                Spacer(modifier = Modifier.height(4.dp))
            }

            // ── Login button ──────────────────────────────────────────
            Button(
                onClick = {
                    focusManager.clearFocus()
                    viewModel.loginWithEmailPassword(email.trim(), password)
                },
                modifier = Modifier.fillMaxWidth().height(58.dp),
                shape = RoundedCornerShape(18.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AuthPrimary, contentColor = AuthBgDark),
                enabled = !uiState.isLoading && email.isNotBlank() && password.isNotBlank(),
                elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = AuthBgDark, strokeWidth = 2.5.dp)
                } else {
                    Text("Masuk", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(22.dp))

            // ── Divider ───────────────────────────────────────────────
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Divider(
                    modifier = Modifier.weight(1f),
                    color = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
                )
                Text(
                    "  atau lanjutkan dengan  ",
                    fontSize = 11.sp, fontWeight = FontWeight.Medium,
                    color = AuthTextSecondary, letterSpacing = 0.5.sp
                )
                Divider(
                    modifier = Modifier.weight(1f),
                    color = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)
                )
            }

            Spacer(modifier = Modifier.height(24.dp))

            // ── Google button ─────────────────────────────────────────
            OutlinedButton(
                onClick = {
                    viewModel.clearError()
                    // Sign out first so the account-picker always appears
                    googleSignInClient.signOut().addOnCompleteListener {
                        val signInIntent = googleSignInClient.signInIntent
                        googleLauncher.launch(signInIntent)
                    }
                },
                modifier = Modifier.fillMaxWidth().height(58.dp),
                shape = RoundedCornerShape(18.dp),
                border = BorderStroke(1.dp,
                    if (isDark) AuthBorderDark else Color(0xFFE2E8F0)),
                colors = ButtonDefaults.outlinedButtonColors(
                    containerColor = if (isDark) AuthSurfaceDark else Color.White,
                    contentColor = if (isDark) Color.White else Color(0xFF0F172A)
                ),
                enabled = !uiState.isLoading
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = AuthPrimary, strokeWidth = 2.5.dp)
                } else {
                    GoogleLogoIcon()
                    Spacer(modifier = Modifier.width(12.dp))
                    Text("Lanjutkan dengan Google",
                        fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                }
            }

            // ── Error ─────────────────────────────────────────────────
            uiState.error?.let {
                Spacer(modifier = Modifier.height(16.dp))
                Text(it,
                    color = MaterialTheme.colorScheme.error,
                    fontSize = 13.sp, textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth())
            }

            forgotPasswordMessage?.let {
                Spacer(modifier = Modifier.height(10.dp))
                Text(
                    text = it,
                    color = AuthPrimary,
                    fontSize = 12.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }

            // ── Footer ────────────────────────────────────────────────
            Spacer(modifier = Modifier.height(40.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Belum punya akun? ",
                    fontSize = 13.sp,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B))
                TextButton(
                    onClick = onNavigateToRegister,
                    contentPadding = PaddingValues(0.dp)
                ) {
                    Text("Daftar Sekarang",
                        fontSize = 13.sp, fontWeight = FontWeight.Bold,
                        color = AuthPrimary)
                }
            }
        }

        if (showForgotPasswordDialog) {
            AlertDialog(
                onDismissRequest = {
                    if (!isForgotPasswordLoading) {
                        showForgotPasswordDialog = false
                    }
                },
                title = { Text("Reset Kata Sandi") },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text(
                            "Masukkan email Anda untuk menerima kode reset kata sandi.",
                            fontSize = 13.sp
                        )
                        OutlinedTextField(
                            value = forgotPasswordEmail,
                            onValueChange = { forgotPasswordEmail = it },
                            singleLine = true,
                            label = { Text("Email") },
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                },
                confirmButton = {
                    TextButton(
                        enabled = !isForgotPasswordLoading && forgotPasswordEmail.isNotBlank(),
                        onClick = {
                            isForgotPasswordLoading = true
                            viewModel.requestForgotPassword(forgotPasswordEmail.trim()) { result ->
                                isForgotPasswordLoading = false
                                when (result) {
                                    is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                                        forgotPasswordMessage = "Kode reset telah dikirim ke ${forgotPasswordEmail.trim()}."
                                        showForgotPasswordDialog = false
                                    }

                                    is com.christopheraldoo.petheal.data.repository.Result.Error -> {
                                        viewModel.setError(result.message)
                                    }

                                    else -> Unit
                                }
                            }
                        }
                    ) {
                        if (isForgotPasswordLoading) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                strokeWidth = 2.dp,
                                color = AuthPrimary
                            )
                        } else {
                            Text("Kirim Kode")
                        }
                    }
                },
                dismissButton = {
                    TextButton(
                        enabled = !isForgotPasswordLoading,
                        onClick = { showForgotPasswordDialog = false }
                    ) {
                        Text("Batal")
                    }
                }
            )
        }
    }
}

// ── Shared helpers ────────────────────────────────────────────────────────────

@Composable
internal fun AuthFieldLabel(text: String, isDark: Boolean) {
    Text(
        text = text,
        fontSize = 13.sp, fontWeight = FontWeight.Medium,
        color = if (isDark) Color.White else Color(0xFF0F172A)
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun AuthTextField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    leadingIcon: (@Composable () -> Unit)? = null,
    trailingIcon: (@Composable () -> Unit)? = null,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    keyboardOptions: KeyboardOptions = KeyboardOptions.Default,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
    isDark: Boolean
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = Modifier.fillMaxWidth().height(60.dp),
        placeholder = { Text(placeholder, color = AuthTextSecondary, fontSize = 15.sp) },
        leadingIcon = leadingIcon,
        trailingIcon = trailingIcon,
        visualTransformation = visualTransformation,
        keyboardOptions = keyboardOptions,
        keyboardActions = keyboardActions,
        singleLine = true,
        shape = RoundedCornerShape(18.dp),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = AuthPrimary,
            unfocusedBorderColor = if (isDark) AuthBorderDark else Color(0xFFCBD5E1),
            focusedContainerColor = if (isDark) AuthSurfaceDark else Color.White,
            unfocusedContainerColor = if (isDark) AuthSurfaceDark else Color.White,
            focusedTextColor = if (isDark) Color.White else Color(0xFF0F172A),
            unfocusedTextColor = if (isDark) Color.White else Color(0xFF0F172A),
            cursorColor = AuthPrimary
        )
    )
}

@Composable
internal fun GoogleLogoIcon(modifier: Modifier = Modifier) {
    Canvas(modifier = modifier.size(22.dp)) {
        val r = size.minDimension / 2f
        drawArc(Color(0xFF4285F4), -90f,  90f, useCenter = true)
        drawArc(Color(0xFF34A853),   0f,  90f, useCenter = true)
        drawArc(Color(0xFFFBBC05),  90f,  90f, useCenter = true)
        drawArc(Color(0xFFEA4335), 180f,  90f, useCenter = true)
        drawCircle(Color.White, radius = r * 0.60f)
    }
}
