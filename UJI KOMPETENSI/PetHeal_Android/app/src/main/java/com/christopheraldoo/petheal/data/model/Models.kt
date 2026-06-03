package com.christopheraldoo.petheal.data.model

import com.google.gson.annotations.SerializedName

// ============= USER MODELS =============
data class User(
    val id: Int? = null,
    @SerializedName("firebase_uid") val firebaseUid: String? = null,
    val name: String? = null,
    val email: String? = null,
    val role: String? = null,
    val phone: String? = null,
    val photo: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null
)

data class AuthResponse(
    val success: Boolean,
    val message: String? = null,
    val data: AuthData? = null
)

data class AuthData(
    val token: String,
    val user: User
)

data class LoginRequest(
    @SerializedName("id_token") val idToken: String,
    @SerializedName("fcm_token") val fcmToken: String? = null,
    @SerializedName("device_type") val deviceType: String = "android"
)

data class EmailPasswordRequest(
    val email: String,
    val password: String,
    @SerializedName("fcm_token") val fcmToken: String? = null,
    @SerializedName("device_type") val deviceType: String = "android"
)

data class EmailRegisterRequest(
    val name: String,
    val email: String,
    val password: String,
    val phone: String? = null,
    @SerializedName("fcm_token") val fcmToken: String? = null,
    @SerializedName("device_type") val deviceType: String = "android"
)

data class FirebaseRegisterRequest(
    @SerializedName("id_token") val idToken: String,
    val name: String,
    val phone: String? = null,
    @SerializedName("fcm_token") val fcmToken: String? = null,
    @SerializedName("device_type") val deviceType: String = "android"
)

// ============= PET MODELS =============
data class Pet(
    val id: Int? = null,
    @SerializedName("user_id") val userId: Int? = null,
    val name: String? = null,
    val species: String? = null,
    val breed: String? = null,
    val gender: String? = null,
    @SerializedName("date_of_birth") val dateOfBirth: String? = null,
    val age: Int? = null,
    val weight: Double? = null,
    val photo: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null
)

data class PetResponse(
    val success: Boolean,
    val message: String? = null,
    val data: Pet? = null
)

data class PetsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<Pet>? = null
)

data class PhotoUploadResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PhotoUploadData? = null
)

data class PhotoUploadData(
    @SerializedName("photo_url") val photoUrl: String? = null
)

data class PetRequest(
    val name: String,
    val species: String,
    val breed: String? = null,
    val gender: String? = null,
    @SerializedName("date_of_birth") val dateOfBirth: String? = null,
    val age: Int? = null,
    val weight: Double? = null,
    val photo: String? = null
)

// ============= SERVICE MODELS =============
data class Service(
    val id: Int? = null,
    val name: String? = null,
    val description: String? = null,
    val price: Double? = null,
    val category: String? = null,
    val duration: Int? = null,
    @SerializedName("is_active") val isActive: Boolean? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null
)

data class ServicesResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<Service>? = null
)

data class ServiceResponse(
    val success: Boolean,
    val message: String? = null,
    val data: Service? = null
)

// ============= DOCTOR MODELS =============
data class Doctor(
    val id: Int? = null,
    val name: String? = null,
    val specialization: String? = null,
    val photo: String? = null,
    @SerializedName("available_days") val availableDays: String? = null,
    @SerializedName("available_time") val availableTime: String? = null,
    @SerializedName("created_at") val createdAt: String? = null
)

data class DoctorsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<Doctor>? = null
)

data class DoctorResponse(
    val success: Boolean,
    val message: String? = null,
    val data: Doctor? = null
)

data class TimeSlot(
    val time: String,
    val available: Boolean
)

data class SlotsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<TimeSlot>? = null
)

