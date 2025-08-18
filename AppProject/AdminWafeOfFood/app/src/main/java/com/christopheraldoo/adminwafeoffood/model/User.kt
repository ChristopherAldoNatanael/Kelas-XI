package com.christopheraldoo.adminwafeoffood.model

data class User(
    val uid: String = "",
    val email: String = "",
    val ownerName: String = "",
    val restaurantName: String = "",
    val location: String = "",
    val phoneNumber: String = "",
    val isActive: Boolean = true,
    val createdAt: Long = System.currentTimeMillis()
) {
    // Empty constructor for Firebase
    constructor() : this("", "", "", "", "", "", true, 0L)
}

data class AuthResult(
    val isSuccess: Boolean,
    val user: User? = null,
    val errorMessage: String? = null
)
