package com.christopheraldoo.petheal.ui.screens.medicalrecord

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.CachePolicy
import coil.request.ImageRequest
import com.christopheraldoo.petheal.util.buildPhotoUrl
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.MediumImage
import java.time.LocalDate
import java.time.format.DateTimeFormatter

// ── Brand tokens ───────────────────────────────────────────────────────────────
private val MrPrimary       = Color(0xFF2BEE6C)
private val MrPrimaryFg     = Color(0xFF052E14)
private val MrBgDark        = Color(0xFFF6F8F6)
private val MrBgLight       = Color(0xFFF6F8F6)
private val MrSurfaceDark   = Color.White
private val MrSurfaceLight  = Color(0xFFFFFFFF)
private val MrBorderDark    = Color(0xFFE2E8F0)
private val MrBorderLight   = Color(0xFFE2E8F0)
private val MrSecDark       = Color(0xFF64748B)

// ── Helper: doctor photo ────────────────────────────────────────────────────
@Composable
private fun MrDocPhoto(url: String?, size: androidx.compose.ui.unit.Dp) {
    val context = LocalContext.current
    val fullUrl = remember(url) { buildPhotoUrl(url) }
    var hasError by remember(fullUrl) { mutableStateOf(false) }
    if (!fullUrl.isNullOrBlank() && !hasError) {
        // ✅ OPTIMIZED: ThumbnailImage resizes to 100px BEFORE decode → huge memory savings!
        ThumbnailImage(
            model = fullUrl,
            contentDescription = null,
            modifier = Modifier.fillMaxSize().clip(CircleShape)
        )
    } else {
        Icon(Icons.Filled.Person, null, tint = MrPrimary, modifier = Modifier.size(size * 0.5f))
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  MEDICAL RECORDS SCREEN  (Timeline)
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun MedicalRecordsScreen(
    onNavigateBack: () -> Unit,
    onNavigateToRecordDetail: (Int) -> Unit,
    onNavigateToHome: () -> Unit,
    onNavigateToBookings: () -> Unit,
    onNavigateToProfile: () -> Unit,
    viewModel: MedicalRecordsViewModel = hiltViewModel()
) {
    val state  by viewModel.listState.collectAsState()
    val isDark = false

    val bg      = if (isDark) MrBgDark      else MrBgLight
    val surface = if (isDark) MrSurfaceDark else MrSurfaceLight
    val border  = if (isDark) MrBorderDark  else MrBorderLight
    val textPri = if (isDark) Color.White   else Color(0xFF0F172A)
    val textSec = if (isDark) MrSecDark     else Color(0xFF64748B)

    LaunchedEffect(Unit) { viewModel.loadRecords() }

    Box(Modifier.fillMaxSize().background(bg)) {
        Column(Modifier.fillMaxSize()) {

            // ── Sticky Header ──────────────────────────────────────────────
            Surface(
                modifier   = Modifier.fillMaxWidth(),
                color      = (if (isDark) MrBgDark else MrBgLight).copy(alpha = 0.97f),
                shadowElevation = 0.dp
            ) {
                Column {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 4.dp)
                            .padding(top = 44.dp, bottom = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            Modifier.size(44.dp).clip(CircleShape).clickable { onNavigateBack() },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.ArrowBack, null, tint = textPri, modifier = Modifier.size(24.dp))
                        }
                        Text("Medical Records", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = textPri)
                        Box(
                            Modifier.size(44.dp).clip(CircleShape).clickable {},
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.FilterList, null, tint = textPri, modifier = Modifier.size(22.dp))
                        }
                    }
                    Divider(color = border, thickness = 1.dp)

                    // Filter chips
                    LazyRow(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 10.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(viewModel.filterCategories) { cat ->
                            MrFilterChip(
                                label      = cat,
                                isSelected = state.selectedFilter == cat,
                                isDark     = isDark,
                                surface    = surface,
                                border     = border,
                                onClick    = { viewModel.setFilter(cat) }
                            )
                        }
                    }
                }
            }

            // ── Body ───────────────────────────────────────────────────────
            when {
                state.isLoading -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = MrPrimary)
                    }
                }
                state.error != null -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
                            Icon(Icons.Filled.ErrorOutline, null, tint = Color(0xFFEF4444), modifier = Modifier.size(48.dp))
                            Spacer(Modifier.height(12.dp))
                            Text(state.error ?: "Error", color = textSec, fontSize = 14.sp, textAlign = TextAlign.Center)
                            Spacer(Modifier.height(16.dp))
                            Button(onClick = { viewModel.loadRecords() },
                                colors = ButtonDefaults.buttonColors(containerColor = MrPrimary, contentColor = MrPrimaryFg)
                            ) { Text("Retry") }
                        }
                    }
                }
                state.filteredRecords.isEmpty() -> {
                    MrEmptyState(textPri = textPri, textSec = textSec)
                }
                else -> {
                    val today    = LocalDate.now().toString()
                    val upcoming = state.filteredRecords.filter { it.nextVisitDate != null && it.nextVisitDate!! >= today }
                    val past     = state.filteredRecords.filter { it.nextVisitDate == null || it.nextVisitDate!! < today }

                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 96.dp)
                    ) {
                        if (upcoming.isNotEmpty()) {
                            item { MrSectionLabel("Upcoming", textSec) }
                            items(upcoming, key = { it.id ?: 0 }) { rec ->
                                MrTimelineItem(
                                    record    = rec,
                                    isUpcoming = true,
                                    isOldest  = false,
                                    isDark    = isDark,
                                    surface   = surface,
                                    border    = border,
                                    bg        = bg,
                                    textPri   = textPri,
                                    textSec   = textSec,
                                    onClick   = { rec.id?.let { onNavigateToRecordDetail(it) } }
                                )
                                Spacer(Modifier.height(16.dp))
                            }
                        }
                        if (past.isNotEmpty()) {
                            item { MrSectionLabel("Past History", textSec) }
                            itemsIndexed(past, key = { _, r -> r.id ?: 0 }) { idx, rec ->
                                MrTimelineItem(
                                    record     = rec,
                                    isUpcoming = false,
                                    isOldest   = idx == past.lastIndex,
                                    isDark     = isDark,
                                    surface    = surface,
                                    border     = border,
                                    bg         = bg,
                                    textPri    = textPri,
                                    textSec    = textSec,
                                    onClick    = { rec.id?.let { onNavigateToRecordDetail(it) } }
                                )
                                Spacer(Modifier.height(16.dp))
                            }
                        }
                    }
                }
            }
        }

        // ── Bottom Nav ─────────────────────────────────────────────────────
        MrBottomNav(
            modifier        = Modifier.align(Alignment.BottomCenter),
            isDark          = isDark,
            surface         = surface,
            border          = border,
            onHome          = onNavigateToHome,
            onBookings      = onNavigateToBookings,
            onProfile       = onNavigateToProfile
        )
    }
}

