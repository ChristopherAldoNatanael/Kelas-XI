package com.christopheraldoo.petheal.ui.components

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.defaultMinSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.FolderShared
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.ui.navigation.PetHealMotion
import com.christopheraldoo.petheal.ui.navigation.Screen
import com.christopheraldoo.petheal.ui.theme.OnSurface
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.TextSecondary

/**
 * PHASE 9: satu-satunya bottom navigation untuk semua tab utama.
 *
 * Gaya mengikuti MedicalRecords (ikon dalam pil + label), tetapi floating:
 * elevated, rounded 24dp, melayang di atas bottom edge (tidak menempel),
 * menghormati navigation-bar insets. Tepat 5 tab, seluruhnya Bahasa Indonesia.
 */
enum class PetHealTab(
    val label: String,
    val icon: ImageVector
) {
    Home("Beranda", Icons.Filled.Home),
    Pets("Hewan", Icons.Filled.Pets),
    Bookings("Booking", Icons.Filled.CalendarMonth),
    Records("Rekam Medis", Icons.Filled.FolderShared),
    Profile("Profil", Icons.Filled.Person);
}

/** Route tujuan tiap tab (dipakai NavHost agar switching konsisten). */
val PetHealTab.route: String
    get() = when (this) {
        PetHealTab.Home -> Screen.Home.route
        PetHealTab.Pets -> Screen.Pets.route
        PetHealTab.Bookings -> Screen.Bookings.route
        PetHealTab.Records -> Screen.MedicalRecords.route
        PetHealTab.Profile -> Screen.Profile.route
    }

/**
 * SATU-SATUNYA cara pindah ke layar tab (Beranda/Hewan/Booking/Rekam
 * Medis/Profil) dari titik mana pun — tombol tab, tombol di halaman
 * detail, hasil pembayaran, notifikasi, maupun deep link.
 *
 * Semua entry point WAJIB lewat sini. Navigasi langsung ke route tab
 * (tanpa blok ini) menumpuk entri tab kedua di atas sub-halaman lama:
 * back stack bercabang, entri Home ganda, dan tombol tab berikutnya
 * membuka halaman yang tidak sesuai — gejala yang dilaporkan user di
 * perangkat fisik ("View All Medical Records" mendarat tidak menentu).
 */
fun androidx.navigation.NavHostController.navigateToTab(tab: PetHealTab) {
    // Route tujuan yang BENAR-BENAR terdaftar harus dipakai sebagai pola:
    // popUpTo membandingkan route, bukan pola. Untuk BookingStart yang
    // ber-argumen opsional, pattern "bookings/start?clinicSlug={...}" tidak
    // akan match — jadi dicari dulu di graph.
    val target = runCatching {
        graph.findNode(tab.route)?.route
    }.getOrNull() ?: tab.route

    // Route layar saat ini (terisi, mis. "bookings/create/3/1"), bukan pola.
    val current = currentDestination?.route

    // Sudah di tab itu → tidak ada yang perlu dilakukan.
    if (current == target) return

    navigate(target) {
        // FIX (2026-11-09): popUpTo(Home) TIDAK pernah melepas sub-layar di
        // atas Home, sehingga "Kembali ke Beranda" dari layar hasil
        // pembayaran hanya menambah salinan Home di atas PaymentResult dan
        // tombol terasa mati. Mem-pop layar SAAT INI secara inclusive membuat
        // target tab benar-benar tampil — dari layar mana pun.
        if (current != null) {
            popUpTo(current) { inclusive = true }
        }
        launchSingleTop = true
        restoreState = true
    }
}

/**
 * Kembali dengan jaminan: bila tumpukan kosong, mendarat di Beranda.
 *
 * `popBackStack()` tanpa hasil (tumpukan kosong / layar terakhir) tidak
 * melakukan apa pun — tombol terasa mati. Dipakai semua tombol "Kembali"
 * di alur pembayaran.
 */
fun androidx.navigation.NavHostController.navigateBackOrHome() {
    val popped = popBackStack()
    if (!popped) navigateToTab(PetHealTab.Home)
}

@Composable
fun PetHealFloatingBottomNav(
    selected: PetHealTab,
    onSelect: (PetHealTab) -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp)
            .navigationBarsPadding()
            .padding(bottom = 12.dp),
        shape = RoundedCornerShape(PetHealRadius.xxl),
        color = Surface,
        shadowElevation = 8.dp,
        border = BorderStroke(1.dp, Outline)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 6.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            PetHealTab.entries.forEach { tab ->
                PetHealTabItem(
                    tab = tab,
                    selected = tab == selected,
                    onClick = { onSelect(tab) }
                )
            }
        }
    }
}

@Composable
private fun PetHealTabItem(
    tab: PetHealTab,
    selected: Boolean,
    onClick: () -> Unit
) {
    // Perpindahan tab memakai durasi & kurva yang sama dengan transisi halaman
    // (PetHealMotion), supaya navigasi terasa satu sistem — bukan dua animasi
    // berbeda yang saling menunggu.
    val capsuleColor by animateColorAsState(
        targetValue = if (selected) Primary.copy(alpha = 0.12f) else Color.Transparent,
        animationSpec = tween(PetHealMotion.TabMs, easing = PetHealMotion.EaseOut),
        label = "tab-capsule"
    )
    val iconTint by animateColorAsState(
        targetValue = if (selected) PrimaryDark else TextSecondary,
        animationSpec = tween(PetHealMotion.TabMs, easing = PetHealMotion.EaseOut),
        label = "tab-icon-tint"
    )
    val labelColor by animateColorAsState(
        targetValue = if (selected) OnSurface else TextSecondary,
        animationSpec = tween(PetHealMotion.TabMs, easing = PetHealMotion.EaseOut),
        label = "tab-label-color"
    )
    // Ikon sedikit "pop" saat tab menjadi aktif — umpan balik visual halus.
    val iconScale by animateFloatAsState(
        targetValue = if (selected) 1f else 0.9f,
        animationSpec = spring(dampingRatio = 0.6f, stiffness = 700f),
        label = "tab-icon-scale"
    )

    androidx.compose.foundation.layout.Column(
        modifier = Modifier
            .defaultMinSize(minWidth = 56.dp)
            .heightIn(min = 48.dp)
            .clip(RoundedCornerShape(PetHealRadius.lg))
            .clickable(onClick = onClick)
            .padding(horizontal = 6.dp, vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Box(
            modifier = Modifier
                .clip(RoundedCornerShape(14.dp))
                .background(capsuleColor)
                .padding(horizontal = 10.dp, vertical = 5.dp),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = tab.icon,
                contentDescription = tab.label,
                tint = iconTint,
                modifier = Modifier
                    .size(22.dp)
                    .graphicsLayer {
                        scaleX = iconScale
                        scaleY = iconScale
                    }
            )
        }
        Text(
            text = tab.label,
            color = labelColor,
            fontSize = 11.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            textAlign = TextAlign.Center
        )
    }
}
