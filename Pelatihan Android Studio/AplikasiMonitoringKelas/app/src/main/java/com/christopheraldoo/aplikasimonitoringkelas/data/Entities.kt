package com.christopheraldoo.aplikasimonitoringkelas.data

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "users")
data class User(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val name: String,
    val email: String,
    val role: String,
    val password: String
)

@Entity(tableName = "schedules")
data class Schedule(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val day: String,
    val classRoom: String,
    val subject: String,
    val teacherCode: String,
    val teacherName: String,
    val periodNumber: String
)

@Entity(tableName = "classroom_status")
data class ClassroomStatus(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val classRoom: String,
    val day: String,
    val startPeriod: String,
    val endPeriod: String,
    val isOccupied: Boolean = false
)