// ── Section label ─────────────────────────────────────────────────────────────
@Composable
private fun MrSectionLabel(text: String, textSec: Color) {
    Text(
        text.uppercase(),
        fontSize = 11.sp, fontWeight = FontWeight.Bold,
        color = textSec, letterSpacing = 1.sp,
        modifier = Modifier.padding(start = 24.dp, bottom = 12.dp, top = 4.dp)
    )
}

// ── Filter chip ───────────────────────────────────────────────────────────────
@Composable
private fun MrFilterChip(
    label: String, isSelected: Boolean, isDark: Boolean,
    surface: Color, border: Color, onClick: () -> Unit
) {
    val bg  by animateColorAsState(if (isSelected) MrPrimary else surface, tween(180), label = "")
    val txt by animateColorAsState(
        if (isSelected) MrPrimaryFg else if (isDark) MrSecDark else Color(0xFF64748B),
        tween(180), label = ""
    )
    Box(
        modifier = Modifier
            .clip(CircleShape)
            .background(bg)
            .then(if (!isSelected) Modifier.border(1.dp, border, CircleShape) else Modifier)
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 6.dp)
    ) {
        Text(label, fontSize = 13.sp, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium, color = txt)
    }
}

// ── Timeline item ─────────────────────────────────────────────────────────────
@Composable
private fun MrTimelineItem(
    record: com.christopheraldoo.petheal.data.model.MedicalRecord,
    isUpcoming: Boolean,
    isOldest: Boolean,
    isDark: Boolean,
    surface: Color,
    border: Color,
    bg: Color,
    textPri: Color,
    textSec: Color,
    onClick: () -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    val lineColor = if (isDark) MrBorderDark else Color(0xFFCBD5E1)
    val dotColor  = if (isUpcoming) MrPrimary else if (isDark) MrBorderDark else Color(0xFFCBD5E1)
    val cardAlpha = if (isOldest) 0.72f else 1f

    val petName  = record.booking?.pet?.name ?: "Pet"
    val petSpec  = record.booking?.pet?.species
    val docName  = record.booking?.doctor?.name ?: "–"
    val diagnosis = record.diagnosis ?: "General Consultation"
    val date     = mrFormatDate(record.createdAt)
    val time     = mrFormatTime(record.createdAt)

    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {

        // Dot + vertical line
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(20.dp)) {
            Spacer(Modifier.height(20.dp))
            Box(
                modifier = Modifier
                    .size(12.dp)
                    .clip(CircleShape)
                    .background(dotColor)
                    .border(3.dp, bg, CircleShape)
            )
            if (!isOldest) {
                Box(
                    modifier = Modifier
                        .width(2.dp)
                        .defaultMinSize(minHeight = 40.dp)
                        .weight(1f)
                        .background(
                            Brush.verticalGradient(listOf(lineColor, lineColor.copy(alpha = 0.2f)))
                        )
                )
            }
        }

        // Card
        Surface(
            modifier = Modifier
                .weight(1f)
                .clip(RoundedCornerShape(16.dp))
                .clickable {
                    if (isUpcoming) onClick()
                    else expanded = !expanded
                },
            color = surface.copy(alpha = cardAlpha),
            shape = RoundedCornerShape(16.dp),
            shadowElevation = 2.dp
        ) {
            Column(Modifier.border(1.dp, border.copy(alpha = cardAlpha), RoundedCornerShape(16.dp))) {

                // ── Header ──────────────────────────────────────────
                Column(Modifier.fillMaxWidth().padding(16.dp)) {
                    Row(
                        Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.Top
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            if (isUpcoming) {
                                MrBadge("Upcoming", Color(0xFF60A5FA), Color(0xFF3B82F6).copy(alpha = 0.1f), Color(0xFF3B82F6).copy(alpha = 0.2f))
                                Spacer(Modifier.height(6.dp))
                            }
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                Text(
                                    diagnosis,
                                    fontSize = 15.sp, fontWeight = FontWeight.Bold,
                                    color = textPri, maxLines = 2, overflow = TextOverflow.Ellipsis
                                )
                                if (!isUpcoming) {
                                    Icon(Icons.Filled.CheckCircle, null, tint = MrPrimary, modifier = Modifier.size(16.dp))
                                }
                            }
                        }
                        Spacer(Modifier.width(8.dp))
                        Column(horizontalAlignment = Alignment.End) {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(bg)
                                    .border(1.dp, border, RoundedCornerShape(6.dp))
                                    .padding(horizontal = 8.dp, vertical = 4.dp)
                            ) {
                                Text(date, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = textPri)
                            }
                            if (time.isNotBlank()) {
                                Spacer(Modifier.height(3.dp))
                                Text(time, fontSize = 10.sp, color = textSec)
                            }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {                        // Doctor mini avatar
                        val docPhoto = record.booking?.doctor?.photo
                        Box(
                            modifier = Modifier
                                .size(22.dp)
                                .clip(CircleShape)
                                .background(if (!docPhoto.isNullOrBlank()) Color.Transparent else MrPrimary.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            MrDocPhoto(url = docPhoto, size = 22.dp)
                        }
                        Text(
                            "$docName${if (!petSpec.isNullOrBlank()) " • $petName ($petSpec)" else " • $petName"}",
                            fontSize = 12.sp, color = textSec,
                            maxLines = 1, overflow = TextOverflow.Ellipsis
                        )
                    }

                    // Upcoming: notes preview + action buttons
                    if (isUpcoming) {
                        if (!record.notes.isNullOrBlank()) {
                            Spacer(Modifier.height(10.dp))
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(bg.copy(alpha = 0.7f))
                                    .border(1.dp, border.copy(alpha = 0.5f), RoundedCornerShape(10.dp))
                                    .padding(10.dp)
                            ) {
                                Text(record.notes ?: "", fontSize = 11.sp, color = textSec, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 16.sp)
                            }
                        }
                        Spacer(Modifier.height(10.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Button(
                                onClick = onClick,
                                modifier = Modifier.weight(1f).height(36.dp),
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = MrPrimary.copy(alpha = 0.15f), contentColor = MrPrimary),
                                contentPadding = PaddingValues(0.dp)
                            ) { Text("Reschedule", fontSize = 12.sp, fontWeight = FontWeight.Bold) }
                            OutlinedButton(
                                onClick = onClick,
                                modifier = Modifier.weight(1f).height(36.dp),
                                shape = RoundedCornerShape(8.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, border),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = textPri),
                                contentPadding = PaddingValues(0.dp)
                            ) { Text("Details", fontSize = 12.sp, fontWeight = FontWeight.Bold) }
                        }
                    }

                    // Past collapsed: tags
                    if (!isUpcoming && !expanded) {
                        Spacer(Modifier.height(10.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            MrBadge(
                                mrDiagnosisCategory(diagnosis),
                                MrPrimary, MrPrimary.copy(alpha = 0.1f), MrPrimary.copy(alpha = 0.2f)
                            )
                            MrBadge("Completed", Color(0xFF94A3B8), Color(0xFF94A3B8).copy(alpha = 0.1f), Color(0xFF94A3B8).copy(alpha = 0.2f))
                        }
                    }
                }

                // ── Expanded body (past) ───────────────────────────
                if (!isUpcoming && expanded) {
                    Divider(color = border.copy(alpha = 0.5f))
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(bg.copy(alpha = 0.3f))
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        if (!record.diagnosis.isNullOrBlank()) {
                            MrDetailRow(Icons.Filled.HealthAndSafety, Color(0xFF10B981), "Diagnosis", record.diagnosis, textPri, textSec)
                        }
                        if (!record.treatment.isNullOrBlank()) {
                            MrDetailRow(Icons.Filled.Vaccines, Color(0xFF3B82F6), "Treatment / Prescribed", record.treatment, textPri, textSec)
                        }
                        if (!record.medicine.isNullOrBlank()) {
                            MrDetailRow(Icons.Filled.MedicalServices, Color(0xFFF59E0B), "Medicine", record.medicine, textPri, textSec)
                        }
                        if (!record.notes.isNullOrBlank()) {
                            MrDetailRow(Icons.Filled.Notes, Color(0xFF8B5CF6), "Notes", record.notes, textPri, textSec)
                        }
                        Divider(color = border.copy(alpha = 0.4f))
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            val footerText = when {
                                record.cost != null -> "Cost: Rp ${"%.0f".format(record.cost)}"
                                !record.nextVisitDate.isNullOrBlank() -> "Next visit: ${record.nextVisitDate}"
                                else -> "Tap to view full details"
                            }
                            Text(footerText, fontSize = 11.sp, color = textSec)
                            Text(
                                "View Report →",
                                fontSize = 11.sp, fontWeight = FontWeight.Bold, color = MrPrimary,
                                modifier = Modifier.clickable { onClick() }
                            )
                        }
                    }
                }
            }
        }
    }
}

