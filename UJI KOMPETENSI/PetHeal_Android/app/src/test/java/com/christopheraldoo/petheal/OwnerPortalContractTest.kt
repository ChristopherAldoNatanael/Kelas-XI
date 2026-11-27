package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.MedicalRecordAmendmentsResponse
import com.christopheraldoo.petheal.data.model.MedicalRecordExportResponse
import com.christopheraldoo.petheal.data.model.PrivacyRequest
import com.christopheraldoo.petheal.data.model.PrivacyRequestResponse
import com.christopheraldoo.petheal.data.model.PrivacyRequestsResponse
import com.google.gson.Gson
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * P3.5 — kontrak JSON portal pemilik dan label UI.
 *
 * Model sempit memastikan field internal amendment/PDP tidak pernah dipetakan
 * ke aplikasi, meskipun server keliru mengirimkannya.
 */
class OwnerPortalContractTest {

    private val gson = Gson()

    @Test
    fun amendment_summary_parses_whitelisted_fields_and_ignores_internal_fields() {
        val json = """
            {
              "success": true,
              "data": [
                {
                  "id": 9,
                  "type": "clinical",
                  "status": "applied",
                  "requested_at": "2026-11-25 10:00:00",
                  "reviewed_at": "2026-11-25 11:00:00",
                  "applied_at": "2026-11-25 12:00:00",
                  "applied_version_no": 3,
                  "reason": "HARUS_DIABAIKAN",
                  "before": {"diagnosis": "HARUS_DIABAIKAN"},
                  "after": {"diagnosis": "HARUS_DIABAIKAN"},
                  "signature_path": "HARUS_DIABAIKAN"
                }
              ]
            }
        """.trimIndent()

        val parsed = gson.fromJson(json, MedicalRecordAmendmentsResponse::class.java)
        val item = parsed.data!!.single()

        assertEquals(9, item.id)
        assertEquals("clinical", item.type)
        assertEquals("applied", item.status)
        assertEquals(3, item.appliedVersionNo)
        assertEquals("Sudah diterapkan", item.statusLabel)
        assertEquals("2026-11-25 12:00:00", item.appliedAt)
    }

    @Test
    fun export_ticket_parses_owner_copy_and_download_fields() {
        val json = """
            {
              "success": true,
              "data": {
                "export_id": 21,
                "format": "pdf",
                "purpose": "owner_copy",
                "expires_at": "2026-11-26T21:30:00+07:00",
                "download_url": "https://contoh.test/api/medical-record-exports/abc",
                "token": "abcdefghijklmnopqrstuvwxyz0123456789"
              }
            }
        """.trimIndent()

        val parsed = gson.fromJson(json, MedicalRecordExportResponse::class.java)
        val ticket = parsed.data!!

        assertEquals(21, ticket.exportId)
        assertEquals("pdf", ticket.format)
        assertEquals("owner_copy", ticket.purpose)
        assertTrue(ticket.downloadUrl!!.contains("/medical-record-exports/"))
        assertEquals(36, ticket.token!!.length)
    }

    @Test
    fun privacy_request_parses_status_due_and_rejection_reason() {
        val json = """
            {
              "success": true,
              "data": [
                {
                  "id": 3,
                  "request_type": "access",
                  "status": "received",
                  "description": "Minta salinan rekam medis.",
                  "requested_at": "2026-11-20 08:00:00",
                  "due_at": "2026-12-04 08:00:00",
                  "verified_at": null,
                  "completed_at": null,
                  "rejection_reason": null,
                  "is_overdue": false,
                  "created_at": "2026-11-20 08:00:00",
                  "response_available": true,
                  "response_file_name": "salinan-data-pdp-3.pdf",
                  "response_file_size": 2048,
                  "response_download_count": 2,
                  "handled_by": 99,
                  "response_path": "rahasia/berkas.pdf"
                },
                {
                  "id": 4,
                  "request_type": "erasure",
                  "status": "rejected",
                  "description": "Hapus data.",
                  "requested_at": "2026-11-01 08:00:00",
                  "due_at": "2026-11-15 08:00:00",
                  "rejection_reason": "Masih dalam retensi.",
                  "is_overdue": false
                }
              ]
            }
        """.trimIndent()

        val parsed = gson.fromJson(json, PrivacyRequestsResponse::class.java)
        val access = parsed.data!![0]
        val erasure = parsed.data!![1]

        assertEquals("Salinan data", access.typeLabel)
        assertEquals("Diterima", access.statusLabel)
        assertFalse(access.isOverdue)
        assertNull(access.completedAt)
        assertTrue(access.responseAvailable)
        assertEquals("salinan-data-pdp-3.pdf", access.responseFileName)
        assertEquals(2048, access.responseFileSize)
        assertEquals(2, access.responseDownloadCount)

        assertEquals("Penghapusan data", erasure.typeLabel)
        assertEquals("Ditolak", erasure.statusLabel)
        assertEquals("Masih dalam retensi.", erasure.rejectionReason)
    }

    @Test
    fun create_privacy_request_response_is_self_contained() {
        val json = """
            {
              "success": true,
              "message": "Permintaan data berhasil dicatat.",
              "data": {
                "id": 7,
                "request_type": "correction",
                "status": "received",
                "description": "Perbaiki tanggal lahir hewan.",
                "requested_at": "2026-11-26 09:00:00",
                "due_at": "2026-12-10 09:00:00",
                "is_overdue": false
              }
            }
        """.trimIndent()

        val parsed = gson.fromJson(json, PrivacyRequestResponse::class.java)
        val request = parsed.data!!

        assertEquals(7, request.id)
        assertEquals("Koreksi data", request.typeLabel)
        assertEquals("Diterima", request.statusLabel)
        assertEquals(false, request.isOverdue)
    }

    @Test
    fun unknown_status_never_crashes_and_is_shown_honestly() {
        val amendmentStatus = com.christopheraldoo.petheal.data.model.MedicalRecordAmendmentSummary(
            status = "status_baru"
        ).statusLabel
        val privacyStatus = PrivacyRequest(status = "status_baru").statusLabel

        assertEquals("status_baru", amendmentStatus)
        assertEquals("status_baru", privacyStatus)
    }
}
