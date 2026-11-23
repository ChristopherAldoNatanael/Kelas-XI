package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.PrimaryWash
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.util.collectAsStateLifecycleAware
import com.christopheraldoo.petheal.util.canonicalPhoneInput

/**
 * Pengisian nomor WhatsApp setelah masuk dengan Google.
 *
 * Google tidak mengirim nomor telepon, jadi akun baru sering masuk tanpa nomor
 * sama sekali. Tanpa nomor, klinik tidak bisa mengabari perubahan jadwal dan
 * tidak punya cara meminta data pengembalian dana saat booking dibatalkan.
 * Layar ini meminta nomor SEKALI, dengan opsi menundanya agar tidak memaksa.
 */
@Composable
fun PhoneSetupScreen(
    onSaved: () -> Unit,
    onSkip: () -> Unit,
    viewModel: PhoneSetupViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsStateLifecycleAware()
    val focusManager = LocalFocusManager.current
    val phoneValid = canonicalPhoneInput(uiState.phone) != null

    LaunchedEffect(uiState.isSaved) {
        if (uiState.isSaved) onSaved()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(AuthBg)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .imePadding()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp)
                .padding(top = 28.dp, bottom = 24.dp)
        ) {
            Text(
                text = "Langkah terakhir",
                fontSize = 12.5.sp,
                fontWeight = FontWeight.SemiBold,
                color = AuthPrimary,
                letterSpacing = 0.4.sp
            )
            Spacer(modifier = Modifier.height(10.dp))
            Text(
                text = "Nomor WhatsApp Anda",
                fontSize = 25.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground
            )
            Spacer(modifier = Modifier.height(10.dp))
            Text(
                text = "Klinik memakai nomor ini untuk mengabari perubahan jadwal, " +
                    "hasil pemeriksaan, dan pengembalian dana. Kami tidak memakainya " +
                    "untuk promosi.",
                fontSize = 14.sp,
                lineHeight = 21.sp,
                color = TextSecondary
            )

            Spacer(modifier = Modifier.height(24.dp))

            AuthFieldLabel("Nama Penerima")
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = uiState.name,
                onValueChange = viewModel::onNameChange,
                placeholder = "Nama lengkap",
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Text,
                    imeAction = ImeAction.Next
                ),
                keyboardActions = KeyboardActions(
                    onNext = { focusManager.moveFocus(androidx.compose.ui.focus.FocusDirection.Down) }
                )
            )

            Spacer(modifier = Modifier.height(20.dp))

            AuthFieldLabel("Nomor WhatsApp")
            Spacer(modifier = Modifier.height(8.dp))
            AuthTextField(
                value = uiState.phone,
                onValueChange = viewModel::onPhoneChange,
                placeholder = "0812 3456 7890",
                leadingIcon = {
                    Icon(
                        Icons.Filled.Phone,
                        contentDescription = null,
                        tint = AuthTextSecondary,
                        modifier = Modifier.size(20.dp)
                    )
                },
                trailingIcon = if (phoneValid) {
                    {
                        Icon(
                            Icons.Filled.CheckCircle,
                            contentDescription = null,
                            tint = AuthPrimary,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                } else null,
                keyboardOptions = KeyboardOptions(
                    keyboardType = KeyboardType.Phone,
                    imeAction = ImeAction.Done
                ),
                keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus() })
            )

            val phoneNote = uiState.phoneError
                ?: "Pakai nomor yang aktif di WhatsApp, bukan nomor kantor atau nomor keluarga."
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = phoneNote,
                fontSize = 12.sp,
                lineHeight = 17.sp,
                color = if (uiState.phoneError != null) MaterialTheme.colorScheme.error
                        else AuthTextSecondary
            )

            uiState.email.takeIf { it.isNotBlank() }?.let { email ->
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Masuk sebagai $email",
                    fontSize = 12.sp,
                    color = AuthTextSecondary
                )
            }

            Spacer(modifier = Modifier.height(20.dp))

            // Kenapa diminta: pasien berhak tahu alasan sebuah formulir muncul.
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(PrimaryWash, RoundedCornerShape(14.dp))
                    .padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.Top
            ) {
                Icon(
                    Icons.Outlined.Info,
                    contentDescription = null,
                    tint = AuthPrimary,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(modifier = Modifier.width(10.dp))
                Text(
                    text = "Akun Google tidak membawa nomor telepon. Tanpa nomor, " +
                        "klinik hanya bisa menghubungi Anda lewat email yang sering " +
                        "terlewat.",
                    fontSize = 12.5.sp,
                    lineHeight = 18.sp,
                    color = AuthTextSecondary
                )
            }

            uiState.error?.let { message ->
                Spacer(modifier = Modifier.height(14.dp))
                Text(
                    text = message,
                    fontSize = 12.5.sp,
                    color = MaterialTheme.colorScheme.error,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }

            Spacer(modifier = Modifier.height(24.dp))

            Button(
                onClick = {
                    focusManager.clearFocus()
                    viewModel.save()
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(54.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AuthPrimary,
                    contentColor = androidx.compose.ui.graphics.Color.White
                )
            ) {
                if (uiState.isSaving) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = androidx.compose.ui.graphics.Color.White,
                        strokeWidth = 2.5.dp
                    )
                } else {
                    Text("Simpan nomor", fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(6.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center
            ) {
                TextButton(onClick = onSkip) {
                    Text(
                        text = "Nanti saja",
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AuthTextSecondary
                    )
                }
            }
        }
    }
}
