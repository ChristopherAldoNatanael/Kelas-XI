# 🐾 PetHeal - Veterinary Booking System (Laravel 11 Backend)

Sistem backend lengkap untuk aplikasi booking klinik hewan dengan Firebase Authentication, FCM Notifications, dan Admin Panel.

## 🚀 Fitur Utama

### 📱 Mobile API (Android)

-   ✅ **Firebase Authentication** (Email/Password & Google Sign-In)
-   ✅ **Sanctum Token** untuk API security
-   ✅ **Pet Management** (CRUD hewan peliharaan)
-   ✅ **Doctor Booking** (Pemesanan jadwal dokter)
-   ✅ **Medical Records** (Riwayat kesehatan)
-   ✅ **FCM Push Notifications** (Reminder vaksin & booking)

### 🖥️ Admin Panel (Web)

-   ✅ **Dashboard** dengan statistik real-time dan grafik
-   ✅ **Booking Management** (Konfirmasi, Complete, Cancel)
-   ✅ **Doctor Management** (CRUD dengan jadwal praktik)
-   ✅ **Medical Records** (Input diagnosis & treatment)
-   ✅ **Responsive Design** dengan Tailwind CSS

## 📋 Prerequisites

-   PHP 8.2+
-   Composer
-   Node.js & NPM
-   SQLite (default) atau MySQL
-   Firebase Account

## 🔧 Installation

### 1. Clone & Install Dependencies

```bash
cd PetHeal_Backend
composer install
npm install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

```bash
# SQLite (default - untuk development)
touch database/database.sqlite

# Run migrations
php artisan migrate:fresh

# Seed sample data
php artisan db:seed
```

### 4. Storage Setup

```bash
php artisan storage:link
```

### 5. Build Assets

```bash
npm run build
```

### 6. Start Server

```bash
php artisan serve
```

Server akan berjalan di: **http://127.0.0.1:8000**

## 🔥 Firebase Setup (WAJIB)

### 1. Buat Firebase Project

1. Buka [Firebase Console](https://console.firebase.google.com)
2. Klik "Add Project" → Beri nama "PetHeal"
3. Aktifkan Google Analytics (opsional)

### 2. Aktifkan Authentication

1. Go to **Build > Authentication**
2. Klik "Get Started"
3. Aktifkan **Email/Password** provider
4. Aktifkan **Google** provider
5. Simpan konfigurasi

### 3. Ambil Web API Key

1. Go to **Project Settings > General**
2. Copy **Web API Key** dan **Project ID**
3. Paste di `.env`:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_API_KEY=your-firebase-api-key
FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
```

**Firebase Configuration (untuk Android/Web):**

```javascript
const firebaseConfig = {
    apiKey: "YOUR_API_KEY",
    authDomain: "your-project.firebaseapp.com",
    projectId: "your-project-id",
    storageBucket: "your-project.firebasestorage.app",
    messagingSenderId: "YOUR_SENDER_ID",
    appId: "YOUR_APP_ID",
    measurementId: "YOUR_MEASUREMENT_ID",
};
```

### 4. Setup FCM (Firebase Cloud Messaging)

1. Go to **Project Settings > Cloud Messaging**
2. Copy **Server Key**
3. Paste di `.env`:

```env
FCM_SERVER_KEY=your-fcm-server-key
```

### 5. Download Service Account (untuk Admin SDK)

1. Go to **Project Settings > Service Accounts**
2. Klik "Generate New Private Key"
3. Simpan JSON file ke: `storage/app/firebase-service-account.json`

## 📱 API Endpoints

### Authentication

| Method | Endpoint                   | Description                    |
| ------ | -------------------------- | ------------------------------ |
| POST   | `/api/auth/firebase-login` | Login dengan Firebase ID Token |
| POST   | `/api/auth/register`       | Register user baru             |
| POST   | `/api/auth/logout`         | Logout (requires auth)         |
| GET    | `/api/auth/profile`        | Get user profile               |
| PUT    | `/api/auth/profile`        | Update profile                 |
| DELETE | `/api/auth/account`        | Delete account                 |

### Pets

| Method | Endpoint               | Description       |
| ------ | ---------------------- | ----------------- |
| GET    | `/api/pets`            | List semua hewan  |
| POST   | `/api/pets`            | Tambah hewan baru |
| GET    | `/api/pets/{id}`       | Detail hewan      |
| PUT    | `/api/pets/{id}`       | Update hewan      |
| DELETE | `/api/pets/{id}`       | Hapus hewan       |
| POST   | `/api/pets/{id}/photo` | Upload foto hewan |

### Doctors

