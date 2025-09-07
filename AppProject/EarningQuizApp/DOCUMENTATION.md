# EarningQuizApp - Technical Documentation

## Overview

EarningQuizApp is a comprehensive educational Android application designed for students aged 7-17 years. The app gamifies learning by offering quizzes across multiple subjects and rewarding users with points that can be withdrawn or used for additional features.

## Architecture

### MVVM (Model-View-ViewModel) Pattern

The application follows the MVVM architectural pattern:

- **Model**: Data classes in `models/` package representing User, Category, Subject, and Question
- **View**: Activities and Fragments handling UI interactions
- **ViewModel**: Shared preferences and utility classes managing data flow

### Project Structure

```
app/src/main/java/com/christopheraldoo/earningquizapp/
├── activities/           # Activity classes for different screens
├── fragments/            # Fragment classes for main navigation
├── adapters/             # RecyclerView adapters
├── models/               # Data models
├── utils/                # Utility classes
└── MainActivity.kt       # Main entry point with fragment navigation
```

## Core Features

### 1. Authentication System
- **Sign Up**: User registration with validation
- **Login**: User authentication 
- **Session Management**: Persistent login using SharedPreferences

### 2. Home Dashboard
- Welcome message with user's name
- Points display
- Quick access to quiz categories
- Navigation to other features

### 3. Quiz Categories
- 8 subject categories: Math, Science, History, Geography, English, Biology, Physics, Chemistry
- Color-coded category cards
- Grid layout for easy navigation

### 4. Spin Wheel Game
- Daily spin limitation (once per day)
- Random point rewards (10-500 points)
- Animated spinning wheel
- Persistent spin tracking

### 5. Point Withdrawal System
- Simulated withdrawal to various payment methods
- Bank transfer, e-wallet, and mobile money options
- Point balance management

### 6. User Profile Management
- View user statistics
- Edit profile information
- Logout functionality

### 7. Leaderboard
- User rankings based on points
- Mock data for demonstration

## Data Management

### SharedPreferences Manager

The `SharedPrefsManager` object handles all local data persistence:

```kotlin
object SharedPrefsManager {
    // User session management
    fun saveUser(context: Context, user: User)
    fun getCurrentUser(context: Context): User?
    fun isLoggedIn(context: Context): Boolean
    
    // Statistics tracking
    fun incrementQuizzesCompleted(context: Context)
    fun addCorrectAnswers(context: Context, count: Int)
    
    // Spin wheel management
    fun hasSpunToday(context: Context): Boolean
    fun setSpunToday(context: Context, hasSpun: Boolean)
}
```

### User Model

```kotlin
data class User(
    val id: String,
    var fullName: String,
    var email: String,
    var password: String,
    var avatarUrl: String? = null,
    var points: Int = 0,
    var rank: Int = 0,
    var quizzesCompleted: Int = 0,
    var correctAnswers: Int = 0,
    val joinDate: Long = System.currentTimeMillis()
)
```

## 📁 Struktur Project
```
EarningQuizApp/
├── app/
│   ├── src/
│   │   ├── main/
│   │   │   ├── java/com/christopheraldoo/earningquizapp/
│   │   │   │   ├── activities/    # Semua kelas Activity
│   │   │   │   ├── adapters/      # Kelas RecyclerView Adapters
│   │   │   │   ├── fragments/     # Semua kelas Fragment
│   │   │   │   ├── models/        # Kelas data (Data Classes)
│   │   │   │   └── utils/         # Kelas utilitas/helper
│   │   │   ├── res/
│   │   │   │   ├── drawable/      # Aset gambar dan shape drawables
│   │   │   │   ├── layout/        # File layout XML
│   │   │   │   ├── menu/          # File menu XML (misalnya, bottom navigation)
│   │   │   │   └── values/        # colors.xml, strings.xml, themes.xml
│   │   └── AndroidManifest.xml
└── README.md
└── DOCUMENTATION.md
```

## 🔧 Komponen Utama

### Activities
- **SplashActivity**: Layar pembuka. Memeriksa status login pengguna untuk navigasi.
- **LoginActivity**: Menangani proses login pengguna (simulasi).
- **RegisterActivity**: Menangani proses pendaftaran pengguna baru (simulasi).
- **HomeActivity**: Activity utama yang menampung BottomNavigationView dan host untuk fragments.
- **QuizActivity**: Mengelola alur permainan kuis, menampilkan pertanyaan, dan menangani jawaban.
- **ResultActivity**: Menampilkan skor akhir setelah kuis selesai.
- **EditProfileActivity**: Memungkinkan pengguna untuk mengedit nama mereka.

### Models
- **User.kt**: Struktur data untuk informasi pengguna (uid, nama, email, poin, peringkat).
- **Question.kt**: Struktur data untuk pertanyaan kuis (teks pertanyaan, pilihan, jawaban benar, subjek).
- **Subject.kt**: Struktur data untuk kategori mata pelajaran (nama, ikon).

### Utils
- **SharedPrefsManager**: Mengelola penyimpanan data sesi pengguna secara lokal menggunakan SharedPreferences.
- **ValidationUtils**: Menyediakan fungsi untuk memvalidasi input pengguna (email, password, dll.).

## 🎨 Design System

### Color Palette
- **Primary**: `#4CAF50` (Green)
- **Secondary**: `#FFC107` (Amber)
- **Text**: `#212121` (Dark Gray)
- **Background**: `#FFFFFF` (White)

### Typography
- Menggunakan font default sistem Android (Roboto).
- Ukuran teks bervariasi secara semantik (misalnya, 24sp for titles, 16sp for body).

### Components
- **btn_primary.xml**: Drawable kustom untuk tombol utama, memberikan tampilan yang konsisten.
- **item_leaderboard_user.xml**: Layout untuk setiap baris di papan peringkat.
- **item_subject.xml**: Layout untuk setiap item di grid mata pelajaran.

## 🔄 Flow Aplikasi
1.  **SplashActivity** memeriksa `SharedPrefsManager`.
2.  Jika tidak login -> **LoginActivity**.
    - Pengguna bisa ke **RegisterActivity** lalu kembali.
3.  Jika sudah login -> **HomeActivity**.
4.  **HomeActivity** menampilkan **HomeFragment** secara default.
5.  Pengguna dapat menavigasi ke **LeaderboardFragment** atau **ProfileFragment** melalui BottomNavigationView.
6.  Dari **HomeFragment**, memilih subjek akan memulai **QuizActivity**.
7.  Setelah kuis selesai di **QuizActivity**, **ResultActivity** ditampilkan.
8.  Dari **ProfileFragment**, pengguna dapat logout (kembali ke **LoginActivity**) atau masuk ke **EditProfileActivity**.

## 🧪 Testing Notes
- Karena tidak ada backend, semua data bersifat mock/statis.
- Login dan registrasi disimulasikan dan hanya memerlukan input yang valid secara format.
- Poin pengguna tidak disimpan secara persisten antar sesi (selain nama dan email).

## 🚀 Future Improvements
- **Integrasi Backend**: Hubungkan aplikasi ke backend (seperti Firebase) untuk data pengguna, pertanyaan, dan leaderboard yang nyata.
- **Persistence Poin**: Simpan poin pengguna dan progres kuis secara lokal menggunakan database Room.
- **Animasi**: Tambahkan transisi dan animasi yang lebih kaya untuk meningkatkan pengalaman pengguna.
- **Avatar Pengguna**: Implementasikan fungsionalitas untuk mengunggah dan mengubah gambar profil.
- **Variasi Kuis**: Tambahkan tipe pertanyaan yang berbeda (misalnya, isian singkat, benar/salah).
