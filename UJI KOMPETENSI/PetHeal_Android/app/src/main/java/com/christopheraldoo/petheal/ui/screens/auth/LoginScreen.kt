package com.christopheraldoo.petheal.ui.screens.auth

import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.TextSecondary

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
import androidx.compose.runtime.saveable.rememberSaveable
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
import androidx.compose.ui.res.stringResource
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
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import com.google.android.gms.common.api.ApiException
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
import com.christopheraldoo.petheal.ui.theme.PrimaryWash
import com.christopheraldoo.petheal.ui.theme.Secondary
import com.christopheraldoo.petheal.ui.theme.SuccessDeep
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.Surface

// ── Brand colors — satu sumber: ui/theme/Color.kt ────────────────────────────
internal val AuthPrimary       = Primary
internal val AuthBg            = Background
internal val AuthTextSecondary = TextSecondary

@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    onNavigateToRegister: () -> Unit,
    onNavigateToCompleteSetup: () -> Unit = {},
    viewModel: LoginViewModel = hiltViewModel(),
    pickerViewModel: ClinicPickerViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val pickerState by pickerViewModel.uiState.collectAsState()
    val authProvider by viewModel.authProvider.collectAsState(initial = null)
    val bgColor = AuthBg
    val focusManager = LocalFocusManager.current
    val context = LocalContext.current

    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var passwordVisible by rememberSaveable { mutableStateOf(false) }
    var showForgotPasswordDialog by rememberSaveable { mutableStateOf(false) }
    // Catatan sukses yang tampil di layar masuk setelah lembar reset selesai.
    var forgotPasswordMessage by rememberSaveable { mutableStateOf<String?>(null) }
    val resetDoneBanner = stringResource(R.string.reset_done_banner)
    // PHASE 7: tenant hint HANYA untuk login email. Login Google selalu
    // mengirim null (klinik ikut akun, backend yang menentukan).
    var showClinicPicker by rememberSaveable { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(Unit) { pickerViewModel.load() }

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
                    scope.launch {
                        try {
                            val firebaseAuth = FirebaseAuth.getInstance()
                            val credential = com.google.firebase.auth.GoogleAuthProvider.getCredential(oauthToken, null)
                            val authResult = firebaseAuth.signInWithCredential(credential).await()
                            val firebaseIdToken = authResult.user?.getIdToken(true)?.await()?.token
                            if (firebaseIdToken != null) {
                                // Akun Google yang SUDAH terikat klinik harus lolos apa
                                // pun pilihan lokal: backend 403 bila slug lokal ≠
                                // klinik akun. Kirim null → klinik ikut akun
                                // (server-side). Akun Google BARU tanpa klinik
                                // otomatis diarahkan ke CompleteSetup.
                                viewModel.loginWithGoogleIdToken(firebaseIdToken, null)
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

    // PHASE 7: clinic-less accounts (e.g. older Google sign-ins) finish
    // binding before entering Home — otherwise every call is a 403.
    LaunchedEffect(uiState.needsClinicSetup) {
        if (uiState.needsClinicSetup) onNavigateToCompleteSetup()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Surface, PrimaryWash, bgColor)
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
                .background(Secondary.copy(alpha = 0.08f))
        )
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .imePadding()
                .navigationBarsPadding()
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
                    color = OnBackground
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
                            listOf(Color.White, PrimaryWash)
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
                    text = "Selamat Datang Kembali",
                    fontSize = 25.sp, fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Kelola booking, pembayaran, dan riwayat kesehatan hewan dalam satu tempat.",
                    fontSize = 14.sp,
                    lineHeight = 20.sp,
                    color = TextSecondary,
                    textAlign = TextAlign.Center
                )
            }

            Spacer(modifier = Modifier.height(28.dp))

            // ── Email ─────────────────────────────────────────────────
            AuthFieldLabel("Alamat Email")
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
            )

            Spacer(modifier = Modifier.height(20.dp))

            // ── Password ──────────────────────────────────────────────
            AuthFieldLabel("Kata Sandi")
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
                            contentDescription = if (passwordVisible) "Sembunyikan kata sandi" else "Tampilkan kata sandi",
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
            )

            // ── Forgot password ───────────────────────────────────────
            if (authProvider != PreferencesManager.AUTH_PROVIDER_GOOGLE) {
                Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterEnd) {
                    TextButton(
                        onClick = {
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

            // ── Klinik (HANYA untuk login email — tombol Google di bawah
            // mengabaikan pilihan ini dan memakai klinik milik akun) ──
            val pickedName = pickerState.clinics
                .firstOrNull { it.slug == pickerState.selectedSlug }?.name
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 2.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = if (pickedName != null) "Klinik (login email): $pickedName" else "Klinik (login email): belum dipilih",
                    fontSize = 13.sp,
                    color = TextSecondary,
                    modifier = Modifier.weight(1f)
                )
                TextButton(onClick = { showClinicPicker = true }) {
                    Text(
                        if (pickedName != null) "Ganti" else "Pilih",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AuthPrimary
                    )
                }
            }

            Spacer(modifier = Modifier.height(4.dp))

            // ── Login button ──────────────────────────────────────────
            Button(
                onClick = {
                    focusManager.clearFocus()
                    viewModel.loginWithEmailPassword(email.trim(), password, pickerState.selectedSlug)
                },
                modifier = Modifier.fillMaxWidth().height(58.dp),
                shape = RoundedCornerShape(18.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AuthPrimary, contentColor = Color.White),
                enabled = !uiState.isLoading && email.isNotBlank() && password.isNotBlank(),
                elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = Color.White, strokeWidth = 2.5.dp)
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
                    color = Outline
                )
                Text(
                    "  atau lanjutkan dengan  ",
                    fontSize = 11.sp, fontWeight = FontWeight.Medium,
                    color = AuthTextSecondary, letterSpacing = 0.5.sp
                )
                Divider(
                    modifier = Modifier.weight(1f),
                    color = Outline
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
                    Outline),
                colors = ButtonDefaults.outlinedButtonColors(
                    containerColor = Color.White,
                    contentColor = OnBackground
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

            forgotPasswordMessage?.let { message ->
                Spacer(modifier = Modifier.height(14.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(SuccessSoft)
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Filled.CheckCircle,
                        contentDescription = null,
                        tint = SuccessDeep,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(10.dp))
                    Text(
                        text = message,
                        color = SuccessDeep,
                        fontSize = 12.sp,
                        lineHeight = 17.sp
                    )
                }
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
                    color = TextSecondary)
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
            ResetPasswordSheet(
                initialEmail = email.trim(),
                viewModel = viewModel,
                onDismiss = { showForgotPasswordDialog = false },
                onCompleted = {
                    forgotPasswordMessage = resetDoneBanner
                    showForgotPasswordDialog = false
                }
            )
        }


        if (showClinicPicker) {
            ClinicPickerSheet(
                onDismiss = { showClinicPicker = false },
                onClinicSelected = { showClinicPicker = false },
                viewModel = pickerViewModel
            )
        }
    }
}

// ── Shared helpers ────────────────────────────────────────────────────────────

@Composable
internal fun AuthFieldLabel(text: String) {
    Text(
        text = text,
        fontSize = 13.sp, fontWeight = FontWeight.Medium,
        color = OnBackground
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
            unfocusedBorderColor = OutlineStrong,
            focusedContainerColor = Color.White,
            unfocusedContainerColor = Color.White,
            focusedTextColor = OnBackground,
            unfocusedTextColor = OnBackground,
            cursorColor = AuthPrimary
        )
    )
}

@Composable
internal fun GoogleLogoIcon(modifier: Modifier = Modifier) {
    // Logo "G" Google resmi (vector 4 warna) — dipakai Login + Register.
    Image(
        painter = painterResource(id = R.drawable.ic_google_g),
        contentDescription = "Google",
        modifier = modifier.size(22.dp)
    )
}
