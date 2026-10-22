package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.DoctorScheduleException
import com.christopheraldoo.petheal.data.model.SlotMeta
import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.util.SlotDayStatus
import com.christopheraldoo.petheal.util.customHoursLabel
import com.christopheraldoo.petheal.util.firstDoctorWorkingDate
import com.christopheraldoo.petheal.util.resolveSlotDayStatus
import com.christopheraldoo.petheal.util.toCustomHoursDates
import com.christopheraldoo.petheal.util.toLeaveDates
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate
import java.time.LocalDateTime

/**
 * PHASE 4: slot picker harus membedakan cuti, hari tidak praktik, belum ada
 * slot, jam lewat, dan penuh. Semua aturan ini murni (tanpa Android) supaya
 * bisa diuji tanpa emulator.
 */
class SlotDayStateTest {

    private val date = LocalDate.of(2026, 10, 21) // Rabu
    private val now = LocalDateTime.of(2026, 10, 21, 18, 0)

    private val openSlots = listOf(
        TimeSlot("09:00", true),
        TimeSlot("09:30", false)
    )

    private fun leaveMeta(reason: String? = "Cuti tahunan") = SlotMeta(
        date = date.toString(),
        isWorkingDay = false,
        isClosed = true,
        hasException = true,
        reason = reason
    )

    @Test
    fun `leave day wins over weekly schedule and empty slots`() {
        assertEquals(
            SlotDayStatus.CLOSED_LEAVE,
            resolveSlotDayStatus(emptyList(), leaveMeta(), isWorkingDay = true, date = date, now = now)
        )
    }

    @Test
    fun `day outside weekly schedule is not practicing`() {
        assertEquals(
            SlotDayStatus.NOT_PRACTICING,
            resolveSlotDayStatus(emptyList(), null, isWorkingDay = false, date = date, now = now)
        )
    }

    @Test
    fun `working day without slots is no slots state`() {
        assertEquals(
            SlotDayStatus.NO_SLOTS,
            resolveSlotDayStatus(emptyList(), null, isWorkingDay = true, date = date, now = now)
        )
    }

    @Test
    fun `all past slots on today is hours passed not fully booked`() {
        val pastSlots = listOf(TimeSlot("09:00", true), TimeSlot("17:30", true))
        assertEquals(
            SlotDayStatus.HOURS_PASSED,
            resolveSlotDayStatus(pastSlots, null, isWorkingDay = true, date = date, now = now)
        )
    }

    @Test
    fun `all unavailable future slots is fully booked`() {
        val tomorrow = date.plusDays(1)
        val taken = listOf(TimeSlot("09:00", false), TimeSlot("10:00", false))
        assertEquals(
            SlotDayStatus.FULLY_BOOKED,
            resolveSlotDayStatus(taken, null, isWorkingDay = true, date = tomorrow, now = now)
        )
    }

    @Test
    fun `at least one available slot means available`() {
        assertEquals(
            SlotDayStatus.AVAILABLE,
            resolveSlotDayStatus(openSlots, null, isWorkingDay = true, date = date.plusDays(1), now = now)
        )
    }

    @Test
    fun `custom hours can open a day outside the weekly schedule`() {
        val specialDate = date.plusDays(1)
        val custom = SlotMeta(
            date = specialDate.toString(),
            isWorkingDay = true,
            isClosed = false,
            hasException = true,
            startTime = "09:00",
            endTime = "11:00"
        )
        // available_days bilang tidak praktik, tetapi server membuka hari itu.
        assertEquals(
            SlotDayStatus.AVAILABLE,
            resolveSlotDayStatus(openSlots, custom, isWorkingDay = false, date = specialDate, now = now)
        )
    }

    @Test
    fun `custom hours dates exclude closed and invalid rows`() {
        val exceptions = listOf(
            DoctorScheduleException(date = "2026-10-24", isClosed = false, startTime = "09:00", endTime = "11:00"),
            DoctorScheduleException(date = "2026-10-25", isClosed = true),
            DoctorScheduleException(date = "x", isClosed = false)
        )
        assertEquals(setOf(LocalDate.of(2026, 10, 24)), exceptions.toCustomHoursDates())
        assertEquals(emptySet<LocalDate>(), (null as List<DoctorScheduleException>?).toCustomHoursDates())
    }

    @Test
    fun `custom hours day with open slots is available`() {
        val custom = SlotMeta(
            date = date.plusDays(1).toString(),
            isWorkingDay = true,
            isClosed = false,
            hasException = true,
            startTime = "10:00",
            endTime = "12:00"
        )
        assertEquals(
            SlotDayStatus.AVAILABLE,
            resolveSlotDayStatus(openSlots, custom, isWorkingDay = true, date = date.plusDays(1), now = now)
        )
    }

    @Test
    fun `custom hours label only for open exception`() {
        val custom = SlotMeta(
            date = date.toString(),
            isWorkingDay = true,
            isClosed = false,
            hasException = true,
            startTime = "10:00",
            endTime = "12:00",
            reason = "Praktik pagi"
        )
        assertEquals("10:00 - 12:00", custom.customHoursLabel())
        assertNull(custom.copy(isClosed = true).customHoursLabel())
        assertNull(custom.copy(hasException = false).customHoursLabel())
        assertNull(custom.copy(startTime = null).customHoursLabel())
        assertNull((null as SlotMeta?).customHoursLabel())
    }

    @Test
    fun `leave dates only include closed exceptions with valid dates`() {
        val exceptions = listOf(
            DoctorScheduleException(date = "2026-10-22", isClosed = true, reason = "Cuti"),
            DoctorScheduleException(date = "2026-10-23", isClosed = false, startTime = "09:00", endTime = "12:00"),
            DoctorScheduleException(date = "bukan-tanggal", isClosed = true),
            DoctorScheduleException(date = null, isClosed = true)
        )

        val leave = exceptions.toLeaveDates()

        assertEquals(1, leave.size)
        assertEquals("Cuti", leave[LocalDate.of(2026, 10, 22)])
        assertTrue("tanggal custom tidak boleh dianggap cuti", !leave.containsKey(LocalDate.of(2026, 10, 23)))
        assertEquals(emptyMap<LocalDate, String?>(), (null as List<DoctorScheduleException>?).toLeaveDates())
    }

    @Test
    fun `first working date skips known leave days`() {
        val start = LocalDate.of(2026, 10, 21) // Rabu
        val leave = setOf(start, start.plusDays(1))
        val result = firstDoctorWorkingDate(
            availableDays = "monday,tuesday,wednesday,thursday,friday,saturday,sunday",
            from = start,
            horizonDays = 7,
            leaveDates = leave
        )
        assertEquals(start.plusDays(2), result)
    }

    @Test
    fun `first working date still respects weekly schedule`() {
        // Kamis mulai, dokter hanya praktik Senin → Senin berikutnya.
        val start = LocalDate.of(2026, 10, 22)
        val result = firstDoctorWorkingDate(
            availableDays = "monday",
            from = start,
            horizonDays = 7
        )
        assertEquals(LocalDate.of(2026, 10, 26), result)
    }
}
