# 📱 PetHeal API Documentation

## 🚀 Base URL

```
http://127.0.0.1:8000/api
```

## 🔐 Authentication

### 1. Register User (Tanpa Firebase)

**Endpoint:** `POST /auth/register`

**Request:**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "firebase_uid": "firebase_uid_from_client",
    "phone": "+6281234567890"
}
```

**Response:**

```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "firebase_uid": "firebase_uid_from_client",
            "role": "user",
            "phone": "+6281234567890"
        },
        "token": "1|laravel_sanctum_token_here"
    }
}
```

### 2. Firebase Login

**Endpoint:** `POST /auth/firebase-login`

**Request:**

```json
{
    "firebase_uid": "firebase_uid_from_client",
    "email": "john@example.com",
    "name": "John Doe"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "firebase_uid": "firebase_uid_from_client",
            "role": "user"
        },
        "token": "1|laravel_sanctum_token_here"
    }
}
```

### 3. Logout

**Endpoint:** `POST /auth/logout`

**Headers:**

```
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

### 4. Get Profile

**Endpoint:** `GET /auth/profile`

**Headers:**

```
Authorization: Bearer {token}
```

### 5. Update Profile

**Endpoint:** `PUT /auth/profile`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**

```json
{
    "name": "John Updated",
    "phone": "+6289876543210"
}
```

### 6. Delete Account

**Endpoint:** `DELETE /auth/account`

**Headers:**

```
Authorization: Bearer {token}
```

---

## 🐾 Pets API

### List All Pets

**Endpoint:** `GET /pets`

**Headers:**

```
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "name": "Buddy",
            "species": "Dog",
            "breed": "Golden Retriever",
            "age": 3,
            "photo": "pets/buddy.jpg",
            "created_at": "2024-01-15T10:00:00.000000Z"
        }
    ]
}
```

### Create Pet

**Endpoint:** `POST /pets`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**

```json
{
    "name": "Buddy",
    "species": "Dog",
    "breed": "Golden Retriever",
    "age": 3,
    "photo": "base64_encoded_image_or_url"
}
```

### Get Single Pet

**Endpoint:** `GET /pets/{id}`

### Update Pet

**Endpoint:** `PUT /pets/{id}`

### Delete Pet

**Endpoint:** `DELETE /pets/{id}`

### Upload Pet Photo

**Endpoint:** `POST /pets/{id}/photo`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request:**

```
photo: [file]
```

### Get Pet Medical Records

**Endpoint:** `GET /pets/{petId}/medical-records`

---

## 👨‍⚕️ Doctors API

### List All Doctors

