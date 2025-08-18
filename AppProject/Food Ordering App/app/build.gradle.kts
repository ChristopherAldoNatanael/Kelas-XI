plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    // Tambahkan plugin Firebase
    id("com.google.gms.google-services")
}

android {
    namespace = "com.christopheraldoo.wavesoffood"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.christopheraldoo.wavesoffood"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
    kotlinOptions {
        jvmTarget = "11"
    }
    
    // Mengaktifkan View Binding untuk memudahkan akses view
    buildFeatures {
        viewBinding = true
    }
}

dependencies {

    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.appcompat)
    implementation(libs.material)
    implementation(libs.androidx.activity)
    implementation(libs.androidx.constraintlayout)
    
    // Navigation Component untuk navigasi antar fragment
    implementation("androidx.navigation:navigation-fragment-ktx:2.7.5")
    implementation("androidx.navigation:navigation-ui-ktx:2.7.5")
    
    // ViewModel dan LiveData untuk MVVM pattern
    implementation("androidx.lifecycle:lifecycle-viewmodel-ktx:2.7.0")
    implementation("androidx.lifecycle:lifecycle-livedata-ktx:2.7.0")
    
    // Coroutines untuk operasi asynchronous
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3")
    
    // Lottie untuk animasi yang smooth
    implementation("com.airbnb.android:lottie:6.1.0")
    
    // Firebase Authentication Dependencies
    implementation("com.google.firebase:firebase-auth-ktx:22.3.0")
    // Firebase Firestore (not used for menu)
    implementation("com.google.firebase:firebase-firestore-ktx:24.9.1")
    // Firebase Realtime Database (required for menu)
    implementation("com.google.firebase:firebase-database-ktx:20.3.0")
    
    // Google Sign-In Dependencies
    implementation("com.google.android.gms:play-services-auth:20.7.0")
    
    // Coroutines untuk async operations
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-play-services:1.7.3")

    // Glide untuk memuat gambar menu dari URL ke ImageView
    implementation("com.github.bumptech.glide:glide:4.16.0")

    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
}