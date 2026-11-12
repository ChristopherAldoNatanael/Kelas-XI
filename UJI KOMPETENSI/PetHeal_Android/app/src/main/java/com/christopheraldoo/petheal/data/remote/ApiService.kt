package com.christopheraldoo.petheal.data.remote

import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.model.SnapTokenRequest
import com.christopheraldoo.petheal.data.model.SnapTokenResponse
import com.christopheraldoo.petheal.data.model.TransactionStatusResponse
import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.*

/**
 * ✅ OPTIMIZED: All endpoints now use NetworkInterceptor for auth.
 * Removed redundant @Header("Authorization") parameters.
 * This prevents token overwrite conflicts and simplifies repository code.
 */
interface ApiService {

    // ============= AUTH =============
    @POST("auth/login")
    suspend fun login(@Body request: EmailPasswordRequest): Response<AuthResponse>

    // Pendaftaran email = minta kode OTP → verifikasi kode (akun dibuat di
    // langkah verifikasi). Endpoint lama auth/register-direct menjawab 410.
    @POST("auth/register/request-otp")
    suspend fun requestRegisterOtp(@Body request: RegisterOtpRequest): Response<OtpSentResponse>

    @POST("auth/register/verify-otp")
    suspend fun verifyRegisterOtp(@Body request: VerifyRegisterOtpRequest): Response<AuthResponse>

    @POST("auth/register/resend-otp")
    suspend fun resendRegisterOtp(@Body request: ResendOtpRequest): Response<OtpSentResponse>

    @POST("auth/firebase-login")
    suspend fun firebaseLogin(@Body request: LoginRequest): Response<AuthResponse>

    @POST("auth/register")
    suspend fun firebaseRegister(@Body request: FirebaseRegisterRequest): Response<AuthResponse>

    @POST("auth/logout")
    suspend fun logout(): Response<MessageResponse>

    // PHASE 7: declared (backend DELETE /api/auth/account exists); used for
    // account removal flows incl. integration-probe cleanup.
    @DELETE("auth/account")
    suspend fun deleteAccount(): Response<MessageResponse>

    // Verifikasi email sebelum hapus akun / ganti email. Kode dikirim ke email
    // akun (hapus) atau ke alamat baru (ganti email); akun hanya berubah
    // setelah kode diverifikasi.
    @POST("auth/account/delete/request-otp")
    suspend fun requestDeleteAccountOtp(): Response<OtpSentResponse>

    @POST("auth/account/delete/verify-otp")
    suspend fun verifyDeleteAccountOtp(@Body request: VerifyOtpCodeRequest): Response<OtpSentResponse>

    @POST("auth/email/change/request-otp")
    suspend fun requestEmailChangeOtp(@Body request: EmailChangeOtpRequest): Response<OtpSentResponse>

    @POST("auth/email/change/verify-otp")
    suspend fun verifyEmailChangeOtp(@Body request: EmailChangeVerifyOtpRequest): Response<ApiResponse<User>>

    @GET("auth/profile")
    suspend fun getProfile(): Response<ApiResponse<User>>

    @PUT("auth/profile")
    suspend fun updateProfile(@Body params: Map<String, String>): Response<ApiResponse<User>>

    @Multipart
    @POST("auth/profile/photo")
    suspend fun uploadProfilePhoto(@Part photo: MultipartBody.Part): Response<ApiResponse<User>>

    // ============= FORGOT PASSWORD =============
    @POST("auth/forgot-password")
    suspend fun forgotPassword(@Body request: ForgotPasswordRequest): Response<MessageResponse>

    @POST("auth/verify-reset-code")
    suspend fun verifyResetCode(@Body request: VerifyResetCodeRequest): Response<MessageResponse>

    @POST("auth/reset-password")
    suspend fun resetPassword(@Body request: ResetPasswordRequest): Response<MessageResponse>

    @GET("dashboard")
    suspend fun getDashboard(): Response<ApiResponse<DashboardData>>

