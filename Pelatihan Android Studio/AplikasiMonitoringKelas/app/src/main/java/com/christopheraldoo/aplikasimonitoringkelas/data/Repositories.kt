package com.christopheraldoo.aplikasimonitoringkelas.data

import android.content.Context
import kotlinx.coroutines.flow.Flow

class UserRepository(context: Context) {
    private val userDao = AppDatabase.getDatabase(context).userDao()
    
    fun getAllUsers(): Flow<List<User>> = userDao.getAllUsers()
    
    fun getUsersByRole(role: String): Flow<List<User>> = userDao.getUsersByRole(role)
    
    suspend fun login(email: String, password: String): User? = userDao.login(email, password)
    
    suspend fun insertUser(user: User): Long = userDao.insertUser(user)
    
    suspend fun updateUser(user: User) = userDao.updateUser(user)
    
    suspend fun deleteUser(user: User) = userDao.deleteUser(user)
}

class ScheduleRepository(context: Context) {
    private val scheduleDao = AppDatabase.getDatabase(context).scheduleDao()
    
    fun getAllSchedules(): Flow<List<Schedule>> = scheduleDao.getAllSchedules()
    
    fun getSchedulesByDayAndClass(day: String, classRoom: String): Flow<List<Schedule>> = 
        scheduleDao.getSchedulesByDayAndClass(day, classRoom)
    
    suspend fun insertSchedule(schedule: Schedule): Long = scheduleDao.insertSchedule(schedule)
    
    suspend fun updateSchedule(schedule: Schedule) = scheduleDao.updateSchedule(schedule)
    
    suspend fun deleteSchedule(schedule: Schedule) = scheduleDao.deleteSchedule(schedule)
}

class ClassroomStatusRepository(context: Context) {
    private val classroomStatusDao = AppDatabase.getDatabase(context).classroomStatusDao()
    
    fun getAllClassroomStatus(): Flow<List<ClassroomStatus>> = classroomStatusDao.getAllClassroomStatus()
    
    fun getEmptyClassroomsByDay(day: String): Flow<List<ClassroomStatus>> = 
        classroomStatusDao.getEmptyClassroomsByDay(day)
    
    suspend fun insertClassroomStatus(classroomStatus: ClassroomStatus): Long = 
        classroomStatusDao.insertClassroomStatus(classroomStatus)
    
    suspend fun updateClassroomStatus(classroomStatus: ClassroomStatus) = 
        classroomStatusDao.updateClassroomStatus(classroomStatus)
    
    suspend fun deleteClassroomStatus(classroomStatus: ClassroomStatus) = 
        classroomStatusDao.deleteClassroomStatus(classroomStatus)
}