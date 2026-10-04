package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.AuthResponse
import com.christopheraldoo.petheal.data.model.BookingsResponse
import com.christopheraldoo.petheal.data.model.DoctorReviewsResponse
import com.christopheraldoo.petheal.data.model.EmailRegisterRequest
import com.christopheraldoo.petheal.data.model.PetsResponse
import com.christopheraldoo.petheal.data.model.PublicClinicsResponse
import com.christopheraldoo.petheal.data.model.TransactionStatusResponse
import com.christopheraldoo.petheal.data.model.VaccinationsResponse
import com.christopheraldoo.petheal.data.model.WeightHistoryResponse
import com.google.gson.Gson
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * PHASE 7: frozen API contract parsing (pure JVM, Gson).
 * Shapes mirror docs/API_CONTRACT.md + API_DOCUMENTATION.md.
 */
class ContractParsingTest {

    private val gson = Gson()

    @Test
    fun `auth envelope parses token user and clinic`() {
        val json = """
        {"success":true,"message":"Login successful","data":{
          "token":"1|abc",
          "user":{"id":7,"name":"U","email":"u@x.id","role":"user",
            "clinic":{"id":1,"name":"Pusat","slug":"petheal-pusat",
              "logo_url":null,"primary_color":"#18C964"}}}}
        """.trimIndent()
        val res = gson.fromJson(json, AuthResponse::class.java)
        assertTrue(res.success)
        assertEquals("1|abc", res.data?.token)
        assertEquals("user", res.data?.user?.role)
        assertEquals("petheal-pusat", res.data?.user?.clinic?.slug)
        assertEquals("#18C964", res.data?.user?.clinic?.primaryColor)
    }

    @Test
    fun `collection envelope parses top-level pagination`() {
        val json = """
        {"success":true,"data":[{"id":1}],"pagination":
          {"current_page":1,"last_page":5,"per_page":20,"total":100}}
        """.trimIndent()
        val pets = gson.fromJson(json, PetsResponse::class.java)
        assertEquals(1, pets.data?.size)
        assertEquals(5, pets.pagination?.lastPage)
        assertEquals(100, pets.pagination?.total)

        val bookings = gson.fromJson(json, BookingsResponse::class.java)
        assertEquals(1, bookings.pagination?.currentPage)
        assertEquals(20, bookings.pagination?.perPage)
    }

    @Test
    fun `nested histories keep data-pagination shape`() {
        val weight = """
        {"success":true,"data":{"pet_id":3,"pet_name":"Cici",
          "current_weight":4.5,"records":[],"weight_change":null,
          "pagination":{"current_page":1,"last_page":1,"per_page":20,"total":0}}}
        """.trimIndent()
        val w = gson.fromJson(weight, WeightHistoryResponse::class.java)
        assertTrue(w.success)
        assertEquals(3, w.data?.petId)
        assertEquals(1, w.data?.pagination?.currentPage)

        val vacc = """
        {"success":true,"data":{"pet_id":3,"vaccinations":[],"upcoming_due":[],
          "pagination":{"current_page":2,"last_page":2,"per_page":20,"total":21}}}
        """.trimIndent()
        val v = gson.fromJson(vacc, VaccinationsResponse::class.java)
        assertEquals(2, v.data?.pagination?.currentPage)
        assertEquals(21, v.data?.pagination?.total)
    }

    @Test
    fun `reviews carry additive top-level pagination`() {
        val json = """
        {"success":true,"data":{"reviews":[],"average_rating":0,"total_reviews":0},
          "pagination":{"current_page":1,"last_page":1,"per_page":10,"total":0}}
        """.trimIndent()
        val res = gson.fromJson(json, DoctorReviewsResponse::class.java)
        assertTrue(res.data?.reviews?.isEmpty() == true)
        assertEquals(10, res.pagination?.perPage)
    }

    @Test
    fun `raw transaction status has no envelope`() {
        val json = """
        {"transaction_id":"t1","order_id":"BOOKING-1-123","gross_amount":"50000",
          "payment_type":"qris","transaction_time":"2026-01-01 10:00:00",
          "transaction_status":"settlement","fraud_status":"accept",
          "status_code":"200","status_message":"OK"}
        """.trimIndent()
        val res = gson.fromJson(json, TransactionStatusResponse::class.java)
        assertEquals("settlement", res.transactionStatus)
        assertEquals("50000", res.grossAmount)
        assertEquals("200", res.statusCode)
    }

    @Test
    fun `public clinics parse with counts`() {
        val json = """
        {"success":true,"data":[{"id":1,"name":"Pusat","slug":"petheal-pusat",
          "address":"Jl","phone":null,"email":null,"logo_url":null,
          "primary_color":"#18C964","description":null,
          "doctors_count":6,"services_count":11}]}
        """.trimIndent()
        val res = gson.fromJson(json, PublicClinicsResponse::class.java)
        assertTrue(res.success)
        assertEquals("petheal-pusat", res.data?.first()?.slug)
        assertEquals(6, res.data?.first()?.doctorsCount)
    }

    @Test
    fun `register request serializes clinic_slug`() {
        val withSlug = gson.toJson(
            EmailRegisterRequest("N", "n@x.id", "password123", null, null, "android", "happy-paws")
        )
        assertTrue(withSlug.contains("\"clinic_slug\":\"happy-paws\""))

        val withoutSlug = gson.toJson(
            EmailRegisterRequest("N", "n@x.id", "password123")
        )
        // Gson omits nulls: key absent == backend treats as not provided.
        assertTrue(!withoutSlug.contains("clinic_slug"))
    }

    @Test
    fun `error envelopes keep message and errors`() {
        val json = """
        {"success":false,"message":"The given data was invalid.",
          "errors":{"booking_date":["The booking date field must be a date."]}}
        """.trimIndent()
        val parsed = gson.fromJson(json, Map::class.java)
        assertEquals(false, parsed["success"])
        assertTrue((parsed["message"] as String).isNotBlank())
        assertTrue(parsed.containsKey("errors"))
    }

    @Test
    fun `null clinic and null data tolerated`() {
        val json = """{"success":true,"message":"ok","data":null}""".trimIndent()
        val res = gson.fromJson(json, PetsResponse::class.java)
        assertTrue(res.success)
        assertNull(res.data)
    }
}