// ── Badge ─────────────────────────────────────────────────────────────────────
@Composable
private fun MrBadge(label: String, textColor: Color, bgColor: Color, borderColor: Color) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .background(bgColor)
            .border(1.dp, borderColor, RoundedCornerShape(4.dp))
            .padding(horizontal = 7.dp, vertical = 2.dp)
    ) {
        Text(label, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = textColor)
    }
}

// ── Detail row ────────────────────────────────────────────────────────────────
@Composable
private fun MrDetailRow(
    icon: ImageVector, iconTint: Color,
    label: String, value: String,
    textPri: Color, textSec: Color
) {
    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Box(
            modifier = Modifier
                .size(40.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(iconTint.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, null, tint = iconTint, modifier = Modifier.size(20.dp))
        }
        Column {
            Text(label.uppercase(), fontSize = 10.sp, fontWeight = FontWeight.Bold, color = textSec, letterSpacing = 0.7.sp)
            Spacer(Modifier.height(2.dp))
            Text(value, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textPri, lineHeight = 18.sp)
        }
    }
}

// ── Empty state ───────────────────────────────────────────────────────────────
@Composable
private fun MrEmptyState(textPri: Color, textSec: Color) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(40.dp)) {
            Box(
                modifier = Modifier
                    .size(80.dp)
                    .clip(CircleShape)
                    .background(MrPrimary.copy(alpha = 0.1f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.FolderShared, null, tint = MrPrimary, modifier = Modifier.size(40.dp))
            }
            Spacer(Modifier.height(16.dp))
            Text("No records yet", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPri)
            Spacer(Modifier.height(8.dp))
            Text(
                "Your pet's health history will appear here\nafter your first vet visit.",
                fontSize = 13.sp, color = textSec,
                textAlign = TextAlign.Center, lineHeight = 20.sp
            )
        }
    }
}

