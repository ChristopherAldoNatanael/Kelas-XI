package com.christopheraldoo.petheal.ui.components

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.EventBusy
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.data.model.SlotMeta
import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.SlotDayStatus
import com.christopheraldoo.petheal.util.customHoursLabel
import com.christopheraldoo.petheal.util.isDoctorWorkingDay
import com.christopheraldoo.petheal.util.isSlotExpired
import com.christopheraldoo.petheal.util.resolveSlotDayStatus
import java.time.LocalDate
import java.time.format.TextStyle
import java.util.Locale

/**
 * Horizontal 14-day rail used by the booking flow.
 *
 * Dates outside the doctor's practice days stay selectable so the user can
 * understand why there is no schedule, but they are visually muted. The
 * selected date is scrolled into view when the screen opens or changes.
 */
@Composable
fun BookingDateRail(
    selectedDate: LocalDate,
    availableDays: String?,
    onDateSelected: (LocalDate) -> Unit,
    modifier: Modifier = Modifier,
    dayCount: Int = 14,
    // PHASE 4: tanggal cuti dari detail dokter (opsional). Tanggal tetap bisa
    // diketuk agar pengguna melihat alasan cuti, tetapi tampil berbeda.
    leaveDates: Map<LocalDate, String?> = emptyMap(),
    // Tanggal yang dibuka lewat jam khusus meski biasanya tutup.
    extraWorkingDates: Set<LocalDate> = emptySet()
) {
    val today = remember { LocalDate.now() }
    val dates = remember(today, dayCount) {
        (0 until dayCount).map { today.plusDays(it.toLong()) }
    }
    val listState = rememberLazyListState()

    LaunchedEffect(selectedDate, dates) {
        val index = dates.indexOf(selectedDate)
        if (index >= 0) listState.animateScrollToItem(index)
    }

    LazyRow(
        state = listState,
        modifier = modifier.fillMaxWidth(),
        contentPadding = PaddingValues(horizontal = 2.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        items(dates, key = { it.toString() }) { date ->
            val selected = date == selectedDate
            val working = isDoctorWorkingDay(availableDays, date) || date in extraWorkingDates
            val isToday = date == today
            val onLeave = leaveDates.containsKey(date)
            val leaveReason = leaveDates[date]

            val background by animateColorAsState(
                targetValue = when {
                    selected -> Primary
                    onLeave -> WarningSoft
                    working -> SurfaceColor
                    else -> SurfaceMuted
                },
                animationSpec = tween(180)
            )
            val borderColor = when {
                selected -> Primary
                onLeave -> WarningText.copy(alpha = 0.30f)
                working -> Outline
                else -> Color.Transparent
            }
            val dayColor = when {
                selected -> OnPrimaryStrong
                onLeave -> WarningText
                working -> TextSecondary
                else -> TextTertiary
            }
            val dateColor = when {
                selected -> OnPrimaryStrong
                onLeave -> WarningText
                working -> OnBackground
                else -> TextTertiary
            }

            Surface(
                modifier = Modifier
                    .width(58.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .selectable(
                        selected = selected,
                        onClick = { onDateSelected(date) },
                        role = Role.RadioButton
                    )
                    .semantics {
                        contentDescription = buildString {
                            append(date.format(java.time.format.DateTimeFormatter.ofPattern("EEEE, d MMMM yyyy", Locale("id", "ID"))))
                            if (onLeave) {
                                append(", dokter cuti")
                                leaveReason?.let { append(": $it") }
                            } else if (!working) {
                                append(", dokter tidak praktik")
                            }
                            if (isToday) append(", hari ini")
                        }
                    },
                shape = RoundedCornerShape(14.dp),
                color = background,
                border = BorderStroke(1.dp, borderColor)
            ) {
                Column(
                    modifier = Modifier
                        .height(68.dp)
                        .padding(vertical = 8.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Text(
                        text = date.dayOfWeek
                            .getDisplayName(TextStyle.SHORT, Locale("id", "ID"))
                            .uppercase(Locale("id", "ID")),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = dayColor
                    )
                    Text(
                        text = date.dayOfMonth.toString(),
                        fontSize = 17.sp,
                        fontWeight = FontWeight.Bold,
                        color = dateColor
                    )
                    Spacer(Modifier.height(3.dp))
                    Box(
                        modifier = Modifier
                            .size(4.dp)
                            .clip(CircleShape)
                            .background(
                                when {
                                    selected -> OnPrimaryStrong.copy(alpha = 0.65f)
                                    onLeave -> WarningDeep
                                    isToday -> Primary
                                    else -> Color.Transparent
                                }
                            )
                    )
                }
            }
        }
    }
}

/**
 * Available-time selector. Slots are grouped by session so the user reads the
 * day the same way a clinic schedule is arranged. All states are explicit:
 * loading, retry, closed day, all hours already passed today, fully booked,
 * and selectable.
 *
 * [date] is the day the slots belong to. It is required to expire hours that
 * have already passed on the current day; when null no expiry is applied.
 *
 * [slotMeta] membawa jadwal efektif dari server (cuti / jam khusus). Null
 * berarti backend lama — picker jatuh kembali ke aturan hari praktik saja.
 */
@Composable
fun BookingTimeSlots(
    slots: List<TimeSlot>,
    selectedTime: String?,
    isLoading: Boolean,
    error: String?,
    isWorkingDay: Boolean,
    onRetry: () -> Unit,
    onSelect: (String) -> Unit,
    modifier: Modifier = Modifier,
    date: LocalDate? = null,
    slotMeta: SlotMeta? = null
) {
    // Satu tempat yang memutuskan keadaan hari: cuti, tidak praktik, kosong,
    // jam lewat, penuh, atau tersedia. Logikanya murni di util agar teruji.
    val status = remember(slots, slotMeta, isWorkingDay, date) {
        resolveSlotDayStatus(
            slots = slots,
            meta = slotMeta,
            isWorkingDay = isWorkingDay,
            date = date
        )
    }
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        when {
            isLoading -> SlotSkeleton()
            error != null -> SlotLoadError(message = error, onRetry = onRetry)
            status == SlotDayStatus.CLOSED_LEAVE -> SlotLeaveNotice(reason = slotMeta?.reason)
            status == SlotDayStatus.NOT_PRACTICING -> SlotEmpty(
                title = "Dokter tidak praktik",
                message = "Pilih tanggal lain yang sesuai hari praktik dokter."
            )
            status == SlotDayStatus.NO_SLOTS -> SlotEmpty(
                title = "Belum ada slot tersedia",
                message = "Jadwal untuk tanggal ini belum dibuka. Pilih tanggal lain."
            )
            status == SlotDayStatus.HOURS_PASSED -> SlotEmpty(
                title = "Jam hari ini sudah lewat",
                message = "Semua jam untuk hari ini sudah terlewati. Pilih besok atau tanggal berikutnya."
            )
            status == SlotDayStatus.FULLY_BOOKED -> SlotEmpty(
                title = "Semua slot sudah penuh",
                message = "Pilih tanggal lain untuk melihat jam yang masih tersedia."
            )
            else -> {
                SlotCustomHoursNotice(meta = slotMeta)
                val groups = groupSlotsBySession(slots)
                groups.forEach { (label, groupSlots) ->
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(
                            text = label.uppercase(Locale("id", "ID")),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 1.sp,
                            color = TextTertiary
                        )
                        SlotGrid(
                            slots = groupSlots,
                            selectedTime = selectedTime,
                            date = date,
                            onSelect = onSelect
                        )
                    }
                }
            }
        }
    }
}

/** Kartu khusus cuti: berbeda dari "slot penuh" agar alasannya terbaca. */
@Composable
private fun SlotLeaveNotice(reason: String?) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = WarningSoft
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(5.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Filled.EventBusy,
                    contentDescription = null,
                    tint = WarningText,
                    modifier = Modifier.size(20.dp)
                )
                Text(
                    text = "Dokter cuti",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = WarningText
                )
            }
            Text(
                text = reason?.takeIf { it.isNotBlank() }
                    ?: "Dokter tidak praktik pada tanggal ini. Pilih tanggal lain.",
                fontSize = 12.sp,
                color = TextSecondary
            )
        }
    }
}

