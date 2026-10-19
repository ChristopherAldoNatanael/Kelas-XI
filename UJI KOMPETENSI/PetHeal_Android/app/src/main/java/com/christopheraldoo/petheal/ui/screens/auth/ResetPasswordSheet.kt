package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.animation.Crossfade
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.ui.components.OtpInput
import com.christopheraldoo.petheal.ui.theme.Error
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.SuccessDeep
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import kotlinx.coroutines.delay

/**
 * Lembar reset kata sandi: email → kode verifikasi → kata sandi baru.
 *
 * Dipisah dari layar masuk karena alurnya tiga langkah dan butuh ruang untuk
 * input kode per digit. Semua langkah memakai satu lembar (bukan dialog
 * bertumpuk) supaya user selalu tahu posisinya; `Pengaturan` sengaja tidak
 * ditambahkan di sini, reset sandi hanya untuk akun email (akun Google tidak
 * punya kata sandi).
 */
private const val CODE_LENGTH = 6
private const val MIN_PASSWORD_LENGTH = 8
private const val RESEND_SECONDS = 60

private const val STEP_EMAIL = 0
private const val STEP_CODE = 1
private const val STEP_DONE = 2

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun ResetPasswordSheet(
    initialEmail: String,
    viewModel: LoginViewModel,
    onDismiss: () -> Unit,
    onCompleted: () -> Unit,
) {
    var step by rememberSaveable { mutableStateOf(STEP_EMAIL) }
    var email by rememberSaveable { mutableStateOf(initialEmail) }
    var code by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var passwordVisible by rememberSaveable { mutableStateOf(false) }
    var busy by rememberSaveable { mutableStateOf(false) }
    var resending by rememberSaveable { mutableStateOf(false) }
    var error by rememberSaveable { mutableStateOf<String?>(null) }
    var codeRejected by rememberSaveable { mutableStateOf(false) }
    var resendLeft by rememberSaveable { mutableStateOf(0) }

    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val focusManager = LocalFocusManager.current

    val hintEmailInvalid = stringResource(R.string.reset_error_email)
    val hintPasswordShort = stringResource(R.string.reset_error_password)

    LaunchedEffect(resendLeft) {
        if (resendLeft > 0) {
            delay(1_000)
            resendLeft--
        }
    }

    val emailValid = run {
        val trimmed = email.trim()
        trimmed.contains('@') && trimmed.substringAfter('@').contains('.')
    }
    val canSubmit = when (step) {
        STEP_EMAIL -> emailValid && !busy
        STEP_CODE -> code.length == CODE_LENGTH && password.length >= MIN_PASSWORD_LENGTH && !busy
        else -> true
    }

    fun requestCode() {
        if (!emailValid) {
            error = hintEmailInvalid
            return
        }
        busy = true
        error = null
        viewModel.requestForgotPassword(email.trim()) { result ->
            busy = false
            when (result) {
                is Result.Success -> {
                    step = STEP_CODE
                    code = ""
                    codeRejected = false
                    resendLeft = RESEND_SECONDS
                }
                is Result.Error -> error = result.message
                else -> Unit
            }
        }
    }

    fun resendCode() {
        if (resendLeft > 0 || busy || resending) return
        resending = true
        error = null
        viewModel.requestForgotPassword(email.trim()) { result ->
            resending = false
            when (result) {
                is Result.Success -> {
                    resendLeft = RESEND_SECONDS
                    codeRejected = false
                }
                is Result.Error -> error = result.message
                else -> Unit
            }
        }
    }

    fun submitCode() {
        if (password.length < MIN_PASSWORD_LENGTH) {
            error = hintPasswordShort
            return
        }
        busy = true
        error = null
        focusManager.clearFocus()
        viewModel.verifyResetCode(email.trim(), code) { verified ->
            when (verified) {
                is Result.Success -> viewModel.resetPassword(email.trim(), code, password) { reset ->
                    busy = false
                    when (reset) {
                        is Result.Success -> step = STEP_DONE
                        is Result.Error -> error = reset.message
                        else -> Unit
                    }
                }
                is Result.Error -> {
                    busy = false
                    codeRejected = true
                    error = verified.message
                }
                else -> {
                    busy = false
                }
            }
        }
    }

    ModalBottomSheet(
        onDismissRequest = { if (!busy) onDismiss() },
        sheetState = sheetState,
        containerColor = Surface,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
        tonalElevation = 0.dp,
        scrimColor = OnBackground.copy(alpha = 0.45f),
        dragHandle = {
            Box(
                modifier = Modifier
                    .padding(top = 10.dp, bottom = 4.dp)
                    .width(36.dp)
                    .height(4.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(Outline)
            )
        }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .imePadding()
                .navigationBarsPadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp)
                .padding(top = 8.dp, bottom = 20.dp)
        ) {
            // ── Baris aksi atas ───────────────────────────────────────
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (step == STEP_CODE) {
                    IconButton(
                        onClick = {
                            step = STEP_EMAIL
                            error = null
                            codeRejected = false
                        },
                        enabled = !busy,
                        modifier = Modifier.size(40.dp)
                    ) {
                        Icon(
                            Icons.Filled.ArrowBack,
                            contentDescription = stringResource(R.string.reset_change_email),
                            tint = OnBackground,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                } else {
                    Spacer(modifier = Modifier.size(40.dp))
                }

                Spacer(modifier = Modifier.weight(1f))

                IconButton(
                    onClick = { if (!busy) onDismiss() },
                    enabled = !busy,
                    modifier = Modifier.size(40.dp)
                ) {
                    Icon(
                        Icons.Filled.Close,
                        contentDescription = stringResource(R.string.reset_close),
                        tint = TextSecondary,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            // ── Indikator langkah ─────────────────────────────────────
            if (step != STEP_DONE) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    repeat(2) { index ->
                        val active = if (step == STEP_EMAIL) index == 0 else true
                        val color by animateColorAsState(
                            targetValue = if (active) Primary else Outline,
                            animationSpec = tween(220),
                            label = "reset-step-$index"
                        )
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .height(3.dp)
                                .clip(RoundedCornerShape(2.dp))
                                .background(color)
                        )
                    }
                }
            }

            Crossfade(
                targetState = step,
                animationSpec = tween(180),
                label = "reset-content"
            ) { current ->
                when (current) {
                    STEP_EMAIL -> Column(modifier = Modifier.fillMaxWidth()) {
                        Spacer(modifier = Modifier.height(22.dp))
                        Text(
                            text = stringResource(R.string.reset_eyebrow).uppercase(),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            letterSpacing = 1.sp,
                            color = TextTertiary
                        )
                        Spacer(modifier = Modifier.height(10.dp))
                        Text(
                            text = stringResource(R.string.reset_title_email),
                            fontSize = 24.sp,
                            lineHeight = 30.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = (-0.3).sp,
                            color = OnBackground
                        )
                        Spacer(modifier = Modifier.height(10.dp))
                        Text(
                            text = stringResource(R.string.reset_desc_email),
                            fontSize = 13.5.sp,
                            lineHeight = 19.sp,
                            color = TextSecondary
                        )
                        Spacer(modifier = Modifier.height(24.dp))
                        AuthFieldLabel(stringResource(R.string.reset_email_label))
                        Spacer(modifier = Modifier.height(8.dp))
                        AuthTextField(
                            value = email,
                            onValueChange = {
                                email = it
                                error = null
                            },
                            placeholder = stringResource(R.string.reset_email_placeholder),
                            leadingIcon = {
                                Icon(
                                    Icons.Filled.Email,
                                    contentDescription = null,
                                    tint = TextSecondary,
                                    modifier = Modifier.size(20.dp)
                                )
                            },
                            keyboardOptions = KeyboardOptions(
                                keyboardType = KeyboardType.Email,
                                imeAction = ImeAction.Done
                            ),
                            keyboardActions = KeyboardActions(
                                onDone = {
                                    focusManager.clearFocus()
                                    if (canSubmit) requestCode()
                                }
                            )
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            text = stringResource(R.string.reset_expiry_hint),
                            fontSize = 11.5.sp,
                            lineHeight = 16.sp,
                            color = TextTertiary
                        )
                    }

                    STEP_CODE -> Column(modifier = Modifier.fillMaxWidth()) {
                        Spacer(modifier = Modifier.height(22.dp))
                        Text(
                            text = stringResource(R.string.reset_title_code),
                            fontSize = 24.sp,
                            lineHeight = 30.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = (-0.3).sp,
                            color = OnBackground
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = stringResource(R.string.reset_desc_code, email.trim()),
                                fontSize = 13.sp,
                                lineHeight = 18.sp,
                                color = TextSecondary,
                                modifier = Modifier.weight(1f)
                            )
                            TextButton(
                                onClick = {
                                    step = STEP_EMAIL
                                    error = null
                                    codeRejected = false
                                },
                                enabled = !busy,
                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                            ) {
                                Text(
                                    text = stringResource(R.string.reset_change_email),
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = Primary
                                )
                            }
                        }
                        Spacer(modifier = Modifier.height(20.dp))
                        AuthFieldLabel(stringResource(R.string.reset_code_label))
                        Spacer(modifier = Modifier.height(10.dp))
                        OtpInput(
                            value = code,
                            onValueChange = {
                                code = it.take(CODE_LENGTH)
                                codeRejected = false
                                error = null
                            },
                            enabled = !busy,
                            isError = codeRejected,
                            modifier = Modifier.fillMaxWidth()
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            if (resendLeft > 0) {
                                Text(
                                    text = stringResource(R.string.otp_resend_wait, resendLeft),
                                    fontSize = 12.sp,
                                    color = TextTertiary
                                )
                            } else {
                                TextButton(
                                    onClick = { resendCode() },
                                    enabled = !busy && !resending,
                                    contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                                ) {
                                    Text(
                                        text = stringResource(R.string.reset_resend_action),
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Primary
                                    )
                                }
                            }
                        }
                        Spacer(modifier = Modifier.height(18.dp))
                        AuthFieldLabel(stringResource(R.string.reset_password_label))
                        Spacer(modifier = Modifier.height(8.dp))
                        AuthTextField(
                            value = password,
                            onValueChange = {
                                password = it
                                error = null
                            },
                            placeholder = stringResource(R.string.reset_password_placeholder),
                            trailingIcon = {
                                IconButton(
                                    onClick = { passwordVisible = !passwordVisible },
                                    modifier = Modifier.size(40.dp)
                                ) {
                                    Icon(
                                        imageVector = if (passwordVisible) Icons.Filled.Visibility
                                        else Icons.Filled.VisibilityOff,
                                        contentDescription = if (passwordVisible) {
                                            "Sembunyikan kata sandi"
                                        } else {
                                            "Tampilkan kata sandi"
                                        },
                                        tint = TextSecondary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                            },
                            visualTransformation = if (passwordVisible) {
                                VisualTransformation.None
                            } else {
                                PasswordVisualTransformation()
                            },
                            keyboardOptions = KeyboardOptions(
                                keyboardType = KeyboardType.Password,
                                imeAction = ImeAction.Done
                            ),
                            keyboardActions = KeyboardActions(
                                onDone = {
                                    focusManager.clearFocus()
                                    if (canSubmit) submitCode()
                                }
                            )
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        PasswordStrength(password = password)
                    }

                    else -> Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Spacer(modifier = Modifier.height(28.dp))
                        Box(
                            modifier = Modifier
                                .size(76.dp)
                                .clip(CircleShape)
                                .background(SuccessSoft),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.CheckCircle,
                                contentDescription = null,
                                tint = SuccessDeep,
                                modifier = Modifier.size(38.dp)
                            )
                        }
                        Spacer(modifier = Modifier.height(20.dp))
                        Text(
                            text = stringResource(R.string.reset_success_title),
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = (-0.2).sp,
                            color = OnBackground,
                            textAlign = TextAlign.Center
                        )
                        Spacer(modifier = Modifier.height(10.dp))
                        Text(
                            text = stringResource(R.string.reset_success_desc),
                            fontSize = 13.5.sp,
                            lineHeight = 19.sp,
                            color = TextSecondary,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(horizontal = 8.dp)
                        )
                    }
                }
            }

            // ── Pesan kesalahan ───────────────────────────────────────
            if (error != null) {
                Spacer(modifier = Modifier.height(16.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(ErrorSoft)
                        .padding(horizontal = 12.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.Top
                ) {
                    Icon(
                        Icons.Filled.ErrorOutline,
                        contentDescription = null,
                        tint = ErrorStrong,
                        modifier = Modifier
                            .padding(top = 1.dp)
                            .size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = error.orEmpty(),
                        fontSize = 12.5.sp,
                        lineHeight = 17.sp,
                        color = Error
                    )
                }
            }

            // ── Aksi utama ────────────────────────────────────────────
            Spacer(modifier = Modifier.height(22.dp))
            Button(
                onClick = {
                    when (step) {
                        STEP_EMAIL -> requestCode()
                        STEP_CODE -> submitCode()
                        else -> onCompleted()
                    }
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                enabled = canSubmit,
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Primary,
                    contentColor = Color.White,
                    disabledContainerColor = SurfaceMuted,
                    disabledContentColor = TextTertiary
                )
            ) {
                if (busy) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(20.dp),
                        color = Color.White,
                        strokeWidth = 2.dp
                    )
                } else {
                    Text(
                        text = when (step) {
                            STEP_EMAIL -> stringResource(R.string.reset_action_send)
                            STEP_CODE -> stringResource(R.string.reset_action_save)
                            else -> stringResource(R.string.reset_success_action)
                        },
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }
    }
}

/**
 * Tiga batang tipis + satu kalimat yang menyebut aturan yang belum terpenuhi.
 * Sengaja bukan skor angka: user hanya perlu tahu apa yang harus ditambahkan.
 */
@Composable
private fun PasswordStrength(password: String) {
    val hasLength = password.length >= MIN_PASSWORD_LENGTH
    val hasMixedCase = password.any { it.isUpperCase() } && password.any { it.isLowerCase() }
    val hasDigitOrSymbol = password.any { it.isDigit() } || password.any { !it.isLetterOrDigit() }
    val score = listOf(hasLength, hasMixedCase, hasDigitOrSymbol).count { it }

    val caption = when {
        !hasLength -> stringResource(R.string.reset_rule_length)
        !password.any { it.isUpperCase() } -> stringResource(R.string.reset_rule_case)
        !hasDigitOrSymbol -> stringResource(R.string.reset_rule_digit)
        else -> stringResource(R.string.reset_strength_strong)
    }
    val activeColor = when {
        score <= 1 -> ErrorStrong
        score == 2 -> Tertiary
        else -> SuccessDeep
    }

    Column(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            repeat(3) { index ->
                val filled = index < score
                val color by animateColorAsState(
                    targetValue = if (filled) activeColor else Outline,
                    animationSpec = tween(220),
                    label = "pw-strength-$index"
                )
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(3.dp)
                        .clip(RoundedCornerShape(2.dp))
                        .background(color)
                )
            }
        }
        Spacer(modifier = Modifier.height(7.dp))
        Text(
            text = caption,
            fontSize = 11.sp,
            color = if (score >= 3) SuccessDeep else TextSecondary
        )
    }
}
