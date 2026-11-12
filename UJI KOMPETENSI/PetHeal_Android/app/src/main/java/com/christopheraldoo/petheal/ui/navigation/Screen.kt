package com.christopheraldoo.petheal.ui.navigation

sealed class Screen(val route: String) {
    companion object {
        /**
         * Route `bookings/start` tanpa argumen opsional. `navigate()` ke
         * route ber-argumen opsional tidak otomatis mengisi default, jadi
         * pemanggil yang tidak punya konteks klinik memakai ini.
         */
        const val BOOKING_START_CLEAN = "bookings/start"
    }

    object Splash         : Screen("splash")
    object Onboarding     : Screen("onboarding")
    object Login          : Screen("login")
    object Register       : Screen("register")
    // Verifikasi kode OTP pendaftaran email — akun baru dibuat di layar ini,
    // jadi alur daftar email selalu lewat sini (endpoint register-direct pensiun).
    object VerifyOtp      : Screen("verify-otp/{email}") {
        fun createRoute(email: String) = "verify-otp/$email"
    }
    // PHASE 7: finish-setup for accounts born without a clinic binding.
    object CompleteSetup  : Screen("complete-setup")
    object Home           : Screen("home")

    // ── Pets ────────────────────────────────────────────────────────────────
    object Pets    : Screen("pets")
    object AddPet  : Screen("pets/add")               // MUST be before PetDetail
    object EditPet : Screen("pets/edit/{petId}") {
        fun createRoute(petId: Int) = "pets/edit/$petId"
    }
    object PetDetail : Screen("pets/{petId}") {
        fun createRoute(petId: Int) = "pets/$petId"
    }

    // ── Multi-clinic discovery ──────────────────────────────────────────────
    // Pencarian & penjelajahan klinik (akun pasien global, tanpa ikatan klinik).
    object ClinicDiscovery : Screen("clinics/discover")
    object ClinicDetail    : Screen("clinics/{slug}") {
        fun createRoute(slug: String) = "clinics/$slug"
    }
    // Perbandingan harga layanan setara antar klinik. Dasar pembanding:
    // kategori standar atau satu layanan acuan.
    object PriceCompare : Screen("compare?categorySlug={categorySlug}&serviceId={serviceId}") {
        fun createRoute(categorySlug: String? = null, serviceId: Int? = null): String {
            val category = categorySlug?.let { java.net.URLEncoder.encode(it, "UTF-8") } ?: ""
            val service = serviceId?.takeIf { it > 0 } ?: 0
            return "compare?categorySlug=$category&serviceId=$service"
        }
    }

    // ── Doctors ─────────────────────────────────────────────────────────────
    object Doctors      : Screen("doctors")
    // `clinicId` = klinik dokter yang diketuk. MULTI-CLINIC: detail dokter
    // klinik lain harus dibuka dengan scope kliniknya sendiri, bukan klinik
    // tersimpan — tanpa itu server bisa menjawab 404 untuk dokter yang sah.
    object DoctorDetail : Screen("doctors/{doctorId}?autoReview={autoReview}&clinicId={clinicId}") {
        fun createRoute(doctorId: Int, autoReview: Boolean = false, clinicId: Int? = null) =
            "doctors/$doctorId?autoReview=$autoReview&clinicId=${clinicId ?: 0}"
    }

    // ── Bookings ────────────────────────────────────────────────────────────
    object Bookings       : Screen("bookings")
    // Entry transaksi mandiri (hewan + dokter ringkas). Daftarkan sebelum
    // "bookings/{bookingId}" agar tidak tertelan pola argumen.
    // `clinicSlug` = klinik yang dipilih user di layar detail/discovery. Alur
    // booking memakainya sebagai titik awal; langkah 2 tetap bisa diganti.
    object BookingStart   : Screen("bookings/start?clinicSlug={clinicSlug}") {
        fun createRoute(clinicSlug: String? = null) =
            "bookings/start?clinicSlug=${clinicSlug.orEmpty()}"
    }
    object CreateBooking  : Screen("bookings/create/{doctorId}/{petId}") {  // before BookingDetail
        fun createRoute(doctorId: Int, petId: Int) = "bookings/create/$doctorId/$petId"
    }
    object BookingDetail  : Screen("bookings/{bookingId}") {
        fun createRoute(bookingId: Int) = "bookings/$bookingId"
    }

    // ── Medical Records ─────────────────────────────────────────────────────
    object MedicalRecords      : Screen("medical-records")
    object MedicalRecordDetail : Screen("medical-records/{recordId}") {
        fun createRoute(recordId: Int) = "medical-records/$recordId"
    }    // ── Profile ─────────────────────────────────────────────────────────────
    object Profile     : Screen("profile")
    object EditProfile : Screen("profile/edit")

    // ── Notifications & Settings ────────────────────────────────────────────
    object Notifications : Screen("notifications")
    object PrivacySecurity : Screen("privacy-security")
    object HelpSupport : Screen("help-support")
    object About : Screen("about")

    // ── Payment ──────────────────────────────────────────────────────────────
    object Payment : Screen("payment/{bookingId}/{isDp}/{totalAmount}/{isRemaining}?methods={methods}") {
        fun createRoute(
            bookingId: Int,
            isDp: Boolean,
            totalAmount: Double,
            isRemaining: Boolean = false,
            methodsCsv: String = ""
        ) = "payment/$bookingId/$isDp/$totalAmount/$isRemaining?methods=$methodsCsv"
    }

    object MedicalExtraPayment : Screen("payment-medical/{bookingId}/{recordId}/{amount}") {
        fun createRoute(bookingId: Int, recordId: Int, amount: Double) =
            "payment-medical/$bookingId/$recordId/$amount"
    }

    // ── Payment Result ────────────────────────────────────────────────────────
    object PaymentResult : Screen(
        "payment-result/{orderId}/{status}/{message}?bookingId={bookingId}&recordId={recordId}&method={method}"
    ) {
        fun createRoute(
            orderId: String,
            status: String,
            message: String,
            bookingId: Int? = null,
            // >0 = pembayaran BIAYA TAMBAHAN rekam medis: layar hasil
            // memuat record untuk mencetak bukti + rinciannya.
            recordId: Int? = null,
            // payment_type Midtrans ('gopay', 'qris', ...) utk label Metode.
            method: String? = null
        ): String {
            // URLEncoder = form-encoding: spasi menjadi '+', yang TIDAK
            // didekode kembali oleh Navigation menjadi spasi (user melihat
            // "Pembayaran+berhasil!"). Ganti '+' -> '%20' setelah encoding;
            // ini tak ambigu karena '+' literal sudah diubah encoder ke
            // '%2B'. Navigation tetap men-dekode %20 -> spasi satu kali,
            // tanpa double-encoding untuk '%', Unicode, dan tanda baca.
            val encoded = java.net.URLEncoder.encode(message, "UTF-8").replace("+", "%20")
            val base = "payment-result/$orderId/$status/$encoded"
            val query = buildString {
                if (bookingId != null && bookingId > 0) append("?bookingId=$bookingId")
                if (recordId != null && recordId > 0) {
                    append(if (isNotEmpty()) "&" else "?")
                    append("recordId=$recordId")
                }
                // Sanitasi: hanya token [A-Za-z0-9_-] yang boleh ikut URL.
                method?.takeIf { it.isNotBlank() && it.matches(Regex("[A-Za-z0-9_-]{1,24}")) }?.let {
                    append(if (isNotEmpty()) "&" else "?")
                    append("method=$it")
                }
            }
            return base + query
        }
    }
}
