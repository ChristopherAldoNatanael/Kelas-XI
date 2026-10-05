package com.christopheraldoo.petheal.ui.navigation

sealed class Screen(val route: String) {
    object Splash         : Screen("splash")
    object Onboarding     : Screen("onboarding")
    object Login          : Screen("login")
    object Register       : Screen("register")
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

    // ── Doctors ─────────────────────────────────────────────────────────────
    object Doctors      : Screen("doctors")
    object DoctorDetail : Screen("doctors/{doctorId}?autoReview={autoReview}") {
        fun createRoute(doctorId: Int, autoReview: Boolean = false) =
            "doctors/$doctorId?autoReview=$autoReview"
    }

    // ── Bookings ────────────────────────────────────────────────────────────
    object Bookings       : Screen("bookings")
    // Entry transaksi mandiri (hewan + dokter ringkas). Daftarkan sebelum
    // "bookings/{bookingId}" agar tidak tertelan pola argumen.
    object BookingStart   : Screen("bookings/start")
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
    object PaymentResult : Screen("payment-result/{orderId}/{status}/{message}") {
        fun createRoute(orderId: String, status: String, message: String) =
            "payment-result/$orderId/$status/${java.net.URLEncoder.encode(message, "UTF-8")}"
    }
}
