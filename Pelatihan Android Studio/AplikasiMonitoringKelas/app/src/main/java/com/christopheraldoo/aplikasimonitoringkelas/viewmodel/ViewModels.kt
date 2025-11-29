package com.christopheraldoo.aplikasimonitoringkelas.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * ViewModel for User authentication and management
 * All data comes from MySQL database via Laravel API
 */
class UserViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = UserRepository(application)

    private val _loginStatus = MutableLiveData<LoginResult>()
    val loginStatus: LiveData<LoginResult> = _loginStatus

    private val _currentUser = MutableLiveData<UserApi?>()
    val currentUser: LiveData<UserApi?> = _currentUser

    private val _allUsers = MutableLiveData<List<UserApi>>()
    val allUsers: LiveData<List<UserApi>> = _allUsers

    fun login(email: String, password: String) {
        viewModelScope.launch {
            try {
                val response = repository.login(email, password)
                if (response.isSuccessful && response.body()?.success == true) {
                    val user = response.body()?.user
                    if (user != null) {
                        _loginStatus.value = LoginResult.Success(
                            UserApi(
                                id = user.id,
                                nama = user.nama,
                                email = user.email,
                                role = user.role,
                                classId = user.classId,
                                className = null,
                                createdAt = user.createdAt
                            )
                        )
                        _currentUser.value = _loginStatus.value.let { (it as? LoginResult.Success)?.user }
                    } else {
                        _loginStatus.value = LoginResult.Error("Login failed - no user data")
                    }
                } else {
                    _loginStatus.value = LoginResult.Error("Invalid email or password")
                }
            } catch (e: Exception) {
                _loginStatus.value = LoginResult.Error("Network error: ${e.message}")
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            try {
                repository.logout()
                _currentUser.value = null
                _loginStatus.value = LoginResult.Logout
            } catch (e: Exception) {
                // Even if logout fails, clear local user data
                _currentUser.value = null
                _loginStatus.value = LoginResult.Logout
            }
        }
    }

    fun getCurrentUser() {
        viewModelScope.launch {
            try {
                val response = repository.getCurrentUser()
                if (response.isSuccessful && response.body()?.success == true) {
                    _currentUser.value = response.body()?.data
                }
            } catch (e: Exception) {
                // Handle error silently for getCurrentUser
            }
        }
    }

    fun getAllUsers() {
        viewModelScope.launch {
            try {
                val response = repository.getAllUsers()
                if (response.isSuccessful && response.body()?.success == true) {
                    _allUsers.value = response.body()?.data ?: emptyList()
                }
            } catch (e: Exception) {
                _allUsers.value = emptyList()
            }
        }
    }
}

/**
 * ViewModel for Schedule management
 * All data comes from MySQL database via Laravel API
 */
class ScheduleViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = ScheduleRepository(application)

    private val _allSchedules = MutableLiveData<List<ScheduleApi>>()
    val allSchedules: LiveData<List<ScheduleApi>> = _allSchedules

    private val _saveStatus = MutableStateFlow<SaveResult<ScheduleApi>>(SaveResult.Initial)
    val saveStatus: StateFlow<SaveResult<ScheduleApi>> = _saveStatus

    fun getAllSchedules() {
        viewModelScope.launch {
            try {
                val response = repository.getAllSchedules()
                if (response.isSuccessful && response.body()?.success == true) {
                    _allSchedules.value = response.body()?.data ?: emptyList()
                }
            } catch (e: Exception) {
                _allSchedules.value = emptyList()
            }
        }
    }

    fun getSchedulesByDay(day: String) {
        viewModelScope.launch {
            try {
                val response = repository.getSchedulesByDay(day)
                if (response.isSuccessful && response.body()?.success == true) {
                    _allSchedules.value = response.body()?.data ?: emptyList()
                }
            } catch (e: Exception) {
                _allSchedules.value = emptyList()
            }
        }
    }

    fun createSchedule(schedule: com.google.gson.JsonObject) {
        viewModelScope.launch {
            try {
                val response = repository.createSchedule(schedule)
                if (response.isSuccessful && response.body()?.success == true) {
                    val createdSchedule = response.body()?.data
                    if (createdSchedule != null) {
                        _saveStatus.value = SaveResult.Success(createdSchedule)
                        getAllSchedules() // Refresh list
                    } else {
                        _saveStatus.value = SaveResult.Error("Failed to create schedule")
                    }
                } else {
                    _saveStatus.value = SaveResult.Error("Failed to create schedule")
                }
            } catch (e: Exception) {
                _saveStatus.value = SaveResult.Error(e.message ?: "Network error")
            }
        }
    }

    fun updateSchedule(scheduleId: Int, schedule: com.google.gson.JsonObject) {
        viewModelScope.launch {
            try {
                val response = repository.updateSchedule(scheduleId, schedule)
                if (response.isSuccessful && response.body()?.success == true) {
                    val updatedSchedule = response.body()?.data
                    if (updatedSchedule != null) {
                        _saveStatus.value = SaveResult.Success(updatedSchedule)
                        getAllSchedules() // Refresh list
                    } else {
                        _saveStatus.value = SaveResult.Error("Failed to update schedule")
                    }
                } else {
                    _saveStatus.value = SaveResult.Error("Failed to update schedule")
                }
            } catch (e: Exception) {
                _saveStatus.value = SaveResult.Error(e.message ?: "Network error")
            }
        }
    }

    fun deleteSchedule(scheduleId: Int) {
        viewModelScope.launch {
            try {
                val response = repository.deleteSchedule(scheduleId)
                if (response.isSuccessful && response.body()?.success == true) {
                    getAllSchedules() // Refresh list
                }
            } catch (e: Exception) {
                // Handle error silently
            }
        }
    }

    fun resetSaveStatus() {
        _saveStatus.value = SaveResult.Initial
    }
}

/**
 * ViewModel for Classroom monitoring
 * All data comes from MySQL database via Laravel API
 */
class ClassroomViewModel(application: Application) : AndroidViewModel(application) {
    private val repository = ClassroomRepository(application)

    private val _allClassrooms = MutableLiveData<List<ClassroomApi>>()
    val allClassrooms: LiveData<List<ClassroomApi>> = _allClassrooms

    private val _emptyClassrooms = MutableLiveData<List<ClassroomApi>>()
    val emptyClassrooms: LiveData<List<ClassroomApi>> = _emptyClassrooms

    fun getAllClassrooms() {
        viewModelScope.launch {
            try {
                val response = repository.getAllClassrooms()
                if (response.isSuccessful && response.body()?.success == true) {
                    _allClassrooms.value = response.body()?.data ?: emptyList()
                }
            } catch (e: Exception) {
                _allClassrooms.value = emptyList()
            }
        }
    }

    fun getEmptyClassrooms(day: String? = null, periodNumber: Int? = null) {
        viewModelScope.launch {
            try {
                val response = repository.getEmptyClassrooms(day, periodNumber)
                if (response.isSuccessful && response.body()?.success == true) {
                    _emptyClassrooms.value = response.body()?.data ?: emptyList()
                }
            } catch (e: Exception) {
                _emptyClassrooms.value = emptyList()
            }
        }
    }
}

// Result classes for handling operations
sealed class LoginResult {
    data class Success(val user: UserApi) : LoginResult()
    data class Error(val message: String) : LoginResult()
    object Logout : LoginResult()
}

sealed class SaveResult<out T> {
    object Initial : SaveResult<Nothing>()
    data class Success<T>(val data: T) : SaveResult<T>()
    data class Error(val message: String) : SaveResult<Nothing>()
}

// Factory for creating ViewModels with Application context
class ViewModelFactory(private val application: Application) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        return when {
            modelClass.isAssignableFrom(UserViewModel::class.java) -> UserViewModel(application) as T
            modelClass.isAssignableFrom(ScheduleViewModel::class.java) -> ScheduleViewModel(application) as T
            modelClass.isAssignableFrom(ClassroomViewModel::class.java) -> ClassroomViewModel(application) as T
            else -> throw IllegalArgumentException("Unknown ViewModel class")
        }
    }
}