/** Info jam praktik khusus tanggal ini (mis. 10.00–12.00 karena seminar). */
@Composable
private fun SlotCustomHoursNotice(meta: SlotMeta?) {
    val hours = meta.customHoursLabel() ?: return
    val safeMeta = meta ?: return
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        color = WarningSoft.copy(alpha = 0.55f)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 9.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                imageVector = Icons.Filled.Schedule,
                contentDescription = null,
                tint = WarningText,
                modifier = Modifier.size(16.dp)
            )
            Column {
                Text(
                    text = "Jam khusus hari ini · $hours",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = WarningText
                )
                safeMeta.reason?.takeIf { it.isNotBlank() }?.let { reason ->
                    Text(
                        text = reason,
                        fontSize = 10.sp,
                        color = TextSecondary
                    )
                }
            }
        }
    }
}

@Composable
private fun SlotGrid(
    slots: List<TimeSlot>,
    selectedTime: String?,
    date: LocalDate?,
    onSelect: (String) -> Unit
) {
    val columns = 4
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        slots.chunked(columns).forEach { rowSlots ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                rowSlots.forEach { slot ->
                    SlotChip(
                        slot = slot,
                        selected = slot.time == selectedTime,
                        expired = date != null && isSlotExpired(date, slot.time),
                        onClick = { onSelect(slot.time) },
                        modifier = Modifier.weight(1f)
                    )
                }
                repeat(columns - rowSlots.size) {
                    Spacer(Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
private fun SlotChip(
    slot: TimeSlot,
    selected: Boolean,
    expired: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val enabled = slot.available
    val background by animateColorAsState(
        targetValue = when {
            selected -> Primary
            enabled -> SurfaceColor
            else -> SurfaceMuted
        },
        animationSpec = tween(160)
    )
    val borderColor = when {
        selected -> Primary
        enabled -> Outline
        else -> Color.Transparent
    }
    val textColor = when {
        selected -> OnPrimaryStrong
        enabled -> OnBackground
        else -> TextTertiary
    }

    Surface(
        modifier = modifier
            .height(40.dp)
            .clip(RoundedCornerShape(10.dp))
            .selectable(
                selected = selected,
                enabled = enabled,
                onClick = onClick,
                role = Role.RadioButton
            )
            .semantics {
                contentDescription = when {
                    enabled -> "Jam ${slot.time}, tersedia"
                    expired -> "Jam ${slot.time}, sudah lewat"
                    else -> "Jam ${slot.time}, penuh"
                }
            },
        shape = RoundedCornerShape(10.dp),
        color = background,
        border = BorderStroke(1.dp, borderColor)
    ) {
        Box(contentAlignment = Alignment.Center) {
            Text(
                text = slot.time,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = textColor,
                textDecoration = if (enabled) null else TextDecoration.LineThrough
            )
        }
    }
}

@Composable
private fun SlotSkeleton() {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        repeat(2) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                repeat(4) {
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(40.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(SurfaceMuted)
                    )
                }
            }
        }
        Text(
            text = "Memuat jadwal dokter...",
            fontSize = 11.sp,
            color = TextTertiary
        )
    }
}

@Composable
private fun SlotLoadError(message: String, onRetry: () -> Unit) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = ErrorSoft
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Filled.CloudOff,
                    contentDescription = null,
                    tint = ErrorStrong,
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = "Jadwal belum bisa dimuat",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = ErrorStrong
                )
            }
            Text(
                text = message,
                fontSize = 12.sp,
                color = TextSecondary
            )
            TextButton(
                onClick = onRetry,
                contentPadding = PaddingValues(horizontal = 0.dp, vertical = 0.dp)
            ) {
                Icon(
                    imageVector = Icons.Filled.Refresh,
                    contentDescription = null,
                    tint = ErrorStrong,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    text = "Coba lagi",
                    color = ErrorStrong,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 12.sp
                )
            }
        }
    }
}

@Composable
private fun SlotEmpty(title: String, message: String) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = SurfaceMuted
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Icon(
                imageVector = Icons.Filled.EventBusy,
                contentDescription = null,
                tint = TextTertiary,
                modifier = Modifier.size(22.dp)
            )
            Text(
                text = title,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = OnBackground,
                textAlign = TextAlign.Center
            )
            Text(
                text = message,
                fontSize = 12.sp,
                color = TextSecondary,
                textAlign = TextAlign.Center
            )
        }
    }
}

private fun groupSlotsBySession(slots: List<TimeSlot>): List<Pair<String, List<TimeSlot>>> {
    val morning = slots.filter { hourOf(it.time) < 12 }
    val afternoon = slots.filter { hourOf(it.time) in 12..15 }
    val evening = slots.filter { hourOf(it.time) >= 16 }

    return listOf(
        "Pagi" to morning,
        "Siang" to afternoon,
        "Sore" to evening
    ).filter { (_, group) -> group.isNotEmpty() }
}

private fun hourOf(time: String): Int {
    return time.substringBefore(':').toIntOrNull() ?: 0
}