| Method | Endpoint                  | Description         |
| ------ | ------------------------- | ------------------- |
| GET    | `/api/doctors`            | List semua dokter   |
| GET    | `/api/doctors/{id}`       | Detail dokter       |
| GET    | `/api/doctors/{id}/slots` | Slot waktu tersedia |

### Bookings

| Method | Endpoint                    | Description       |
| ------ | --------------------------- | ----------------- |
| GET    | `/api/bookings`             | List booking user |
| GET    | `/api/bookings/upcoming`    | Booking mendatang |
| POST   | `/api/bookings`             | Buat booking baru |
| GET    | `/api/bookings/{id}`        | Detail booking    |
| POST   | `/api/bookings/{id}/cancel` | Cancel booking    |

### Medical Records

| Method | Endpoint                            | Description          |
| ------ | ----------------------------------- | -------------------- |
| GET    | `/api/medical-records`              | List medical records |
| GET    | `/api/medical-records/{id}`         | Detail record        |
| GET    | `/api/pets/{petId}/medical-records` | Records by pet       |

### Device Tokens (FCM)

| Method | Endpoint            | Description           |
| ------ | ------------------- | --------------------- |
| POST   | `/api/device-token` | Register device token |
| DELETE | `/api/device-token` | Remove device token   |

## 🖥️ Admin Panel Routes

| Route                    | Description                |
| ------------------------ | -------------------------- |
| `/admin`                 | Dashboard dengan statistik |
| `/admin/bookings`        | Kelola semua booking       |
| `/admin/bookings/{id}`   | Detail booking             |
| `/admin/doctors`         | Kelola dokter              |
| `/admin/medical-records` | Kelola medical records     |

## 🧪 Testing API dengan cURL

### Register User

```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "firebase_id_token": "your-firebase-id-token",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+6281234567890"
  }'
```

### Login

```bash
curl -X POST http://127.0.0.1:8000/api/auth/firebase-login \
  -H "Content-Type: application/json" \
  -d '{
    "firebase_id_token": "your-firebase-id-token"
  }'
```

### Get Doctors (No Auth Required)

```bash
curl http://127.0.0.1:8000/api/doctors
```

### Get Available Slots

```bash
curl "http://127.0.0.1:8000/api/doctors/1/slots?date=2024-01-20"
```

### Create Booking (Requires Auth)

```bash
curl -X POST http://127.0.0.1:8000/api/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-sanctum-token" \
  -d '{
    "pet_id": 1,
    "doctor_id": 1,
    "booking_date": "2024-01-20",
    "booking_time": "10:00",
    "notes": "Pemeriksaan rutin"
  }'
```

## 📁 Project Structure

```
PetHeal_Backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/          # Mobile API controllers
│   │   │   └── Admin/        # Web admin controllers
│   │   └── Middleware/
│   ├── Models/               # Eloquent models
│   └── Services/             # Business logic
│       ├── FirebaseService.php
│       └── FCMService.php
├── database/
│   ├── migrations/           # All database migrations
│   └── seeders/             # Sample data seeders
├── resources/
│   └── views/
│       ├── layouts/          # Admin layouts
│       └── admin/            # Admin panel views
├── routes/
│   ├── api.php              # API routes
│   └── web.php              # Web routes
└── storage/
    └── app/
        └── firebase-service-account.json  # Firebase credentials
```

## 🔐 Security Features

-   ✅ **Firebase JWT Verification** - Token validation dengan Google public keys
-   ✅ **Sanctum API Tokens** - Secure API authentication
-   ✅ **Role-based Access** - User roles dengan Spatie Permission
-   ✅ **Input Validation** - Form request validation
-   ✅ **HTTPS Ready** - Production-ready security headers

## 🔔 FCM Notification Types

1. **Booking Confirmation** - Saat booking dikonfirmasi admin
2. **Booking Complete** - Saat booking selesai
3. **Vaccination Reminder** - Reminder vaksin (H-1)
4. **Control Reminder** - Reminder kontrol berkala

## 🚀 Deployment (Production)

### 1. Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 2. Database (MySQL)

```env
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=petheal
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

### 3. Queue Worker (untuk FCM)

```bash
php artisan queue:work --daemon
```

### 4. Scheduler (untuk reminder otomatis)

Tambahkan ke crontab:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 📝 License

MIT License - PetHeal Veterinary Booking System

## 👨‍💻 Developer

Built with ❤️ using Laravel 11, Firebase, and Tailwind CSS

---

**Note**: Untuk Android development, lihat folder `PetHeal_Android/` untuk implementasi Kotlin Jetpack Compose.