    // ============= PETS =============
    @GET("pets")
    suspend fun getPets(): Response<PetsResponse>

    @GET("pets/{id}")
    suspend fun getPet(@Path("id") id: Int): Response<PetResponse>

    @POST("pets")
    suspend fun createPet(@Body pet: PetRequest): Response<PetResponse>

    @Multipart
    @POST("pets/with-photo")
    suspend fun createPetWithPhoto(
        @Part("name") name: okhttp3.RequestBody,
        @Part("species") species: okhttp3.RequestBody,
        @Part("breed") breed: okhttp3.RequestBody?,
        @Part("gender") gender: okhttp3.RequestBody?,
        @Part("date_of_birth") dateOfBirth: okhttp3.RequestBody?,
        @Part("age") age: okhttp3.RequestBody?,
        @Part("weight") weight: okhttp3.RequestBody?,
        @Part photo: MultipartBody.Part
    ): Response<PetResponse>

    @PUT("pets/{id}")
    suspend fun updatePet(@Path("id") id: Int, @Body pet: PetRequest): Response<PetResponse>

    @Multipart
    @POST("pets/{id}/photo")
    suspend fun uploadPetPhoto(@Path("id") id: Int, @Part photo: MultipartBody.Part): Response<PhotoUploadResponse>

    @DELETE("pets/{id}")
    suspend fun deletePet(@Path("id") id: Int): Response<MessageResponse>

    // ============= PET HEALTH TRACKING =============
    @GET("pets/{petId}/weight-history")
    suspend fun getWeightHistory(@Path("petId") petId: Int): Response<WeightHistoryResponse>

    @POST("pets/{petId}/weight-records")
    suspend fun addWeightRecord(
        @Path("petId") petId: Int,
        @Body request: WeightRecordRequest
    ): Response<WeightRecordResponse>

    @DELETE("pets/{petId}/weight-records/{recordId}")
    suspend fun deleteWeightRecord(
        @Path("petId") petId: Int,
        @Path("recordId") recordId: Int
    ): Response<MessageResponse>

    @GET("pets/{petId}/vaccinations")
    suspend fun getVaccinations(@Path("petId") petId: Int): Response<VaccinationsResponse>

    @POST("pets/{petId}/vaccinations")
    suspend fun addVaccination(
        @Path("petId") petId: Int,
        @Body request: VaccinationRequest
    ): Response<VaccinationResponse>

    @DELETE("pets/{petId}/vaccinations/{vaccinationId}")
    suspend fun deleteVaccination(
        @Path("petId") petId: Int,
        @Path("vaccinationId") vaccinationId: Int
    ): Response<MessageResponse>

    // ============= PUBLIC CLINICS (multi-tenant picker, no auth) =============
    @GET("public/clinics")
    suspend fun getPublicClinics(): Response<PublicClinicsResponse>

    /**
     * MULTI-CLINIC DISCOVERY: daftar klinik dengan pencarian, filter, dan
     * pengurutan berdasarkan jarak/rating. `lat`/`lng` opsional — tanpa
     * koordinat backend mengembalikan `distance_km = null` (bukan 0).
     */
    @GET("public/clinics")
    suspend fun discoverClinics(
        @Query("search") search: String? = null,
        @Query("category") category: String? = null,
        @Query("service") service: String? = null,
        @Query("sort") sort: String? = null,
        @Query("lat") lat: Double? = null,
        @Query("lng") lng: Double? = null,
        @Query("min_rating") minRating: Double? = null,
        @Query("max_distance_km") maxDistanceKm: Double? = null,
        @Query("open_now") openNow: Boolean? = null,
        @Query("limit") limit: Int? = null
    ): Response<ClinicListResponse>

    @GET("public/clinics/{slug}")
    suspend fun getPublicClinic(@Path("slug") slug: String): Response<ClinicDetailResponse>

