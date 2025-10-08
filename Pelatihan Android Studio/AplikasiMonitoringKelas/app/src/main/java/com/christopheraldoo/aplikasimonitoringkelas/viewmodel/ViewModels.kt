package com.christopheraldoo.aplikasimonitoringkelas.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.asLiveData
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomStatus
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomStatusRepository
import com.christopheraldoo.aplikasimonitoringkelas.data.Schedule
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleRepository
import com.christopheraldoo.aplikasimonitoringkelas.data.User
import com.christopheraldoo.aplikasimonitoringkelas.data.UserRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class UserViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = UserRepository(application)

    val allUsers = repository.getAllUsers().asLiveData()

    private val _loginStatus = MutableLiveData<LoginResult>()
    val loginStatus: LiveData<LoginResult> = _loginStatus

    fun login(email: String, password: String) {
        viewModelScope.launch {
            val user = repository.login(email, password)
            if (user != null) {
                _loginStatus.value = LoginResult.Success(user)
            } else {
                _loginStatus.value = LoginResult.Error("Invalid email or password")
            }
        }
    }

    fun insertUser(user: User) {
        viewModelScope.launch {
            repository.insertUser(user)
        }
    }

    fun updateUser(user: User) {
        viewModelScope.launch {
            repository.updateUser(user)
        }
    }

    fun deleteUser(user: User) {
        viewModelScope.launch {
            repository.deleteUser(user)
        }
    }

    fun getUsersByRole(role: String) = repository.getUsersByRole(role).asLiveData()
}

class ScheduleViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = ScheduleRepository(application)

    val allSchedules = repository.getAllSchedules().asLiveData()

    private val _saveStatus = MutableStateFlow<SaveResult<Schedule>>(SaveResult.Initial)
    val saveStatus: StateFlow<SaveResult<Schedule>> = _saveStatus

    fun getSchedulesByDayAndClass(day: String, classRoom: String) = 
        repository.getSchedulesByDayAndClass(day, classRoom).asLiveData()

    fun insertSchedule(schedule: Schedule) {
        viewModelScope.launch {
            try {
                val id = repository.insertSchedule(schedule)
                if (id > 0) {
                    _saveStatus.value = SaveResult.Success(schedule.copy(id = id))
                } else {
                    _saveStatus.value = SaveResult.Error("Failed to insert schedule")
                }
            } catch (e: Exception) {
                _saveStatus.value = SaveResult.Error(e.message ?: "Unknown error")
            }
        }
    }

    fun updateSchedule(schedule: Schedule) {
        viewModelScope.launch {
            try {
                repository.updateSchedule(schedule)
                _saveStatus.value = SaveResult.Success(schedule)
            } catch (e: Exception) {
                _saveStatus.value = SaveResult.Error(e.message ?: "Unknown error")
            }
        }
    }

    fun deleteSchedule(schedule: Schedule) {
        viewModelScope.launch {
            repository.deleteSchedule(schedule)
        }
    }

    fun resetSaveStatus() {
        _saveStatus.value = SaveResult.Initial
    }
}

class ClassroomStatusViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = ClassroomStatusRepository(application)

    val allClassroomStatus = repository.getAllClassroomStatus().asLiveData()

    fun getEmptyClassroomsByDay(day: String) = repository.getEmptyClassroomsByDay(day).asLiveData()

    fun insertClassroomStatus(classroomStatus: ClassroomStatus) {
        viewModelScope.launch {
            repository.insertClassroomStatus(classroomStatus)
        }
    }

    fun updateClassroomStatus(classroomStatus: ClassroomStatus) {
        viewModelScope.launch {
            repository.updateClassroomStatus(classroomStatus)
        }
    }

    fun deleteClassroomStatus(classroomStatus: ClassroomStatus) {
        viewModelScope.launch {
            repository.deleteClassroomStatus(classroomStatus)
        }
    }
}

// Result classes for handling operations
sealed class LoginResult {
    data class Success(val user: User) : LoginResult()
    data class Error(val message: String) : LoginResult()
}

sealed class SaveResult<out T> {
    object Initial : SaveResult<Nothing>()
    data class Success<T>(val data: T) : SaveResult<T>()
    data class Error(val message: String) : SaveResult<Nothing>()
}

// Factory for creating ViewModels with Application context
class ViewModelFactory(private val application: Application) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        return when {
            modelClass.isAssignableFrom(UserViewModel::class.java) -> UserViewModel(application) as T
            modelClass.isAssignableFrom(ScheduleViewModel::class.java) -> ScheduleViewModel(application) as T
            modelClass.isAssignableFrom(ClassroomStatusViewModel::class.java) -> ClassroomStatusViewModel(application) as T
            else -> throw IllegalArgumentException("Unknown ViewModel class")
        }
    }
}