package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.util.allSlotsExpired
import com.christopheraldoo.petheal.util.isSlotExpired
import com.christopheraldoo.petheal.util.parseSlotStartDateTime
import com.christopheraldoo.petheal.util.withExpiredSlotsUnavailable
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate
import java.time.LocalDateTime

/**
 * Aturan slot lewat: pada hari yang sama, jam yang sudah terlewati tidak
 * boleh dipilih lagi — sisanya (booking, hari lain) tidak boleh berubah.
 */
class BookingSlotAvailabilityTest {

    // Rabu, 21 Oktober 2026 pukul 18.00.
    private val today = LocalDate.of(2026, 10, 21)
    private val now = LocalDateTime.of(2026, 10, 21, 18, 0)

    @Test
    fun `slots before now are expired and later slots are not`() {
        assertTrue(isSlotExpired(today, "09:00", now))
        assertTrue(isSlotExpired(today, "17:30", now))
        // Slot yang mulai tepat sekarang sudah tidak bisa dikerjakan.
        assertTrue(isSlotExpired(today, "18:00", now))
        assertFalse(isSlotExpired(today, "18:30", now))
        assertFalse(isSlotExpired(today, "20:00", now))
    }

    @Test
    fun `same clock time on another day is not expired`() {
        val tomorrow = today.plusDays(1)
        assertFalse(isSlotExpired(tomorrow, "09:00", now))
        assertTrue(isSlotExpired(today.minusDays(1), "20:00", now))
    }

    @Test
    fun `expired flag overrides only available past slots`() {
        val slots = listOf(
            TimeSlot("09:00", true),   // lewat → false
            TimeSlot("18:00", true),   // tepat sekarang → false
            TimeSlot("18:30", true),   // masih bisa → true
            TimeSlot("19:00", false)   // sudah dibooking → tetap false
        )

        val result = slots.withExpiredSlotsUnavailable(today, now)

        assertEquals(slots.size, result.size)
        assertEquals(listOf(false, false, true, false), result.map { it.available })
        assertEquals(slots.map { it.time }, result.map { it.time })
    }

    @Test
    fun `all slots expired only when no future slot remains`() {
        assertTrue(
            listOf(TimeSlot("09:00", true), TimeSlot("17:30", false))
                .allSlotsExpired(today, now)
        )
        assertFalse(
            listOf(TimeSlot("09:00", true), TimeSlot("18:30", true))
                .allSlotsExpired(today, now)
        )
        assertFalse(emptyList<TimeSlot>().allSlotsExpired(today, now))
    }

    @Test
    fun `unknown time format is left untouched`() {
        assertFalse(isSlotExpired(today, "soon", now))
        val untouched = listOf(TimeSlot("soon", true))
            .withExpiredSlotsUnavailable(today, now)
            .single()
        assertTrue("slot dengan jam tak dikenal harus tetap tersedia: $untouched", untouched.available)
        assertNull(parseSlotStartDateTime(today, ""))
        assertNull(parseSlotStartDateTime(today, "9-30"))
    }

    @Test
    fun `seconds precision slots parse correctly`() {
        assertEquals(
            LocalDateTime.of(2026, 10, 21, 17, 30),
            parseSlotStartDateTime(today, "17:30:00")
        )
        assertTrue(isSlotExpired(today, "17:30:00", now))
        assertFalse(isSlotExpired(today, "18:30:00", now))
    }
}
