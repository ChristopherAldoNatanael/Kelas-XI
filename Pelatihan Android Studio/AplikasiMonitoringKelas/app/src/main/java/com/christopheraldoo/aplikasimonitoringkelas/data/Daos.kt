package com.christopheraldoo.aplikasimonitoringkelas.data

import androidx.room.*
import kotlinx.coroutines.flow.Flow

@Dao
interface UserDao {
    @Query("SELECT * FROM users")
    fun getAllUsers(): Flow<List<User>>
    
    @Query("SELECT * FROM users WHERE email = :email AND password = :password LIMIT 1")
    suspend fun login(email: String, password: String): User?
    
    @Query("SELECT * FROM users WHERE role = :role")
    fun getUsersByRole(role: String): Flow<List<User>>
    
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertUser(user: User): Long
    
    @Update
    suspend fun updateUser(user: User)
    
    @Delete
    suspend fun deleteUser(user: User)
}

@Dao
interface ScheduleDao {
    @Query("SELECT * FROM schedules")
    fun getAllSchedules(): Flow<List<Schedule>>
    
    @Query("SELECT * FROM schedules WHERE day = :day AND classRoom = :classRoom")
    fun getSchedulesByDayAndClass(day: String, classRoom: String): Flow<List<Schedule>>
    
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSchedule(schedule: Schedule): Long
    
    @Update
    suspend fun updateSchedule(schedule: Schedule)
    
    @Delete
    suspend fun deleteSchedule(schedule: Schedule)
}

@Dao
interface ClassroomStatusDao {
    @Query("SELECT * FROM classroom_status")
    fun getAllClassroomStatus(): Flow<List<ClassroomStatus>>
    
    @Query("SELECT * FROM classroom_status WHERE day = :day AND isOccupied = 0")
    fun getEmptyClassroomsByDay(day: String): Flow<List<ClassroomStatus>>
    
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertClassroomStatus(classroomStatus: ClassroomStatus): Long
    
    @Update
    suspend fun updateClassroomStatus(classroomStatus: ClassroomStatus)
    
    @Delete
    suspend fun deleteClassroomStatus(classroomStatus: ClassroomStatus)
}