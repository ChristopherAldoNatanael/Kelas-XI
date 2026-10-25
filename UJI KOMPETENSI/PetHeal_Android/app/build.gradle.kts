import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("kotlin-kapt")
    id("com.google.dagger.hilt.android")
    id("com.google.gms.google-services")
}

// Load local.properties
val localProperties = Properties()
val localPropertiesFile = rootProject.file("local.properties")
if (localPropertiesFile.exists()) {
    localProperties.load(FileInputStream(localPropertiesFile))
}

android {
    namespace = "com.christopheraldoo.petheal"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.christopheraldoo.petheal"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        // Midtrans Configuration (loaded from local.properties)
        buildConfigField("String", "MIDTRANS_CLIENT_KEY", "\"${localProperties.getProperty("MIDTRANS_CLIENT_KEY", "your-midtrans-client-key-here")}\"")
        buildConfigField("String", "MIDTRANS_MERCHANT_ID", "\"${localProperties.getProperty("MIDTRANS_MERCHANT_ID", "your-midtrans-merchant-id-here")}\"")
        buildConfigField("String", "MIDTRANS_BASE_URL", "\"https://api.sandbox.midtrans.com/v2/\"")
        buildConfigField("String", "MIDTRANS_SNAP_URL", "\"https://app.sandbox.midtrans.com/snap/v2/\"")
        
        // Backend URL
        buildConfigField("String", "BACKEND_BASE_URL", "\"${localProperties.getProperty("BACKEND_BASE_URL", "http://10.0.2.2:8000/api/")}\"")
        
        // Google OAuth Client ID
        buildConfigField("String", "GOOGLE_CLIENT_ID", "\"${localProperties.getProperty("GOOGLE_CLIENT_ID", "your-google-client-id-here")}\"")
    }

    buildTypes {
        debug {
            isMinifyEnabled = false
            // Enable logging in debug builds
            buildConfigField("boolean", "ENABLE_HTTP_LOGGING", "true")
        }
        release {
            isMinifyEnabled = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // Disable logging in release builds
            buildConfigField("boolean", "ENABLE_HTTP_LOGGING", "false")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // java.time dipakai di 13+ file (util/, ViewModel, Screen) padahal
        // minSdk 24. Tanpa desugaring, Android 7.x crash NoClassDefFoundError.
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    composeOptions {
        kotlinCompilerExtensionVersion = "1.5.10"
    }
}

// BackendIntegrationTest memanggil backend nyata. URL bisa diarahkan ke
// lingkungan lain (mis. ngrok) dengan: ./gradlew test -Dbackend.url=https://.../api/
tasks.withType<Test>().configureEach {
    systemProperty(
        "backend.url",
        System.getProperty("backend.url") ?: "http://127.0.0.1:8000/api/"
    )
}

dependencies {
    // Java time desugaring (minSdk 24) — versi kompatibel AGP 8.7
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.0.4")

    // Core Android
    implementation("androidx.core:core-ktx:1.12.0")
    implementation("androidx.appcompat:appcompat:1.6.1")
    implementation("com.google.android.material:material:1.10.0")
    implementation("androidx.activity:activity-compose:1.8.1")

    // Compose
    implementation(platform("androidx.compose:compose-bom:2023.10.01"))
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.ui:ui-graphics")
    implementation("androidx.compose.ui:ui-tooling-preview")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.compose.material:material-icons-extended")
    debugImplementation("androidx.compose.ui:ui-tooling")

    // Navigation
    implementation("androidx.navigation:navigation-compose:2.7.5")

    // Lifecycle
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.6.2")
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.6.2")

    // Hilt
    implementation("com.google.dagger:hilt-android:2.48")
    kapt("com.google.dagger:hilt-android-compiler:2.48")
    implementation("androidx.hilt:hilt-navigation-compose:1.1.0")

    // Retrofit & OkHttp
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")

    // Coroutines
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3")

    // Coil
    implementation("io.coil-kt:coil-compose:2.5.0")

    // DataStore
    implementation("androidx.datastore:datastore-preferences:1.0.0")

    // Firebase - Updated to latest versions for better manifest handling
    implementation(platform("com.google.firebase:firebase-bom:33.8.0"))
    implementation("com.google.firebase:firebase-auth-ktx")
    implementation("com.google.firebase:firebase-messaging-ktx")
    implementation("com.google.android.gms:play-services-auth:21.0.0")

    // Midtrans SDK
    implementation("com.midtrans:uikit:2.0.0-SANDBOX") {
        // SDK Midtrans ikut membawa desugar_jdk_libs 1.1.5 sebagai dependency
        // biasa; begitu core library desugaring aktif, dex 'j$.' lama tercampur
        // dengan ext dex dan D8 menolak merge (mergeExtDexDebug gagal).
        // java.time tetap tersedia dari coreLibraryDesugaring 2.0.4 di atas.
        exclude(group = "com.android.tools", module = "desugar_jdk_libs")
        exclude(group = "com.android.tools", module = "desugar_jdk_libs_configuration")
    }

    // WebView for Snap Payment
    implementation("androidx.webkit:webkit:1.8.0")

    // Testing
    testImplementation("junit:junit:4.13.2")
    androidTestImplementation("androidx.test.ext:junit:1.1.5")
    androidTestImplementation("androidx.test.espresso:espresso-core:3.5.1")
}

kapt {
    correctErrorTypes = true
}
