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
    @POST("api/auth/login")
    suspend fun login(@Body request: EmailPasswordRequest): Response<AuthResponse>

    @POST("api/auth/register")
    suspend fun register(@Body request: EmailRegisterRequest): Response<AuthResponse>

    @POST("api/auth/firebase-login")
    suspend fun firebaseLogin(@Body request: LoginRequest): Response<AuthResponse>

    @POST("api/auth/firebase-register")
    suspend fun firebaseRegister(@Body request: FirebaseRegisterRequest): Response<AuthResponse>

    @POST("api/auth/logout")
    suspend fun logout(): Response<MessageResponse>

    @GET("api/auth/profile")
    suspend fun getProfile(): Response<ApiResponse<User>>

    @PUT("api/auth/profile")
    suspend fun updateProfile(@Body params: Map<String, String>): Response<ApiResponse<User>>

    @Multipart
    @POST("api/auth/profile/photo")
    suspend fun uploadProfilePhoto(@Part photo: MultipartBody.Part): Response<ApiResponse<User>>

    // ============= FORGOT PASSWORD =============
    @POST("api/auth/forgot-password")
    suspend fun forgotPassword(@Body request: ForgotPasswordRequest): Response<MessageResponse>

    @POST("api/auth/verify-reset-code")
    suspend fun verifyResetCode(@Body request: VerifyResetCodeRequest): Response<MessageResponse>

    @POST("api/auth/reset-password")
    suspend fun resetPassword(@Body request: ResetPasswordRequest): Response<MessageResponse>

    // ============= PETS =============
    @GET("api/pets")
    suspend fun getPets(): Response<PetsResponse>

    @GET("api/pets/{id}")
    suspend fun getPet(@Path("id") id: Int): Response<PetResponse>

    @POST("api/pets")
    suspend fun createPet(@Body pet: PetRequest): Response<PetResponse>

    @Multipart
    @POST("api/pets/with-photo")
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

    @PUT("api/pets/{id}")
    suspend fun updatePet(@Path("id") id: Int, @Body pet: PetRequest): Response<PetResponse>

    @Multipart
    @POST("api/pets/{id}/photo")
    suspend fun uploadPetPhoto(@Path("id") id: Int, @Part photo: MultipartBody.Part): Response<PhotoUploadResponse>

    @DELETE("api/pets/{id}")
    suspend fun deletePet(@Path("id") id: Int): Response<MessageResponse>

    // ============= SERVICES =============
    @GET("api/services")
    suspend fun getServices(): Response<ServicesResponse>

    @GET("api/services/{id}")
    suspend fun getService(@Path("id") id: Int): Response<ServiceResponse>

    // ============= DOCTORS =============
    @GET("api/doctors")
    suspend fun getDoctors(): Response<DoctorsResponse>

    @GET("api/doctors/{id}")
    suspend fun getDoctor(@Path("id") id: Int): Response<DoctorResponse>

    @GET("api/doctors/{id}/slots")
    suspend fun getDoctorSlots(@Path("id") id: Int, @Query("date") date: String): Response<SlotsResponse>

    // ============= BOOKINGS =============
    @GET("api/bookings")
    suspend fun getBookings(): Response<BookingsResponse>

    @GET("api/bookings/upcoming")
    suspend fun getUpcomingBookings(): Response<BookingsResponse>

    @GET("api/bookings/{id}")
    suspend fun getBooking(@Path("id") id: Int): Response<BookingResponse>

    @POST("api/bookings")
    suspend fun createBooking(@Body booking: BookingRequest): Response<BookingResponse>

    @POST("api/bookings/{id}/cancel")
    suspend fun cancelBooking(@Path("id") id: Int, @Body reason: Map<String, String>): Response<BookingResponse>

    @POST("api/bookings/{id}/reschedule")
    suspend fun rescheduleBooking(@Path("id") id: Int, @Body request: RescheduleRequest): Response<BookingResponse>

    @DELETE("api/bookings/{id}")
    suspend fun deleteBooking(@Path("id") id: Int): Response<MessageResponse>

    // ============= MEDICAL RECORDS =============
    @GET("api/medical-records")
    suspend fun getMedicalRecords(): Response<MedicalRecordsResponse>

    @GET("api/medical-records/{id}")
    suspend fun getMedicalRecord(@Path("id") id: Int): Response<MedicalRecordResponse>

    @GET("api/pets/{petId}/medical-records")
    suspend fun getMedicalRecordsByPet(@Path("petId") petId: Int): Response<MedicalRecordsResponse>

    // ============= DEVICE TOKEN =============
    @POST("api/device-token")
    suspend fun saveDeviceToken(@Body request: DeviceTokenRequest): Response<DeviceTokenResponse>

    @DELETE("api/device-token")
    suspend fun removeDeviceToken(@Body request: DeviceTokenRequest): Response<DeviceTokenResponse>

    // ============= PAYMENT METHODS (PUBLIC - NO AUTH) =============
    @GET("api/payment-methods")
    suspend fun getPaymentMethods(): Response<PaymentMethodsResponse>

    // ============= MIDTRANS PAYMENT =============
    @POST("api/payment/snap-token")
    suspend fun createSnapToken(@Body request: SnapTokenRequest): Response<SnapTokenResponse>

    @GET("api/payment/transaction-status/{orderId}")
    suspend fun getTransactionStatus(@Path("orderId") orderId: String): Response<TransactionStatusResponse>

    @POST("api/payment/remaining/{bookingId}")
    suspend fun createRemainingPaymentSnapToken(@Path("bookingId") bookingId: Int): Response<SnapTokenResponse>

    @GET("api/payment/booking/{bookingId}")
    suspend fun getBookingPaymentStatus(@Path("bookingId") bookingId: Int): Response<BookingPaymentStatusResponse>
}
