# 🍽️ Waves Of Food - Admin Dashboard

<div align="center">
  <img src="https://img.shields.io/badge/Android-3DDC84?style=for-the-badge&logo=android&logoColor=white" alt="Android">
  <img src="https://img.shields.io/badge/Kotlin-0095D5?style=for-the-badge&logo=kotlin&logoColor=white" alt="Kotlin">
  <img src="https://img.shields.io/badge/Material%20Design%203-757575?style=for-the-badge&logo=material-design&logoColor=white" alt="Material Design">
  <img src="https://img.shields.io/badge/Status-In%20Development-orange?style=for-the-badge" alt="Status">
</div>

## 📱 About

**Waves Of Food - Admin Dashboard** adalah aplikasi mobile Android yang dirancang khusus untuk pemilik dan manajer restoran dalam mengelola operasional mereka di platform "Waves Of Food". Aplikasi ini menampilkan desain yang modern, bersih, profesional, dan sangat mudah digunakan.

## ✨ Key Features

### 🏠 Dashboard Utama

- **Real-time Statistics**: Total pendapatan, jumlah pesanan, menu terlaris
- **Quick Actions**: Akses cepat untuk tambah menu dan lihat pesanan
- **Sales Trend**: Grafik penjualan mingguan
- **Recent Orders**: Daftar pesanan terbaru dengan status

### 📋 Manajemen Pesanan

- **Status Filtering**: Filter pesanan berdasarkan status (Masuk, Dikonfirmasi, Dalam Proses, dll.)
- **Order Details**: Informasi lengkap pesanan, pelanggan, dan item
- **Action Buttons**: Terima, tolak, atau tandai pesanan selesai
- **Real-time Updates**: Status pesanan terupdate secara real-time

### 🍕 Manajemen Menu

- **Category Management**: Kelola menu berdasarkan kategori
- **Availability Toggle**: Aktifkan/nonaktifkan ketersediaan menu
- **Image Upload**: Upload foto menu dengan mudah
- **Price Management**: Atur harga menu dengan fleksibel

### 📊 Analytics & Reports

- **Date Range Filters**: Filter laporan berdasarkan periode
- **Visual Charts**: Grafik interaktif untuk analisis penjualan
- **Popular Items**: Daftar menu terlaris
- **Export Options**: Export laporan dalam format CSV/PDF

### ⚙️ Profile & Settings

- **Restaurant Info**: Kelola informasi restoran
- **Operating Hours**: Atur jam operasional
- **Account Settings**: Manajemen akun admin
- **Secure Logout**: Keluar dengan aman

## 🎨 Design System

### Color Palette

```xml
<!-- Primary Colors -->
<color name="primary_green">#22C55E</color>
<color name="primary_green_light">#34D399</color>
<color name="primary_green_dark">#16A34A</color>

<!-- Theme Colors -->
<color name="background_light">#FFFFFF</color>
<color name="background_dark">#1A1A1A</color>
<color name="surface_light">#F9FAFB</color>
<color name="surface_dark">#111827</color>
```

### Typography

- **Primary Font**: Poppins/Inter/Nunito Sans
- **Hierarchy**: H1 (30sp), H2 (24sp), H3 (20sp), Body (16sp), Caption (14sp)
- **Weights**: Bold, SemiBold, Medium, Regular

### Components

- **Buttons**: Fully rounded corners dengan gradient
- **Cards**: Elevated design dengan shadow
- **Input Fields**: Outlined style dengan icons
- **Icons**: Outline minimalist style

## 🏗️ Architecture

### Project Structure

```
app/
├── src/main/
│   ├── java/com/christopheraldoo/adminwafeoffood/
│   │   └── MainActivity.kt
│   └── res/
│       ├── layout/           # Layout files
│       ├── drawable/         # Icons & graphics
│       ├── values/           # Colors, strings, styles
│       ├── menu/             # Navigation menus
│       └── color/            # Color selectors
```

### Layouts Implemented

- ✅ `activity_main.xml` - Main container dengan bottom navigation
- ✅ `fragment_dashboard.xml` - Dashboard utama
- ✅ `fragment_orders.xml` - Manajemen pesanan
- ✅ `fragment_menu.xml` - Manajemen menu
- ✅ `fragment_profile.xml` - Profile & settings
- ✅ `fragment_analytics.xml` - Analytics & reports
- ✅ `activity_add_menu.xml` - Form tambah menu
- ✅ `item_order.xml` - Layout item pesanan
- ✅ `item_menu.xml` - Layout item menu

## 🛠️ Technical Stack

- **Language**: Kotlin
- **Min SDK**: 21 (Android 5.0)
- **Target SDK**: 34
- **UI Framework**: Material Design 3
- **Architecture**: MVVM (planned)
- **Navigation**: Navigation Component (planned)
- **Database**: Room (planned)
- **Image Loading**: Glide (planned)

## 🚀 Getting Started

### Prerequisites

- Android Studio Hedgehog or newer
- Android SDK 21+
- Gradle 8.0+

### Installation

1. Clone repository:

   ```bash
   git clone https://github.com/christopheraldoo/admin-wafe-of-food.git
   ```

2. Buka project di Android Studio

3. Sync Gradle files

4. Build dan run aplikasi

### Current Status

- ✅ **UI Design**: Semua layout telah selesai
- ✅ **Resources**: Colors, strings, dimensions, styles
- ✅ **Icons**: Complete icon set
- ⏳ **Fragments**: In development
- ⏳ **Navigation**: In development
- ⏳ **Data Layer**: Planned

## 📸 Screenshots

_Screenshots akan ditambahkan setelah implementasi fragment selesai_

## 🎯 Roadmap

### Phase 1: Foundation ✅

- [x] Design system setup
- [x] Resource implementation
- [x] Layout development

### Phase 2: Implementation (Current)

- [ ] Fragment classes
- [ ] Navigation setup
- [ ] Data binding
- [ ] Business logic

### Phase 3: Features

- [ ] Real-time updates
- [ ] Image upload
- [ ] Export functionality
- [ ] Push notifications

### Phase 4: Testing & Polish

- [ ] Unit testing
- [ ] UI testing
- [ ] Performance optimization
- [ ] Security implementation

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Developer

**Christopher Aldo**

- Email: christopher.aldo@example.com
- GitHub: [@christopheraldoo](https://github.com/christopheraldoo)

---

<div align="center">
  <p>Made with ❤️ for restaurant owners</p>
  <p><strong>Waves Of Food Admin Dashboard</strong></p>
</div>
