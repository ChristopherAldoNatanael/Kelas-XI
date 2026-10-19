package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant

/**
 * Input kode OTP: satu kotak per digit, fokus otomatis pindah ke kotak
 * berikutnya, dan menempel (paste) kode panjang langsung mengisi beberapa
 * kotak. Dipakai layar verifikasi OTP pendaftaran dan dialog reset sandi
 * supaya bentuk inputnya sama di seluruh aplikasi.
 *
 * [value] selalu berisi hanya digit (maksimal [length]); [onValueChange]
 * menerima nilai lengkap yang baru, bukan satu digit.
 */
@Composable
fun OtpInput(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    length: Int = 6,
    enabled: Boolean = true,
    isError: Boolean = false,
) {
    val focusRequesters = remember(length) { List(length) { FocusRequester() } }
    val digits = value.filter { it.isDigit() }.take(length)

    // Fokuskan kotak kosong pertama begitu layar terbuka → keyboard langsung
    // siap, user tidak perlu mengetuk kotak.
    LaunchedEffect(Unit) {
        val target = digits.length.coerceAtMost(length - 1)
        focusRequesters[target].requestFocus()
    }

    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        repeat(length) { index ->
            val current = digits.getOrNull(index)?.toString().orEmpty()

            Box(
                modifier = Modifier
                    .weight(1f)
                    .aspectRatio(0.85f)
                    .clip(RoundedCornerShape(14.dp))
                    .background(if (enabled) Surface else SurfaceVariant)
                    .border(
                        width = if (isError) 1.5.dp else 1.dp,
                        color = if (isError) MaterialTheme.colorScheme.error else Outline,
                        shape = RoundedCornerShape(14.dp)
                    ),
                contentAlignment = Alignment.Center
            ) {
                BasicTextField(
                    value = current,
                    onValueChange = { raw ->
                        val typed = raw.filter { it.isDigit() }
                        when {
                            // Dihapus: buang digit di posisi ini dan sesudahnya.
                            typed.isEmpty() -> onValueChange(digits.take(index))

                            // Ketik/ubah satu digit lalu maju ke kotak berikutnya.
                            typed.length == 1 -> {
                                onValueChange(
                                    (digits.take(index) + typed + digits.drop(index + 1)).take(length)
                                )
                                if (index < length - 1) focusRequesters[index + 1].requestFocus()
                            }

                            // Tempel beberapa digit sekaligus.
                            else -> {
                                onValueChange((digits.take(index) + typed).take(length))
                                val target = (index + typed.length).coerceAtMost(length - 1)
                                focusRequesters[target].requestFocus()
                            }
                        }
                    },
                    enabled = enabled,
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(
                        keyboardType = KeyboardType.NumberPassword,
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { if (index < length - 1) focusRequesters[index + 1].requestFocus() }
                    ),
                    textStyle = TextStyle(
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground,
                        textAlign = TextAlign.Center
                    ),
                    cursorBrush = SolidColor(Primary),
                    modifier = Modifier
                        .fillMaxWidth()
                        .focusRequester(focusRequesters[index])
                )
            }
        }
    }
}