**Endpoint:** `GET /doctors`

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Dr. Sarah Smith",
            "specialization": "Veterinary Surgery",
            "available_days": ["Monday", "Wednesday", "Friday"],
            "available_time": "09:00-17:00",
            "created_at": "2024-01-15T10:00:00.000000Z"
        }
    ]
}
```

### Get Doctor Details

**Endpoint:** `GET /doctors/{id}`

### Get Available Slots

**Endpoint:** `GET /doctors/{id}/slots?date=2024-01-20`

**Response:**

```json
{
    "success": true,
    "data": {
        "doctor_id": 1,
        "date": "2024-01-20",
        "available_slots": ["09:00", "10:00", "11:00", "14:00", "15:00"]
    }
}
```

---

## 📅 Bookings API

### List All Bookings

**Endpoint:** `GET /bookings`

**Headers:**

```
Authorization: Bearer {token}
```

### Create Booking

**Endpoint:** `POST /bookings`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**

```json
{
    "pet_id": 1,
    "doctor_id": 1,
    "booking_date": "2024-01-20",
    "booking_time": "10:00",
    "notes": "Annual checkup"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Booking created successfully",
    "data": {
        "id": 1,
        "user_id": 1,
        "pet_id": 1,
        "doctor_id": 1,
        "booking_date": "2024-01-20",
        "booking_time": "10:00",
        "status": "pending",
        "notes": "Annual checkup"
    }
}
```

### Get Single Booking

**Endpoint:** `GET /bookings/{id}`

### Update Booking

**Endpoint:** `PUT /bookings/{id}`

### Cancel Booking

**Endpoint:** `POST /bookings/{id}/cancel`

### Delete Booking

**Endpoint:** `DELETE /bookings/{id}`

### Get Upcoming Bookings

**Endpoint:** `GET /bookings/upcoming`

---

## 📋 Medical Records API

### List All Medical Records

**Endpoint:** `GET /medical-records`

**Headers:**

```
Authorization: Bearer {token}
```

### Create Medical Record

**Endpoint:** `POST /medical-records`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**

```json
{
    "booking_id": 1,
    "diagnosis": "Healthy",
    "treatment": "Vaccination",
    "medicine": "Rabies vaccine",
    "notes": "Next visit in 6 months",
    "next_visit_date": "2024-07-20"
}
```

### Get Single Medical Record

**Endpoint:** `GET /medical-records/{id}`

### Update Medical Record

**Endpoint:** `PUT /medical-records/{id}`

### Delete Medical Record

**Endpoint:** `DELETE /medical-records/{id}`

---

## 📱 Device Token API (FCM)

### Register Device Token

**Endpoint:** `POST /device-token`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**

```json
{
    "token": "fcm_device_token_here"
}
```

### Remove Device Token

**Endpoint:** `DELETE /device-token`

**Headers:**

```
Authorization: Bearer {token}
```

---

## 🔥 Firebase Auth Integration (Android)

### Step 1: Get Firebase ID Token

```kotlin
// In your Android app
val user = FirebaseAuth.getInstance().currentUser
user?.getIdToken(true)?.addOnSuccessListener { result ->
    val idToken = result.token
    // Send this to your backend
}
```

### Step 2: Send to Backend

```kotlin
@POST("/api/auth/firebase-login")
suspend fun firebaseLogin(
    @Body request: FirebaseLoginRequest
): Response<LoginResponse>

data class FirebaseLoginRequest(
    val firebase_uid: String,
    val email: String,
    val name: String
)
```

### Step 3: Store Token

```kotlin
// Save the returned Sanctum token
val token = response.data.token
// Store in DataStore or SharedPreferences
```

### Step 4: Use Token in Requests

```kotlin
@GET("/api/pets")
suspend fun getPets(
    @Header("Authorization") token: String
): Response<PetsResponse>
```

---

## 🎨 Admin Panel

Access admin panel at:

```
http://127.0.0.1:8000/admin/dashboard
```

Features:

-   Dashboard with statistics
-   Booking management
-   Doctor management
-   Medical records

---

## ⚠️ Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 422 Validation Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

### 404 Not Found

```json
{
    "message": "Resource not found."
}
```

---

## 📊 HTTP Status Codes

| Code | Meaning          |
| ---- | ---------------- |
| 200  | OK - Success     |
| 201  | Created          |
| 400  | Bad Request      |
| 401  | Unauthorized     |
| 422  | Validation Error |
| 404  | Not Found        |
| 500  | Server Error     |

---

## 🔗 Testing with cURL

### Register

```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@test.com","password":"password123","firebase_uid":"test123"}'
```

### Login

```bash
curl -X POST http://127.0.0.1:8000/api/auth/firebase-login \
  -H "Content-Type: application/json" \
  -d '{"firebase_uid":"test123","email":"test@test.com","name":"Test"}'
```

### Get Pets (with token)

```bash
curl -X GET http://127.0.0.1:8000/api/pets \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🚀 Next Steps for Android

1. **Setup Firebase Auth** in Android project
2. **Create API Service** using Retrofit
3. **Implement Repository Pattern**
4. **Build UI with Jetpack Compose**
5. **Integrate FCM** for push notifications

Backend sudah siap 100%! 🎉