// ── Bottom nav ────────────────────────────────────────────────────────────────
@Composable
private fun MrBottomNav(
    modifier: Modifier = Modifier,
    isDark: Boolean,
    surface: Color,
    border: Color,
    onHome: () -> Unit,
    onBookings: () -> Unit,
    onProfile: () -> Unit
) {
    Surface(modifier = modifier.fillMaxWidth(), color = surface.copy(alpha = 0.97f), shadowElevation = 8.dp) {
        Column {
            Divider(color = border, thickness = 1.dp)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
                    .padding(top = 8.dp, bottom = 20.dp),
                horizontalArrangement = Arrangement.SpaceAround,
                verticalAlignment = Alignment.Bottom
            ) {
                MrNavBtn(Icons.Filled.Home, "Home", false, isDark, onHome)
                MrNavBtn(Icons.Filled.FolderShared, "Records", true, isDark) {}
                Spacer(Modifier.width(56.dp)) // centre FAB spacer
                MrNavBtn(Icons.Filled.CalendarMonth, "Bookings", false, isDark, onBookings)
                MrNavBtn(Icons.Filled.Person, "Profile", false, isDark, onProfile)
            }
        }
    }
}

@Composable
private fun MrNavBtn(icon: ImageVector, label: String, isActive: Boolean, isDark: Boolean, onClick: () -> Unit) {
    Column(
        modifier = Modifier.width(52.dp).clickable(onClick = onClick),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        if (isActive) {
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(12.dp))
                    .background(MrPrimary.copy(alpha = 0.12f))
                    .padding(horizontal = 10.dp, vertical = 4.dp),
                contentAlignment = Alignment.Center
            ) { Icon(icon, null, tint = MrPrimary, modifier = Modifier.size(22.dp)) }
        } else {
            Icon(icon, null, tint = if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8), modifier = Modifier.size(24.dp))
        }
        Text(
            label, fontSize = 10.sp,
            fontWeight = if (isActive) FontWeight.Bold else FontWeight.Medium,
            color = if (isActive) (if (isDark) Color.White else Color(0xFF0F172A))
                    else if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8)
        )
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
private fun mrFormatDate(s: String?): String {
    if (s.isNullOrBlank()) return "–"
    return try {
        LocalDate.parse(s.take(10)).format(DateTimeFormatter.ofPattern("MMM dd"))
    } catch (e: Exception) { s.take(10) }
}

