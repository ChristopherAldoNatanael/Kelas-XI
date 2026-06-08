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

    @POST("auth/register-direct")
    suspend fun register(@Body request: EmailRegisterRequest): Response<AuthResponse>

    @POST("auth/firebase-login")
    suspend fun firebaseLogin(@Body request: LoginRequest): Response<AuthResponse>

    @POST("auth/register")
    suspend fun firebaseRegister(@Body request: FirebaseRegisterRequest): Response<AuthResponse>

    @POST("auth/logout")
    suspend fun logout(): Response<MessageResponse>

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

    // ============= SERVICES =============
    @GET("services")
    suspend fun getServices(): Response<ServicesResponse>

    @GET("services/{id}")
    suspend fun getService(@Path("id") id: Int): Response<ServiceResponse>

    // ============= DOCTORS =============
    @GET("doctors")
    suspend fun getDoctors(): Response<DoctorsResponse>

    @GET("doctors/{id}")
    suspend fun getDoctor(@Path("id") id: Int): Response<DoctorResponse>

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

    @POST("payment/remaining/{bookingId}")
    suspend fun createRemainingPaymentSnapToken(@Path("bookingId") bookingId: Int): Response<SnapTokenResponse>

    @GET("payment/booking/{bookingId}")
    suspend fun getBookingPaymentStatus(@Path("bookingId") bookingId: Int): Response<BookingPaymentStatusResponse>
}
