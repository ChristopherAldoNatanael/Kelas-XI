package com.christopheraldoo.petheal.ui.screens.auth

import android.app.Activity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
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
import androidx.compose.ui.focus.FocusDirection
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
internal val AuthBgDark         = Color(0xFF102216)
internal val AuthBgLight        = Color(0xFFF6F8F6)
internal val AuthSurfaceDark    = Color(0xFF1C271F)
internal val AuthBorderDark     = Color(0xFF3B5443)
internal val AuthTextSecondary  = Color(0xFF9DB9A6)

@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    onNavigateToRegister: () -> Unit,
    viewModel: LoginViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val isDark = isSystemInDarkTheme()
    val bgColor = if (isDark) AuthBgDark else AuthBgLight
    val focusManager = LocalFocusManager.current
    val context = LocalContext.current

    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }

    // Google Sign-In client
    val googleSignInClient = remember {        val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
            .requestIdToken("285160088448-71u7b08k065tu3squvo73cuv9rg9s9u7.apps.googleusercontent.com")
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
                                viewModel.setError("Failed to get Firebase ID token")
                            }
                        } catch (e: Exception) {
                            viewModel.setError("Google sign-in failed: ${e.message}")
                        }
                    }
                } ?: viewModel.setError("Google account has no ID token. Enable Google Sign-In in Firebase Console.")
            } catch (e: ApiException) {
                viewModel.setError("Google sign-in error (code ${e.statusCode})")
            }
        }
    }

    LaunchedEffect(uiState.isSuccess) {
        if (uiState.isSuccess) onLoginSuccess()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgColor)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp)
                .padding(top = 8.dp, bottom = 24.dp)
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
                    text = "Log In",
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
                    .padding(bottom = 32.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Box(
                    modifier = Modifier
                        .size(64.dp)
                        .background(AuthPrimary.copy(alpha = 0.20f), RoundedCornerShape(16.dp)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Filled.Pets, contentDescription = null,
                        tint = AuthPrimary, modifier = Modifier.size(32.dp)
                    )
                }
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Welcome Back",
                    fontSize = 22.sp, fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else Color(0xFF0F172A)
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Please sign in to your PetHeal account",
                    fontSize = 13.sp, color = AuthTextSecondary,
                    textAlign = TextAlign.Center
                )
            }

            // ── Email ─────────────────────────────────────────────────
            AuthFieldLabel("Email Address", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = email,
                onValueChange = { email = it },
                placeholder = "hello@example.com",
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
            AuthFieldLabel("Password", isDark)
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
            Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterEnd) {
                TextButton(onClick = { }) {
                    Text("Forgot Password?",
                        fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                        color = AuthPrimary)
                }
            }

            Spacer(modifier = Modifier.height(4.dp))

            // ── Login button ──────────────────────────────────────────
            Button(
                onClick = {
                    focusManager.clearFocus()
                    viewModel.loginWithEmailPassword(email.trim(), password)
                },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
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
                    Text("Login", fontSize = 16.sp, fontWeight = FontWeight.Bold)
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
                    "  Or continue with  ",
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
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
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
                    Text("Continue with Google",
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
                Text("Don't have an account? ",
                    fontSize = 13.sp,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B))
                TextButton(
                    onClick = onNavigateToRegister,
                    contentPadding = PaddingValues(0.dp)
                ) {
                    Text("Register Now",
                        fontSize = 13.sp, fontWeight = FontWeight.Bold,
                        color = AuthPrimary)
                }
            }
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
        modifier = Modifier.fillMaxWidth().height(56.dp),
        placeholder = { Text(placeholder, color = AuthTextSecondary, fontSize = 15.sp) },
        leadingIcon = leadingIcon,
        trailingIcon = trailingIcon,
        visualTransformation = visualTransformation,
        keyboardOptions = keyboardOptions,
        keyboardActions = keyboardActions,
        singleLine = true,
        shape = RoundedCornerShape(12.dp),
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