private fun mrFormatTime(s: String?): String {
    if (s.isNullOrBlank() || s.length < 16) return ""
    return try { s.substring(11, 16) } catch (e: Exception) { "" }
}

private fun mrDiagnosisCategory(d: String): String {
    val l = d.lowercase()
    return when {
        l.contains("vaccin")                     -> "Vaccination"
        l.contains("dental") || l.contains("clean") -> "Dental"
        l.contains("surg")                       -> "Surgery"
        l.contains("lab") || l.contains("blood") -> "Lab Result"
        l.contains("emergency")                  -> "Emergency"
        l.contains("routine") || l.contains("wellness") -> "Routine"
        else                                     -> "Checkup"
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  MEDICAL RECORD DETAIL SCREEN
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun MedicalRecordDetailScreen(
    recordId: Int,
    onNavigateBack: () -> Unit,
    viewModel: MedicalRecordsViewModel = hiltViewModel()
) {
    val state  by viewModel.detailState.collectAsState()
    val isDark = false

    val bg      = if (isDark) MrBgDark      else MrBgLight
    val surface = if (isDark) MrSurfaceDark else MrSurfaceLight
    val border  = if (isDark) MrBorderDark  else MrBorderLight
    val textPri = if (isDark) Color.White   else Color(0xFF0F172A)
    val textSec = if (isDark) MrSecDark     else Color(0xFF64748B)

    LaunchedEffect(recordId) { viewModel.loadRecord(recordId) }

    Box(Modifier.fillMaxSize().background(bg)) {
        when {
            state.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = MrPrimary)
            }
            state.record != null -> {
                val rec = state.record!!
                Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(bottom = 32.dp)) {

                    // ── Hero gradient header ───────────────────────────────
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(
                                Brush.verticalGradient(
                                    listOf(MrPrimary.copy(alpha = 0.22f), bg)
                                )
                            )
                            .padding(horizontal = 16.dp)
                            .padding(top = 44.dp, bottom = 28.dp)
                    ) {
                        Column {
                            Box(
                                modifier = Modifier
                                    .size(40.dp)
                                    .clip(CircleShape)
                                    .background(surface.copy(alpha = 0.75f))
                                    .clickable { onNavigateBack() },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(Icons.Filled.ArrowBack, null, tint = textPri, modifier = Modifier.size(20.dp))
                            }
                            Spacer(Modifier.height(20.dp))
                            MrBadge(
                                mrDiagnosisCategory(rec.diagnosis ?: "Checkup").uppercase(),
                                MrPrimary, MrPrimary.copy(alpha = 0.15f), MrPrimary.copy(alpha = 0.3f)
                            )
                            Spacer(Modifier.height(8.dp))
                            Text(rec.diagnosis ?: "Medical Record", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = textPri, lineHeight = 30.sp)
                            Spacer(Modifier.height(8.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                    Icon(Icons.Filled.CalendarToday, null, tint = textSec, modifier = Modifier.size(13.dp))
                                    Text(mrFormatDate(rec.createdAt), fontSize = 12.sp, color = textSec)
                                }
                                if (rec.booking?.doctor?.name != null) {
                                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                        Icon(Icons.Filled.Person, null, tint = textSec, modifier = Modifier.size(13.dp))
                                        Text(rec.booking.doctor.name, fontSize = 12.sp, color = textSec, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    }
                                }
                            }
                        }
                    }
                    // ── Doctor info ────────────────────────────────────────
                    val doctor = rec.booking?.doctor
                    if (doctor != null) {
                        Surface(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                            color = surface, shape = RoundedCornerShape(16.dp), shadowElevation = 2.dp
                        ) {
                            Row(
                                modifier = Modifier
                                    .border(1.dp, border, RoundedCornerShape(16.dp))
                                    .padding(14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(12.dp)
                            ) {                                Box(
                                    modifier = Modifier
                                        .size(52.dp)
                                        .clip(CircleShape)
                                        .background(if (isDark) Color.White else Color(0xFFE8F5E9)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    MrDocPhoto(url = doctor.photo, size = 52.dp)
                                }
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        "Treated by",
                                        fontSize = 10.sp, fontWeight = FontWeight.Bold,
                                        color = textSec, letterSpacing = 0.5.sp
                                    )
                                    Text(doctor.name ?: "–", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPri)
                                    Text(doctor.specialization ?: "Veterinarian", fontSize = 12.sp, color = MrPrimary, fontWeight = FontWeight.Medium)
                                }
                            }
                        }
                        Spacer(Modifier.height(16.dp))
                    }

                    // ── Pet info ───────────────────────────────────────────
                    val pet = rec.booking?.pet
                    if (pet != null) {
                        Surface(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                            color = surface, shape = RoundedCornerShape(16.dp), shadowElevation = 2.dp
                        ) {
                            Row(
                                modifier = Modifier
                                    .border(1.dp, border, RoundedCornerShape(16.dp))
                                    .padding(16.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                Box(
                                    modifier = Modifier.size(48.dp).clip(CircleShape).background(MrPrimary.copy(alpha = 0.15f)),
                                    contentAlignment = Alignment.Center
                                ) { Icon(Icons.Filled.Pets, null, tint = MrPrimary, modifier = Modifier.size(24.dp)) }
                                Column {
                                    Text(pet.name ?: "Pet", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPri)
                                    Text(
                                        buildString {
                                            append(pet.species ?: "")
                                            if (!pet.breed.isNullOrBlank()) append(" • ${pet.breed}")
                                            if (pet.age != null) append(" • ${pet.age} yrs")
                                            if (pet.weight != null) append(" • ${pet.weight} kg")
                                        },
                                        fontSize = 12.sp, color = textSec
                                    )
                                }
                            }
                        }
                        Spacer(Modifier.height(16.dp))
                    }

                    // ── Medical details ────────────────────────────────────
                    Surface(
                        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                        color = surface, shape = RoundedCornerShape(16.dp), shadowElevation = 2.dp
                    ) {
                        Column(
                            modifier = Modifier.border(1.dp, border, RoundedCornerShape(16.dp)).padding(20.dp),
                            verticalArrangement = Arrangement.spacedBy(16.dp)
                        ) {
                            Text("Medical Details", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPri)
                            Divider(color = border.copy(alpha = 0.5f))
                            if (!rec.diagnosis.isNullOrBlank())
                                MrDetailRow(Icons.Filled.HealthAndSafety, Color(0xFF10B981), "Diagnosis", rec.diagnosis, textPri, textSec)
                            if (!rec.treatment.isNullOrBlank())
                                MrDetailRow(Icons.Filled.Vaccines, Color(0xFF3B82F6), "Treatment", rec.treatment, textPri, textSec)
                            if (!rec.medicine.isNullOrBlank())
                                MrDetailRow(Icons.Filled.MedicalServices, Color(0xFFF59E0B), "Prescribed Medicine", rec.medicine, textPri, textSec)
                            if (!rec.notes.isNullOrBlank())
                                MrDetailRow(Icons.Filled.Notes, Color(0xFF8B5CF6), "Doctor's Notes", rec.notes, textPri, textSec)
                        }
                    }

                    Spacer(Modifier.height(16.dp))

                    // ── Follow-up / cost ───────────────────────────────────
                    if (rec.nextVisitDate != null || rec.cost != null) {
                        Surface(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                            color = surface, shape = RoundedCornerShape(16.dp), shadowElevation = 2.dp
                        ) {
                            Row(
                                modifier = Modifier
                                    .border(1.dp, border, RoundedCornerShape(16.dp))
                                    .padding(20.dp),
                                horizontalArrangement = Arrangement.SpaceAround,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                if (rec.nextVisitDate != null) {
                                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                        Box(
                                            modifier = Modifier.size(44.dp).clip(CircleShape).background(Color(0xFF3B82F6).copy(alpha = 0.1f)),
                                            contentAlignment = Alignment.Center
                                        ) { Icon(Icons.Filled.EventRepeat, null, tint = Color(0xFF60A5FA), modifier = Modifier.size(22.dp)) }
                                        Spacer(Modifier.height(6.dp))
                                        Text("Next Visit", fontSize = 10.sp, color = textSec, fontWeight = FontWeight.Bold)
                                        Text(rec.nextVisitDate, fontSize = 13.sp, color = textPri, fontWeight = FontWeight.Bold)
                                    }
                                }
                                if (rec.nextVisitDate != null && rec.cost != null) {
                                    Divider(modifier = Modifier.height(56.dp).width(1.dp), color = border)
                                }
                                if (rec.cost != null) {
                                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                        Box(
                                            modifier = Modifier.size(44.dp).clip(CircleShape).background(MrPrimary.copy(alpha = 0.1f)),
                                            contentAlignment = Alignment.Center
                                        ) { Icon(Icons.Filled.Payments, null, tint = MrPrimary, modifier = Modifier.size(22.dp)) }
                                        Spacer(Modifier.height(6.dp))
                                        Text("Total Cost", fontSize = 10.sp, color = textSec, fontWeight = FontWeight.Bold)
                                        Text("Rp ${"%.0f".format(rec.cost)}", fontSize = 13.sp, color = textPri, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }
                }
            }
            else -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
                        Icon(Icons.Filled.ErrorOutline, null, tint = Color(0xFFEF4444), modifier = Modifier.size(48.dp))
                        Spacer(Modifier.height(12.dp))
                        Text(state.error ?: "Record not found", color = textSec, fontSize = 14.sp, textAlign = TextAlign.Center)
                        Spacer(Modifier.height(16.dp))
                        Button(
                            onClick = onNavigateBack,
                            colors = ButtonDefaults.buttonColors(containerColor = MrPrimary, contentColor = MrPrimaryFg)
                        ) { Text("Go Back") }
                    }
                }
            }
        }
    }
}
