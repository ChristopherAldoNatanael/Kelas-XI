package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.foundation.BorderStroke
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

@Composable
fun RegisterScreen(
    onRegisterSuccess: () -> Unit,
    onNavigateBack: () -> Unit,
    viewModel: RegisterViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val isDark = isSystemInDarkTheme()
    val bgColor = if (isDark) AuthBgDark else AuthBgLight
    val focusManager = LocalFocusManager.current

    var name by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var confirmPasswordVisible by remember { mutableStateOf(false) }

    val passwordsMatch = password == confirmPassword || confirmPassword.isEmpty()

    LaunchedEffect(uiState.isSuccess) {
        if (uiState.isSuccess) onRegisterSuccess()
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
                IconButton(
                    onClick = onNavigateBack,
                    modifier = Modifier.size(48.dp)
                ) {
                    Icon(
                        Icons.Filled.ArrowBack,
                        contentDescription = "Back",
                        tint = if (isDark) Color.White else Color(0xFF0F172A)
                    )
                }
                Text(
                    text = "Register",
                    fontSize = 17.sp, fontWeight = FontWeight.Bold,
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
                    text = "Create Account",
                    fontSize = 22.sp, fontWeight = FontWeight.Bold,
                    color = if (isDark) Color.White else Color(0xFF0F172A)
                )
                Spacer(modifier = Modifier.height(6.dp))
                Text(
                    text = "Join PetHeal and care for your pet",
                    fontSize = 13.sp, color = AuthTextSecondary,
                    textAlign = TextAlign.Center
                )
            }

            // ── Full Name ─────────────────────────────────────────────
            AuthFieldLabel("Full Name", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = name,
                onValueChange = { name = it },
                placeholder = "Your full name",
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
                placeholder = "Min. 8 characters",
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
            AuthFieldLabel("Confirm Password", isDark)
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = confirmPassword,
                onValueChange = { confirmPassword = it },
                placeholder = "Re-enter password",
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
                    "Passwords do not match",
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
                    viewModel.register(name.trim(), email.trim(), password)
                },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AuthPrimary, contentColor = AuthBgDark),
                enabled = !uiState.isLoading && name.isNotBlank()
                        && email.isNotBlank() && password.length >= 8
                        && passwordsMatch && confirmPassword.isNotBlank(),
                elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = AuthBgDark, strokeWidth = 2.5.dp)
                } else {
                    Text("Create Account", fontSize = 16.sp, fontWeight = FontWeight.Bold)
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
                onClick = { },
                modifier = Modifier.fillMaxWidth().height(56.dp),
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp,
                    if (isDark) AuthBorderDark else Color(0xFFE2E8F0)),
                colors = ButtonDefaults.outlinedButtonColors(
                    containerColor = if (isDark) AuthSurfaceDark else Color.White,
                    contentColor = if (isDark) Color.White else Color(0xFF0F172A)
                )
            ) {
                GoogleLogoIcon()
                Spacer(modifier = Modifier.width(12.dp))
                Text("Continue with Google",
                    fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
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
                Text("Already have an account? ",
                    fontSize = 13.sp,
                    color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B))
                TextButton(
                    onClick = onNavigateBack,
                    contentPadding = PaddingValues(0.dp)
                ) {
                    Text("Login",
                        fontSize = 13.sp, fontWeight = FontWeight.Bold,
                        color = AuthPrimary)
                }
            }
        }
    }
}
