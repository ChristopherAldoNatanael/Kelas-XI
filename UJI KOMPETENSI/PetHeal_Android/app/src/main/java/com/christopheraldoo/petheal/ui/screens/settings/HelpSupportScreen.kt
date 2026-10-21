package com.christopheraldoo.petheal.ui.screens.settings

import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.TextSecondary

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
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
import androidx.compose.material.icons.filled.BugReport
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.HelpOutline
import androidx.compose.material.icons.filled.SupportAgent
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.collectAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.ui.components.ClinicContactActions
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.util.normalizeWhatsAppNumber
import com.christopheraldoo.petheal.util.openClinicEmail
import com.christopheraldoo.petheal.util.openWhatsApp

/**
 * Halaman Bantuan & Dukungan.
 *
 * Memakai komponen kontak yang sama dengan sheet Bantuan di alur booking:
 * WhatsApp admin klinik, telepon, dan email — semuanya dari Clinic Profile.
 * Tidak ada lagi alamat support yang di-hardcode.
 */
@Composable
fun HelpSupportScreen(
    onNavigateBack: () -> Unit,
    viewModel: HelpSupportViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current
    val bg = Background
    val primary = Primary
    val textPrimary = OnBackground
    val textSecondary = TextSecondary

    LaunchedEffect(Unit) { viewModel.loadSupportContact() }

    val clinicName = state.contact?.clinicName?.trim()?.takeIf { it.isNotBlank() }
    val hasWhatsApp = normalizeWhatsAppNumber(state.contact?.phone) != null

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(bg)
            .statusBarsPadding()
            .navigationBarsPadding()
            .verticalScroll(rememberScrollState())
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.verticalGradient(
                        listOf(Color.White, PrimaryLight)
                    )
                )
                .padding(top = 18.dp, start = 18.dp, end = 18.dp, bottom = 26.dp)
        ) {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = textPrimary)
                    }
                    Text(
                        "Bantuan & Dukungan",
                        color = textPrimary,
                        fontSize = 19.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
                Spacer(Modifier.height(18.dp))
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                    shape = RoundedCornerShape(28.dp),
                    elevation = CardDefaults.cardElevation(0.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(20.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(58.dp)
                                .clip(RoundedCornerShape(20.dp))
                                .background(primary.copy(alpha = 0.14f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Filled.SupportAgent,
                                contentDescription = null,
                                tint = primary,
                                modifier = Modifier.size(32.dp)
                            )
                        }
                        Spacer(Modifier.width(16.dp))
                        Column(Modifier.weight(1f)) {
                            Text(
                                "Butuh bantuan?",
                                color = textPrimary,
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                clinicName?.let {
                                    "Admin $it siap membantu masalah booking, pembayaran, dan akun."
                                } ?: "Admin klinik siap membantu masalah booking, pembayaran, dan akun.",
                                color = textSecondary,
                                fontSize = 13.sp,
                                lineHeight = 19.sp
                            )
                        }
                    }
                }
            }
        }

        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            ClinicContactActions(
                contact = state.contact,
                message = state.helpMessage,
                isLoading = state.isLoading,
                error = state.error,
                onRetry = { viewModel.loadSupportContact(forceRefresh = true) }
            )

            SectionTitle("FAQ Cepat")
            FaqCard(
                question = "Bagaimana cara membuat booking?",
                answer = "Pilih dokter, pilih hewan, tentukan tanggal dan jam yang tersedia, lalu selesaikan tipe pembayaran."
            )
            FaqCard(
                question = "Kenapa jam tertentu tidak bisa dipilih?",
                answer = "Jam yang sudah lewat hari ini tidak dapat dipilih. Pilih jam berikutnya atau tanggal besoknya."
            )
            FaqCard(
                question = "Kenapa pembayaran saya pending?",
                answer = "Status pending berarti transaksi masih menunggu konfirmasi dari payment gateway. Cek kembali dari halaman Booking Saya."
            )
            FaqCard(
                question = "Di mana melihat rekam medis?",
                answer = "Rekam medis tersedia setelah konsultasi selesai dan dapat dibuka dari menu Rekam Medis atau detail booking."
            )

            SectionTitle("Laporkan Masalah")
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(22.dp))
                    .clickable {
                        if (hasWhatsApp) {
                            openWhatsApp(context, state.contact?.phone, state.reportMessage)
                        } else {
                            openClinicEmail(
                                context = context,
                                address = state.contact?.email,
                                subject = "Laporan masalah aplikasi" +
                                    (clinicName?.let { " - $it" } ?: "")
                            )
                        }
                    },
                color = Color(0xFFFFF7ED),
                shape = RoundedCornerShape(22.dp)
            ) {
                Row(
                    modifier = Modifier.padding(18.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .background(Color(0xFFF97316).copy(alpha = 0.14f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Filled.BugReport, contentDescription = null, tint = WarningDeep)
                    }
                    Spacer(Modifier.width(14.dp))
                    Column(Modifier.weight(1f)) {
                        Text(
                            "Laporkan Bug",
                            fontWeight = FontWeight.Bold,
                            color = textPrimary,
                            fontSize = 15.sp
                        )
                        Text(
                            if (hasWhatsApp) {
                                "Chat admin dengan kronologi singkat masalah yang Anda alami."
                            } else {
                                "Kirim kronologi dan screenshot melalui email klinik."
                            },
                            color = textSecondary,
                            fontSize = 12.sp
                        )
                    }
                    Icon(Icons.Filled.ExpandMore, contentDescription = null, tint = textSecondary)
                }
            }
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Text(text, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = OnBackground)
}

@Composable
private fun FaqCard(question: String, answer: String) {
    var expanded by remember { mutableStateOf(false) }
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { expanded = !expanded },
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.HelpOutline,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(Modifier.width(10.dp))
                Text(
                    question,
                    modifier = Modifier.weight(1f),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
                Icon(Icons.Filled.ExpandMore, contentDescription = null, tint = TextSecondary)
            }
            if (expanded) {
                Spacer(Modifier.height(12.dp))
                Divider(color = Outline)
                Spacer(Modifier.height(12.dp))
                Text(answer, fontSize = 13.sp, lineHeight = 20.sp, color = TextSecondary)
            }
        }
    }
}
