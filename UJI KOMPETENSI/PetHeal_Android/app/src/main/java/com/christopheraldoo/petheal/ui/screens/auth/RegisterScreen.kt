package com.christopheraldoo.petheal.ui.screens.auth

import android.app.Activity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
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
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.painterResource
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
import com.christopheraldoo.petheal.R
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import com.google.android.gms.common.api.ApiException
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await

@Composable
fun RegisterScreen(
    onRegisterSuccess: () -> Unit,
    onNavigateBack: () -> Unit,
    onNavigateToCompleteSetup: () -> Unit = {},
    viewModel: RegisterViewModel = hiltViewModel(),
    pickerViewModel: ClinicPickerViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val pickerState by pickerViewModel.uiState.collectAsState()
    val isDark = false
    val bgColor = if (isDark) AuthBgDark else AuthBgLight
    val focusManager = LocalFocusManager.current
    val context = LocalContext.current

    var name by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var confirmPasswordVisible by remember { mutableStateOf(false) }
    var isGoogleSigningIn by remember { mutableStateOf(false) }
    // PHASE 7: tenant binding is mandatory — a slug-less account gets 403
    // on every protected call. The sheet persists the choice; the slug is
    // sent with both email and Google registration.
    var showClinicPicker by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { pickerViewModel.load() }

    val googleSignInClient = remember {
        val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
            .requestIdToken(BuildConfig.GOOGLE_CLIENT_ID)
            .requestEmail()
            .build()
        GoogleSignIn.getClient(context, gso)
    }

    val googleLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        isGoogleSigningIn = false
        if (result.resultCode == Activity.RESULT_OK) {
            val task = GoogleSignIn.getSignedInAccountFromIntent(result.data)
            try {
                val account = task.getResult(ApiException::class.java)
                googleSignInClient.signOut()
                account.idToken?.let { oauthToken ->
                    CoroutineScope(Dispatchers.Main).launch {
                        try {
                            val firebaseAuth = FirebaseAuth.getInstance()
                            val credential = com.google.firebase.auth.GoogleAuthProvider.getCredential(oauthToken, null)
                            val authResult = firebaseAuth.signInWithCredential(credential).await()
                            val firebaseIdToken = authResult.user?.getIdToken(true)?.await()?.token
                            if (firebaseIdToken != null) {
                                viewModel.registerWithGoogleIdToken(
                                    firebaseIdToken,
                                    name.trim().ifBlank { account.displayName.orEmpty() },
                                    pickerState.selectedSlug
                                )
                            } else {
                                viewModel.setError("Token Firebase tidak berhasil diambil")
                            }
                        } catch (e: Exception) {
                            viewModel.setError("Daftar dengan Google gagal: ${e.message}")
                        }
                    }
                } ?: viewModel.setError("Akun Google tidak mengembalikan ID token. Periksa konfigurasi Google Sign-In di Firebase.")
            } catch (e: ApiException) {
                viewModel.setError("Terjadi kendala saat daftar dengan Google (kode ${e.statusCode})")
            }
        }
    }

    val passwordsMatch = password == confirmPassword || confirmPassword.isEmpty()

    LaunchedEffect(uiState.isSuccess) {
        if (uiState.isSuccess) onRegisterSuccess()
    }

    // PHASE 7: defensive — email register requires a slug, but a null
    // binding must never land silently on Home.
    LaunchedEffect(uiState.needsClinicSetup) {
        if (uiState.needsClinicSetup) onNavigateToCompleteSetup()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Color.White, Color(0xFFF2FBF5), bgColor)
                )
            )
    ) {
        Box(
            modifier = Modifier
                .size(240.dp)
                .offset(x = 220.dp, y = (-80).dp)
                .clip(RoundedCornerShape(90.dp))
                .background(AuthPrimary.copy(alpha = 0.12f))
        )
        Box(
            modifier = Modifier
                .size(170.dp)
                .offset(x = (-80).dp, y = 160.dp)
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
                IconButton(
                    onClick = onNavigateBack,
                    modifier = Modifier.size(48.dp)
                ) {
                    Icon(
                        Icons.Filled.ArrowBack,
                        contentDescription = "Kembali",
                        tint = if (isDark) Color.White else Color(0xFF0F172A)
                    )
                }
                Text(
                    text = "Daftar",
                    fontSize = 17.sp, fontWeight = FontWeight.Bold,
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
                Image(
                    painter = painterResource(id = R.drawable.logo_android),
                    contentDescription = "PetHeal logo",
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .size(76.dp)
                        .clip(RoundedCornerShape(22.dp))
                )
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Buat Akun",
                    fontSize = 25.sp, fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else Color(0xFF0F172A)
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Daftar sekali untuk mengatur hewan, konsultasi dokter, dan rekam medis dengan rapi.",
                    fontSize = 14.sp,
                    lineHeight = 20.sp,
                    color = Color(0xFF64748B),
                    textAlign = TextAlign.Center
                )
            }

            Spacer(modifier = Modifier.height(28.dp))

            // ── Full Name ─────────────────────────────────────────────
            AuthFieldLabel("Nama Lengkap", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = name,
                onValueChange = { name = it },
                placeholder = "Masukkan nama lengkap",
                leadingIcon = {
                    Icon(Icons.Filled.Person, null,
                        tint = AuthTextSecondary, modifier = Modifier.size(20.dp))
                },
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Text, imeAction = ImeAction.Next),
                keyboardActions = KeyboardActions(
                    onNext = { focusManager.moveFocus(FocusDirection.Down) }),
                isDark = isDark
            )

            Spacer(modifier = Modifier.height(20.dp))

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
                placeholder = "Minimal 8 karakter",
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
                            tint = AuthTextSecondary, modifier = Modifier.size(20.dp)
                        )
                    }
                },
                visualTransformation = if (passwordVisible) VisualTransformation.None
                                       else PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Password, imeAction = ImeAction.Next),
                keyboardActions = KeyboardActions(
                    onNext = { focusManager.moveFocus(FocusDirection.Down) }),
                isDark = isDark
            )

            Spacer(modifier = Modifier.height(20.dp))

            // ── Confirm Password ──────────────────────────────────────
            AuthFieldLabel("Konfirmasi Kata Sandi", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = confirmPassword,
                onValueChange = { confirmPassword = it },
                placeholder = "Masukkan ulang kata sandi",
                leadingIcon = {
                    Icon(Icons.Filled.Lock, null,
                        tint = AuthTextSecondary, modifier = Modifier.size(20.dp))
                },
                trailingIcon = if (confirmPassword.isNotEmpty()) {
                    {
                        Icon(
                            imageVector = if (passwordsMatch) Icons.Filled.CheckCircle
                                          else Icons.Filled.Cancel,
                            contentDescription = null,
                            tint = if (passwordsMatch) AuthPrimary else MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                } else null,
                visualTransformation = if (confirmPasswordVisible) VisualTransformation.None
                                       else PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus() }),
                isDark = isDark
            )

            if (!passwordsMatch) {
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    "Konfirmasi kata sandi belum sama",
                    color = MaterialTheme.colorScheme.error,
                    fontSize = 12.sp,
                    modifier = Modifier.padding(start = 4.dp)
                )
            }

            Spacer(modifier = Modifier.height(28.dp))

            // ── Klinik (wajib — akun terikat satu klinik) ─────────────
            AuthFieldLabel("Klinik", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            val pickedClinicName = pickerState.clinics
                .firstOrNull { it.slug == pickerState.selectedSlug }?.name
            OutlinedButton(
                onClick = { showClinicPicker = true },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp,
                    if (isDark) AuthBorderDark else Color(0xFFE2E8F0)),
                colors = ButtonDefaults.outlinedButtonColors(
                    containerColor = if (isDark) AuthSurfaceDark else Color.White,
                    contentColor = if (isDark) Color.White else Color(0xFF0F172A)
                )
            ) {
                Icon(
                    Icons.Filled.LocalHospital, null,
                    tint = AuthTextSecondary, modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    pickedClinicName ?: "Pilih klinik Anda",
                    fontSize = 15.sp,
                    fontWeight = if (pickedClinicName != null) FontWeight.SemiBold else FontWeight.Normal,
                    color = if (pickedClinicName != null) (if (isDark) Color.White else Color(0xFF0F172A)) else AuthTextSecondary,
                    modifier = Modifier.weight(1f),
                    textAlign = TextAlign.Start
                )
            }
            if (pickerState.selectedSlug.isNullOrBlank()) {
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    "Akun harus terikat pada satu klinik",
                    color = MaterialTheme.colorScheme.error,
                    fontSize = 12.sp,
                    modifier = Modifier.padding(start = 4.dp)
                )
            }

            Spacer(modifier = Modifier.height(28.dp))

            // ── Register button ───────────────────────────────────────
            Button(
                onClick = {
                    focusManager.clearFocus()
                    viewModel.register(name.trim(), email.trim(), password, pickerState.selectedSlug)
                },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AuthPrimary, contentColor = AuthBgDark),
                enabled = !uiState.isLoading && name.isNotBlank()
                        && email.isNotBlank() && password.length >= 8
                        && passwordsMatch && confirmPassword.isNotBlank()
                        && !pickerState.selectedSlug.isNullOrBlank(),
                elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = AuthBgDark, strokeWidth = 2.5.dp)
                } else {
                    Text("Buat Akun", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

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
                    if (isGoogleSigningIn || uiState.isLoading) return@OutlinedButton
                    viewModel.clearError()
                    isGoogleSigningIn = true
                    googleSignInClient.signOut().addOnCompleteListener {
                        googleLauncher.launch(googleSignInClient.signInIntent)
                    }
                },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp,
                    if (isDark) AuthBorderDark else Color(0xFFE2E8F0)),
                colors = ButtonDefaults.outlinedButtonColors(
                    containerColor = if (isDark) AuthSurfaceDark else Color.White,
                    contentColor = if (isDark) Color.White else Color(0xFF0F172A)
                ),
                enabled = !uiState.isLoading && !isGoogleSigningIn
                        && !pickerState.selectedSlug.isNullOrBlank()
            ) {
                if (isGoogleSigningIn) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = AuthPrimary,
                        strokeWidth = 2.5.dp
                    )
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

            // ── Footer ────────────────────────────────────────────────
            Spacer(modifier = Modifier.height(40.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Sudah punya akun? ",
                    fontSize = 13.sp,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B))
                TextButton(
                    onClick = onNavigateBack,
                    contentPadding = PaddingValues(0.dp)
                ) {
                Text("Masuk",
                        fontSize = 13.sp, fontWeight = FontWeight.Bold,
                        color = AuthPrimary)
                }
            }
        }
    }

    if (showClinicPicker) {
        ClinicPickerSheet(
            onDismiss = { showClinicPicker = false },
            onClinicSelected = { showClinicPicker = false },
            viewModel = pickerViewModel
        )
    }
}
