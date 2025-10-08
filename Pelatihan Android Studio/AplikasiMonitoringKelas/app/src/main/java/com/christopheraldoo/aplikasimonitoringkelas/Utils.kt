package com.christopheraldoo.aplikasimonitoringkelas

// Email validation function
fun String.isValidEmail(): Boolean {
    return android.util.Patterns.EMAIL_ADDRESS.matcher(this).matches()
}
