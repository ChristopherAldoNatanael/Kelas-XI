package com.christopheraldoo.petheal.ui.components

import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft

/**
 * Pesan gagal untuk permintaan ulang (mis. filter diubah) yang TIDAK boleh
 * mengosongkan layar.
 *
 * Dipakai layar discovery: daftar yang sudah tampil tetap terlihat, sedangkan
 * kegagalan muncul sebagai banner tipis di atasnya dengan aksi "Coba lagi".
 * Ini mencegah satu request gagal (jaringan putus, filter ditolak server)
 * terlihat seperti halaman rusak.
 */
@Composable
fun InlineErrorBanner(
    message: String,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = Dimens.ScreenPaddingHorizontal),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = WarningSoft
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = message,
                fontSize = 12.sp,
                color = WarningDeep,
                modifier = Modifier.weight(1f)
            )
            TextButton(onClick = onRetry, contentPadding = PaddingValues(0.dp)) {
                Text("Coba lagi", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Primary)
            }
        }
    }
}