// ============= BOOKING MODELS =============
data class Booking(
    val id: Int? = null,
    @SerializedName("user_id") val userId: Int? = null,
    @SerializedName("pet_id") val petId: Int? = null,
    @SerializedName("doctor_id") val doctorId: Int? = null,
    @SerializedName("service_id") val serviceId: Int? = null,
    @SerializedName("booking_date") val bookingDate: String? = null,
    @SerializedName("booking_time") val bookingTime: String? = null,
    val status: String? = null,
    val notes: String? = null,
    @SerializedName("payment_method_id") val paymentMethodId: Int? = null,
    @SerializedName("payment_type") val paymentType: String? = null,
    @SerializedName("payment_status") val paymentStatus: String? = null,
    @SerializedName("total_amount") val totalAmount: Double? = null,
    @SerializedName("dp_amount") val dpAmount: Double? = null,
    @SerializedName("paid_amount") val paidAmount: Double? = null,
    @SerializedName("remaining_amount") val remainingAmount: Double? = null,
    @SerializedName("cancellation_reason") val cancellationReason: String? = null,
    @SerializedName("confirmed_at") val confirmedAt: String? = null,
    @SerializedName("completed_at") val completedAt: String? = null,
    val pet: Pet? = null,
    val doctor: Doctor? = null,
    val service: Service? = null,
    @SerializedName("created_at") val createdAt: String? = null
)

data class BookingResponse(
    val success: Boolean,
    val message: String? = null,
    val data: Booking? = null
)

data class BookingsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<Booking>? = null
)

data class BookingRequest(
    @SerializedName("pet_id") val petId: Int,
    @SerializedName("doctor_id") val doctorId: Int,
    @SerializedName("service_id") val serviceId: Int,
    @SerializedName("booking_date") val bookingDate: String,
    @SerializedName("booking_time") val bookingTime: String,
    val notes: String? = null,
    @SerializedName("payment_method_id") val paymentMethodId: Int? = null,
    @SerializedName("payment_type") val paymentType: String? = null,
    @SerializedName("total_amount") val totalAmount: Double? = null,
    @SerializedName("dp_amount") val dpAmount: Double? = null
)

data class RescheduleRequest(
    @SerializedName("booking_date") val bookingDate: String,
    @SerializedName("booking_time") val bookingTime: String
)

// ============= MEDICAL RECORD MODELS =============
data class MedicalRecord(
    val id: Int? = null,
    @SerializedName("booking_id") val bookingId: Int? = null,
    val diagnosis: String? = null,
    val treatment: String? = null,
    val medicine: String? = null,
    val notes: String? = null,
    @SerializedName("next_visit_date") val nextVisitDate: String? = null,
    val cost: Double? = null,
    val booking: Booking? = null,
    @SerializedName("created_at") val createdAt: String? = null
)

data class MedicalRecordsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<MedicalRecord>? = null
)

data class MedicalRecordResponse(
    val success: Boolean,
    val message: String? = null,
    val data: MedicalRecord? = null
)

// ============= DEVICE TOKEN MODELS =============
data class DeviceTokenRequest(
    val token: String,
    @SerializedName("device_type") val deviceType: String = "android"
)

data class DeviceTokenResponse(
    val success: Boolean,
    val message: String? = null
)

// ============= NOTIFICATION MODELS =============
data class AppNotification(
    val id: String,
    val title: String,
    val body: String,
    val type: String,           // "booking_status" | "booking_reminder" | "vaccination_reminder" | "general"
    val petName: String? = null,
    val status: String? = null,
    val date: String? = null,
    val timestamp: Long,        // epoch-ms
    val isRead: Boolean = false
)

// ============= API RESPONSE WRAPPER =============
data class ApiResponse<T>(
    val success: Boolean,
    val message: String? = null,
    val data: T? = null
)

data class MessageResponse(
    val success: Boolean,
    val message: String? = null
)

// ============= PAYMENT METHOD MODELS =============
data class PaymentMethod(
    val id: Int? = null,
    val name: String? = null,
    val type: String? = null,
    val description: String? = null,
    val icon: String? = null,
    @SerializedName("is_active") val isActive: Boolean? = null
)

data class PaymentMethodsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<PaymentMethod>? = null
)

// ============= FORGOT PASSWORD MODELS =============
data class ForgotPasswordRequest(
    val email: String
)

data class VerifyResetCodeRequest(
    val email: String,
    val code: String
)

data class ResetPasswordRequest(
    val email: String,
    val code: String,
    val password: String,
    @SerializedName("password_confirmation") val passwordConfirmation: String
)
