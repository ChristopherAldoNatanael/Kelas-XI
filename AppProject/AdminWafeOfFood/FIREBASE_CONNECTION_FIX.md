## PANDUAN PERBAIKAN KONEKSI FIREBASE

### 1. UPDATE FIREBASE RULES

Buka Firebase Console: https://console.firebase.google.com/project/waves-of-food-9af5f/database/waves-of-food-9af5f-default-rtdb/rules

Ganti rules dengan kode berikut:

```json
{
  "rules": {
    ".read": "auth != null",
    ".write": "auth != null",

    "test_connection": {
      ".read": true,
      ".write": true
    },

    "users": {
      "$uid": {
        ".read": "auth != null",
        ".write": "auth != null"
      }
    },

    "user_orders": {
      ".read": "auth != null",
      ".write": "auth != null",
      "$uid": {
        ".read": "auth != null",
        ".write": "auth != null",
        "$orderId": {
          ".read": "auth != null",
          ".write": "auth != null"
        }
      }
    },

    "orders": {
      ".read": "auth != null",
      ".write": "auth != null"
    },

    "menus": {
      ".read": "auth != null",
      ".write": "auth != null"
    }
  }
}
```

### 2. VERIFIKASI KONFIGURASI

1. **Database URL**: `https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/`
2. **Project ID**: `waves-of-food-9af5f`
3. **Region**: `asia-southeast1`

### 3. LANGKAH-LANGKAH TESTING

1. Build dan jalankan aplikasi admin
2. Login dengan akun admin
3. Periksa log untuk melihat status koneksi:
   - `🔥 Initializing Firebase...`
   - `✅ Firebase App initialized`
   - `🌐✅ Firebase Database connected successfully`
   - `✅ Test write successful`
   - `✅ Test read successful`

### 4. TROUBLESHOOTING

Jika masih ada masalah:

1. **Cek Internet Connection**
2. **Pastikan Authentication berfungsi**
3. **Periksa Firebase Console untuk error logs**
4. **Coba restart aplikasi**

### 5. FITUR YANG DITAMBAHKAN

1. **FirebaseConfig.kt** - Konfigurasi Firebase yang lebih robust
2. **Enhanced connection testing** - Test write/read actual data
3. **Better error handling** - Error messages yang lebih informatif
4. **Offline persistence** - Data tetap tersedia offline
5. **Debug logging** - Logging detail untuk troubleshooting
