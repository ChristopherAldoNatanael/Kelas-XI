package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.ui.components.OtpInput
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.PrimaryWash
import com.christopheraldoo.petheal.ui.theme.SuccessDeep
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.TextSecondary

/**
 * Layar verifikasi kode OTP pendaftaran email.
 *
 * Sengaja layar terpisah (bukan langkah di dalam form daftar) supaya jelas:
 * akun belum ada sampai kode ini benar. Tombol "Ganti email" hanya kembali ke
 * form daftar — isian form tetap tersimpan karena memakai `rememberSaveable`.
 */
@Composable
fun OtpVerificationScreen(
    email: String,
    onVerified: () -> Unit,
    onNavigateToCompleteSetup: () -> Unit,
    onNavigateBack: () -> Unit,
    viewModel: OtpVerificationViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var code by rememberSaveable { mutableStateOf("") }

    // Hitung mundur kirim ulang mulai saat layar dibuka, supaya user tidak
    // langsung menembak tombol dan kena rate limit backend.
    LaunchedEffect(Unit) { viewModel.startCountdown() }

    LaunchedEffect(uiState.isVerified) {
        if (uiState.isVerified) onVerified()
    }
    LaunchedEffect(uiState.needsClinicSetup) {
        if (uiState.needsClinicSetup) onNavigateToCompleteSetup()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(listOf(Surface, PrimaryWash, AuthBg))
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .imePadding()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp)
                .padding(top = 20.dp, bottom = 28.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // ── Top app bar ───────────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 24.dp, top = 8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(
                    onClick = onNavigateBack,
                    modifier = Modifier.size(48.dp)
                ) {
                    Icon(
                        Icons.Filled.ArrowBack,
                        contentDescription = "Kembali",
                        tint = OnBackground
                    )
                }
                Text(
                    text = stringResource(R.string.otp_title),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
            }

            Box(
                modifier = Modifier
                    .size(88.dp)
                    .clip(CircleShape)
                    .background(AuthPrimary.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.Email,
                    contentDescription = null,
                    tint = AuthPrimary,
                    modifier = Modifier.size(40.dp)
                )
            }

            Spacer(modifier = Modifier.height(20.dp))

            Text(
                text = stringResource(R.string.otp_heading),
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = stringResource(R.string.otp_subtitle, email),
                fontSize = 14.sp,
                lineHeight = 20.sp,
                color = TextSecondary,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(28.dp))

            OtpInput(
                value = code,
                onValueChange = {
                    code = it
                    if (uiState.error != null) viewModel.clearError()
                },
                enabled = !uiState.isLoading,
                isError = uiState.error != null,
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(modifier = Modifier.height(14.dp))

            Text(
                text = stringResource(R.string.otp_spam_hint),
                fontSize = 12.sp,
                lineHeight = 17.sp,
                color = TextSecondary,
                textAlign = TextAlign.Center
            )

            if (uiState.resendSent) {
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = stringResource(R.string.otp_resend_sent),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Medium,
                    color = SuccessDeep,
                    textAlign = TextAlign.Center
                )
            }

            uiState.error?.let { message ->
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp)
                        .background(
                            MaterialTheme.colorScheme.errorContainer,
                            shape = RoundedCornerShape(8.dp)
                        )
                        .padding(vertical = 12.dp, horizontal = 16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Warning,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onErrorContainer,
                        modifier = Modifier.size(20.dp)
                    )
                    Text(
                        text = message,
                        color = MaterialTheme.colorScheme.onErrorContainer,
                        fontSize = 13.sp,
                        textAlign = TextAlign.Start,
                        modifier = Modifier.weight(1f)
                    )
                }
            }

            Spacer(modifier = Modifier.height(28.dp))

            Button(
                onClick = { viewModel.verify(email, code) },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AuthPrimary,
                    contentColor = Color.White
                ),
                enabled = !uiState.isLoading && code.length == OtpVerificationViewModel.OTP_LENGTH,
                elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = Color.White,
                        strokeWidth = 2.5.dp
                    )
                } else {
                    Text(
                        text = stringResource(R.string.otp_verify_action),
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            Spacer(modifier = Modifier.height(20.dp))

            // ── Kirim ulang ───────────────────────────────────────────
            Row(
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = stringResource(R.string.otp_resend_prompt),
                    fontSize = 13.sp,
                    color = TextSecondary
                )
                Spacer(modifier = Modifier.width(4.dp))
                TextButton(
                    onClick = { viewModel.resend(email) },
                    enabled = !uiState.isResending && uiState.resendCountdown == 0,
                    contentPadding = PaddingValues(0.dp)
                ) {
                    if (uiState.isResending) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(16.dp),
                            strokeWidth = 2.dp,
                            color = AuthPrimary
                        )
                    } else {
                        Text(
                            text = if (uiState.resendCountdown > 0) {
                                stringResource(R.string.otp_resend_wait, uiState.resendCountdown)
                            } else {
                                stringResource(R.string.otp_resend_action)
                            },
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (uiState.resendCountdown > 0) TextSecondary else AuthPrimary
                        )
                    }
                }
            }

            TextButton(
                onClick = onNavigateBack,
                enabled = !uiState.isLoading
            ) {
                Text(
                    text = stringResource(R.string.otp_change_email),
                    fontSize = 13.sp,
                    color = TextSecondary
                )
            }
        }
    }
}
