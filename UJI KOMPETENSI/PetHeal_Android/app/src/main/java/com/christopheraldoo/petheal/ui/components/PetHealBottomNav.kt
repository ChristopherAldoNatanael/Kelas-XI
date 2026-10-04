package com.christopheraldoo.petheal.ui.components

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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
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
                .background(if (selected) Primary.copy(alpha = 0.12f) else androidx.compose.ui.graphics.Color.Transparent)
                .padding(horizontal = 10.dp, vertical = 5.dp),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = tab.icon,
                contentDescription = tab.label,
                tint = if (selected) PrimaryDark else TextSecondary,
                modifier = Modifier.size(22.dp)
            )
        }
        Text(
            text = tab.label,
            color = if (selected) OnSurface else TextSecondary,
            fontSize = 11.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            textAlign = TextAlign.Center
        )
    }
}
