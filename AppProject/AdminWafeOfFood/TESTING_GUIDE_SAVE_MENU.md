# 🧪 TESTING GUIDE - Save Menu & Upload Image Features

## 🚀 Quick Start Testing

### **1. Build & Run Application**
```powershell
# Navigate to project directory
cd "c:\Kelas XI RPL\AppProject\AdminWafeOfFood"

# Clean and build
.\gradlew clean assembleDebug

# Install to device/emulator
.\gradlew installDebug
```

## 📱 Manual Testing Steps

### **Test 1: Add New Menu with Gallery Image**
1. **Login** sebagai admin
2. **Navigate** ke Menu Fragment
3. **Tap** FAB (Floating Action Button) untuk add menu
4. **Fill Form:**
   - Nama: `"Nasi Gudeg Special"`
   - Deskripsi: `"Nasi gudeg dengan ayam, telur, dan krecek, disajikan hangat"`
   - Harga: `28000`
   - Kategori: Pilih `"Main Course"`
5. **Upload Image:**
   - Tap `"📸 BUKA GALERI ANDROID"`
   - Grant permission jika diminta
   - Pilih gambar dari galeri
   - **Observe:** Progress upload 0-100%
6. **Save Menu:**
   - Tap `"💾 SIMPAN MENU"`
   - **Expected:** Loading spinner, kemudian success message
   - **Expected:** Kembali ke Menu Fragment dengan menu baru

### **Test 2: Add Menu with URL Image**
1. **Repeat steps 1-4** dari Test 1
2. **Input URL** instead of gallery:
   - Paste URL gambar: `https://example.com/food.jpg`
   - **Expected:** Image preview muncul
3. **Save Menu** dan verify success

### **Test 3: Form Validation**
1. **Try empty form:**
   - Tap save dengan form kosong
   - **Expected:** Validation error "Nama menu harus diisi"
2. **Try short name:**
   - Input nama: `"Ab"`
   - **Expected:** Error "Nama menu minimal 3 karakter"  
3. **Try short description:**
   - Input deskripsi: `"Short"`
   - **Expected:** Error "Deskripsi menu minimal 10 karakter"
4. **Try invalid price:**
   - Input harga: `0` atau `-1000`
   - **Expected:** Error "Harga harus lebih dari 0"

### **Test 4: Edit Existing Menu**
1. **Dari Menu Fragment**, tap menu item existing
2. **Edit mode** should open dengan data ter-load
3. **Modify data** dan save
4. **Expected:** Menu updated di Firebase dan UI

## 🔍 Debug & Monitoring

### **LogCat Tags to Monitor:**
```
AddMenuViewModel
AddEditMenuActivity  
MenuRepository
Firebase
```

### **Key Log Messages:**
```
✅ "Menu saved successfully"
✅ "Image uploaded successfully" 
✅ "Upload progress: X%"
❌ "Validation error: ..."
❌ "Error saving menu: ..."
```

## 📊 Expected Firebase Data Structure

### **Realtime Database - /menus/**
```json
{
  "-NewGeneratedId": {
    "id": "-NewGeneratedId",
    "name": "Nasi Gudeg Special", 
    "category": "main_course",
    "description": "Nasi gudeg dengan ayam, telur, dan krecek, disajikan hangat",
    "price": 28000,
    "imageURL": "https://firebasestorage.googleapis.com/.../menu_images/uuid.jpg",
    "adminId": "admin_001",
    "isAvailable": true,
    "createdAt": 1672531200000,
    "updatedAt": 1672531200000
  }
}
```

### **Firebase Storage - /menu_images/**
```
/menu_images/
  └── uuid1.jpg
  └── uuid2.jpg  
  └── uuid3.jpg
```

## ✅ Success Criteria

### **Menu Save Success:**
- ✅ Form validation passes
- ✅ Loading UI shown during save
- ✅ Data saved to Firebase Realtime Database
- ✅ Success toast message shown
- ✅ Returns to Menu Fragment
- ✅ New menu appears in list (real-time update)

### **Image Upload Success:**  
- ✅ Permission granted for gallery access
- ✅ Image selected from gallery
- ✅ Upload progress shown (0-100%)
- ✅ Image uploaded to Firebase Storage
- ✅ Download URL generated and saved with menu
- ✅ Image preview shown in form

### **Error Handling Success:**
- ✅ Validation errors shown for invalid input
- ✅ Network errors handled gracefully
- ✅ Permission denied handled properly
- ✅ Firebase errors shown to user
- ✅ UI state restored after errors

## 🐛 Common Issues & Solutions

### **Issue: Permission Denied for Gallery**
```kotlin
// Solution: Check AndroidManifest.xml has correct permissions
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" android:maxSdkVersion="32" />
<uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />
```

### **Issue: Firebase Storage Upload Failed**  
```kotlin  
// Check Firebase Storage rules
service firebase.storage {
  match /b/{bucket}/o {
    match /{allPaths=**} {
      allow read, write: if request.auth != null;
    }
  }
}
```

### **Issue: Menu Not Saving to Database**
```kotlin
// Check Firebase Database rules  
{
  "rules": {
    "menus": {
      ".read": "auth != null",
      ".write": "auth != null"
    }
  }
}
```

## 📱 Testing Devices

### **Recommended Test Scenarios:**
- ✅ **Android 13+** (READ_MEDIA_IMAGES permission)
- ✅ **Android 12 & below** (READ_EXTERNAL_STORAGE permission)  
- ✅ **Different screen sizes** (phone, tablet)
- ✅ **Different network conditions** (WiFi, mobile, offline)
- ✅ **Different image formats** (JPG, PNG, WEBP)

## 🎯 Performance Testing

### **Image Upload Performance:**
- ✅ **Small images** (< 1MB) - Should upload in 2-5 seconds
- ✅ **Medium images** (1-5MB) - Should upload in 5-15 seconds  
- ✅ **Large images** (> 5MB) - May need compression

### **Form Submission Performance:**
- ✅ **Without image** - Should save in < 2 seconds
- ✅ **With image** - Should save in 5-20 seconds (depending on image size)

---

## 🏆 **FITUR YANG SUDAH READY FOR PRODUCTION:**

✅ **Comprehensive Menu Management** dengan validasi lengkap  
✅ **Firebase Integration** untuk database dan storage  
✅ **Image Upload** dari galeri Android dengan progress tracking  
✅ **Form Validation** yang user-friendly  
✅ **Loading States** dan error handling yang robust  
✅ **MVVM Architecture** yang clean dan maintainable  
✅ **Permission Management** yang compatible dengan Android 13+  

Aplikasi siap untuk **production deployment**! 🚀