    /** Detail klinik + daftar layanan (layar Clinic Detail). */
    @GET("public/clinics/{slug}")
    suspend fun getClinicDetail(
        @Path("slug") slug: String,
        @Query("lat") lat: Double? = null,
        @Query("lng") lng: Double? = null
    ): Response<ClinicDetailFullResponse>

    @GET("public/service-categories")
    suspend fun getServiceCategories(): Response<ServiceCategoriesResponse>

    /**
     * Perbandingan harga antar klinik. Dasar pembanding SELALU kategori
     * standar / `service_id` (bukan nama mirip) — lihat backend
     * PublicClinicController::compareServices.
     */
    @GET("public/services/compare")
    suspend fun compareServices(
        @Query("category_slug") categorySlug: String? = null,
        @Query("service_id") serviceId: Int? = null,
        @Query("name") name: String? = null,
        @Query("lat") lat: Double? = null,
        @Query("lng") lng: Double? = null,
        @Query("sort") sort: String? = null,
        @Query("min_price") minPrice: Double? = null,
        @Query("max_price") maxPrice: Double? = null,
        @Query("max_distance_km") maxDistanceKm: Double? = null,
        @Query("min_rating") minRating: Double? = null,
        @Query("open_now") openNow: Boolean? = null
    ): Response<CompareResponse>

    @GET("public/services/{id}")
    suspend fun getPublicServiceDetail(
        @Path("id") id: Int,
        @Query("lat") lat: Double? = null,
        @Query("lng") lng: Double? = null
    ): Response<ServiceDetailResponse>

    // ============= SERVICES =============
    // PHASE 7: anonymous callers MUST pass clinic_slug (backend 422 otherwise).
    // Authenticated users are scoped by their own clinic server-side.
    // MULTI-CLINIC: `clinic_id` memaksa scope satu klinik. Tanpa parameter
    // ini pasien menerima layanan SEMUA klinik (perilaku lama, sengaja
    // dipertahankan agar perbandingan harga lintas klinik tetap jalan).
    @GET("services")
    suspend fun getServices(
        @Query("clinic_slug") clinicSlug: String? = null,
        @Query("clinic_id") clinicId: Int? = null
    ): Response<ServicesResponse>

    @GET("services/{id}")
    suspend fun getService(
        @Path("id") id: Int,
        @Query("clinic_slug") clinicSlug: String? = null,
        @Query("clinic_id") clinicId: Int? = null
    ): Response<ServiceResponse>

    // ============= DOCTORS =============
    // `clinic_id`/`clinic_slug` memfilter katalog ke satu klinik. Tanpa
    // keduanya pasien menerima dokter seluruh klinik, masing-masing membawa
    // `clinic` ringkas agar UI tidak perlu menebak klinik tujuannya.
    @GET("doctors")
    suspend fun getDoctors(
        @Query("clinic_id") clinicId: Int? = null,
        @Query("clinic_slug") clinicSlug: String? = null
    ): Response<DoctorsResponse>

    // `clinic_id` memaksa scope satu klinik. Dipakai saat dokter berasal dari
    // klinik yang bukan klinik tersimpan (booking lintas klinik).
    @GET("doctors/{id}")
    suspend fun getDoctor(
        @Path("id") id: Int,
        @Query("clinic_id") clinicId: Int? = null
    ): Response<DoctorResponse>

    @GET("doctors/{id}/slots")
    suspend fun getDoctorSlots(@Path("id") id: Int, @Query("date") date: String): Response<SlotsResponse>

    @GET("doctors/{id}/reviews")
    suspend fun getDoctorReviews(@Path("id") id: Int): Response<DoctorReviewsResponse>

    @POST("doctors/{id}/reviews")
    suspend fun submitDoctorReview(
        @Path("id") id: Int,
        @Body request: DoctorReviewRequest
    ): Response<DoctorReviewResponse>

    // ============= BOOKINGS =============
    @GET("bookings")
    suspend fun getBookings(): Response<BookingsResponse>

    @GET("bookings/upcoming")
    suspend fun getUpcomingBookings(): Response<BookingsResponse>

    @GET("bookings/{id}")
    suspend fun getBooking(@Path("id") id: Int): Response<BookingResponse>

    @POST("bookings")
    suspend fun createBooking(@Body booking: BookingRequest): Response<BookingResponse>

    /** Check-in mandiri: hanya berlaku pada hari jadwal & booking terkonfirmasi. */
    @POST("bookings/{id}/check-in")
    suspend fun checkInBooking(@Path("id") id: Int): Response<BookingResponse>

    @POST("bookings/{id}/cancel")
    suspend fun cancelBooking(@Path("id") id: Int, @Body reason: Map<String, String>): Response<BookingResponse>

    @POST("bookings/{id}/reschedule")
    suspend fun rescheduleBooking(@Path("id") id: Int, @Body request: RescheduleRequest): Response<BookingResponse>

    @DELETE("bookings/{id}")
    suspend fun deleteBooking(@Path("id") id: Int): Response<MessageResponse>

    // ============= MEDICAL RECORDS =============
    @GET("medical-records")
    suspend fun getMedicalRecords(): Response<MedicalRecordsResponse>

    @GET("medical-records/{id}")
    suspend fun getMedicalRecord(@Path("id") id: Int): Response<MedicalRecordResponse>

    @GET("bookings/{id}/medical-record")
    suspend fun getBookingMedicalRecord(@Path("id") id: Int): Response<MedicalRecordResponse>

    @POST("medical-records/{id}/pay")
    suspend fun createMedicalRecordExtraPayment(@Path("id") id: Int): Response<SnapTokenResponse>

    @GET("medical-records/{id}/payment-status")
    suspend fun getMedicalRecordPaymentStatus(@Path("id") id: Int): Response<MedicalRecordPaymentStatusResponse>

    @GET("pets/{petId}/medical-records")
    suspend fun getMedicalRecordsByPet(@Path("petId") petId: Int): Response<MedicalRecordsResponse>

    // ============= DEVICE TOKEN =============
    @POST("device-token")
    suspend fun saveDeviceToken(@Body request: DeviceTokenRequest): Response<DeviceTokenResponse>

    @HTTP(method = "DELETE", path = "device-token", hasBody = true)
    suspend fun removeDeviceToken(@Body request: DeviceTokenRequest): Response<DeviceTokenResponse>

    // ============= NOTIFICATIONS =============
    @GET("notifications")
    suspend fun getNotifications(@Query("limit") limit: Int = 50): Response<NotificationsResponse>

    @POST("notifications/{id}/read")
    suspend fun markNotificationRead(@Path("id") id: String): Response<ApiResponse<AppNotification>>

    @POST("notifications/read-all")
    suspend fun markAllNotificationsRead(): Response<MessageResponse>

    @DELETE("notifications")
    suspend fun clearNotifications(): Response<MessageResponse>

    // ============= PAYMENT METHODS (PUBLIC - NO AUTH) =============
    @GET("payment-methods")
    suspend fun getPaymentMethods(): Response<PaymentMethodsResponse>

    // ============= MIDTRANS PAYMENT =============
    @GET("payment/preflight")
    suspend fun checkPaymentPreflight(): Response<PaymentPreflightResponse>

    @POST("payment/snap-token")
    suspend fun createSnapToken(@Body request: SnapTokenRequest): Response<SnapTokenResponse>

    @GET("payment/transaction-status/{orderId}")
    suspend fun getTransactionStatus(@Path("orderId") orderId: String): Response<TransactionStatusResponse>

    @POST("payment/sync-status")
    suspend fun syncPaymentStatus(@Body request: PaymentSyncRequest): Response<BookingPaymentStatusResponse>

    @POST("payment/remaining/{bookingId}")
    suspend fun createRemainingPaymentSnapToken(@Path("bookingId") bookingId: Int): Response<SnapTokenResponse>

    @GET("payment/booking/{bookingId}")
    suspend fun getBookingPaymentStatus(@Path("bookingId") bookingId: Int): Response<BookingPaymentStatusResponse>
}
