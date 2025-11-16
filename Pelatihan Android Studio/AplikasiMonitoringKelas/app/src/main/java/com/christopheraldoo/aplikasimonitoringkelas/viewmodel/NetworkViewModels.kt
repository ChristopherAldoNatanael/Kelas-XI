package com.christopheraldoo.aplikasimonitoringkelas.viewmodel

import android.app.Application
import android.util.Log
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomApi
import com.christopheraldoo.aplikasimonitoringkelas.data.LoginResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleApi
import com.christopheraldoo.aplikasimonitoringkelas.data.SubjectApi
import com.christopheraldoo.aplikasimonitoringkelas.data.TeacherApi
import com.christopheraldoo.aplikasimonitoringkelas.data.UserApi
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkRepository
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkUtils
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.google.gson.JsonObject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class AuthViewModel(application: Application) : AndroidViewModel(application) {
    private val networkRepository = NetworkRepository(application)
    private val sessionManager = SessionManager(application)

    private val _loginState = MutableStateFlow<ApiState<LoginResponse>>(ApiState.Initial)
    val loginState: StateFlow<ApiState<LoginResponse>> = _loginState

    private val _currentUserState = MutableStateFlow<ApiState<UserApi>>(ApiState.Initial)
    val currentUserState: StateFlow<ApiState<UserApi>> = _currentUserState

    private val _logoutState = MutableStateFlow<ApiState<Boolean>>(ApiState.Initial)
    val logoutState: StateFlow<ApiState<Boolean>> = _logoutState

    fun login(email: String, password: String) {
        _loginState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val (response, error) = networkRepository.login(email, password)

                if (response != null && response.success == true) {
                    val user = response.data?.user
                    val token = response.data?.token
                    if (user != null && token != null) {
                        sessionManager.createLoginSession(
                            id = user.id.toLong(),
                            name = user.nama,
                            email = user.email,
                            role = user.role,
                            token = token,
                            classId = user.classId
                        )

                        _loginState.value = ApiState.Success(response)
                    } else {
                        _loginState.value = ApiState.Error("Login response data is invalid")
                    }
                } else {
                    _loginState.value = ApiState.Error(error ?: (response?.message ?: "Unknown error"))
                }
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Login error", e)
                _loginState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun getCurrentUser() {
        _currentUserState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _currentUserState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (user, error) = networkRepository.getCurrentUser(token)

                if (user != null) {
                    _currentUserState.value = ApiState.Success(user)
                } else {
                    _currentUserState.value = ApiState.Error(error ?: "Failed to get user data")
                }
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Get current user error", e)
                _currentUserState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun logout() {
        _logoutState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token != null) {
                    val (success, error) = networkRepository.logout(token)

                    if (success) {
                        sessionManager.logoutUser()
                        _logoutState.value = ApiState.Success(true)
                    } else {
                        _logoutState.value = ApiState.Error(error ?: "Logout failed")
                    }
                } else {
                    sessionManager.logoutUser()
                    _logoutState.value = ApiState.Success(true)
                }
            } catch (e: Exception) {
                Log.e("AuthViewModel", "Logout error", e)
                sessionManager.logoutUser()
                _logoutState.value = ApiState.Success(true)
            }
        }
    }

    fun resetStates() {
        _loginState.value = ApiState.Initial
        _currentUserState.value = ApiState.Initial
        _logoutState.value = ApiState.Initial
    }
}

class ScheduleNetworkViewModel(application: Application) : AndroidViewModel(application) {
    private val networkRepository = NetworkRepository(application)

    private val _schedulesState = MutableStateFlow<ApiState<List<ScheduleApi>>>(ApiState.Initial)
    val schedulesState: StateFlow<ApiState<List<ScheduleApi>>> = _schedulesState

    private val _createScheduleState = MutableStateFlow<ApiState<ScheduleApi>>(ApiState.Initial)
    val createScheduleState: StateFlow<ApiState<ScheduleApi>> = _createScheduleState

    private val _updateScheduleState = MutableStateFlow<ApiState<ScheduleApi>>(ApiState.Initial)
    val updateScheduleState: StateFlow<ApiState<ScheduleApi>> = _updateScheduleState

    private val _deleteScheduleState = MutableStateFlow<ApiState<Boolean>>(ApiState.Initial)
    val deleteScheduleState: StateFlow<ApiState<Boolean>> = _deleteScheduleState

    private var isLoadingSchedules = false

    fun getSchedules(day: String? = null, classId: Int? = null, teacherId: Int? = null) {
        if (isLoadingSchedules) {
            Log.d("ScheduleNetworkViewModel", "Schedules already loading, skipping duplicate request")
            return
        }
        
        isLoadingSchedules = true
        _schedulesState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _schedulesState.value = ApiState.Error("No authentication token found")
                    isLoadingSchedules = false
                    return@launch
                }

                val (schedules, error) = networkRepository.getSchedules(token, day, classId, teacherId)

                if (schedules != null) {
                    _schedulesState.value = ApiState.Success(schedules)
                    Log.d("ScheduleNetworkViewModel", "Successfully loaded ${schedules.size} schedules")
                } else {
                    _schedulesState.value = ApiState.Error(error ?: "Failed to get schedules")
                    Log.e("ScheduleNetworkViewModel", "Failed to load schedules: $error")
                }
            } catch (e: Exception) {
                Log.e("ScheduleNetworkViewModel", "Get schedules error", e)
                _schedulesState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            } finally {
                isLoadingSchedules = false
            }
        }
    }

    fun createSchedule(scheduleJson: JsonObject) {
        _createScheduleState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _createScheduleState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (schedule, error) = networkRepository.createSchedule(token, scheduleJson)

                if (schedule != null) {
                    _createScheduleState.value = ApiState.Success(schedule)
                    Log.d("ScheduleNetworkViewModel", "Schedule created successfully, refreshing list")
                    getSchedules()
                } else {
                    _createScheduleState.value = ApiState.Error(error ?: "Failed to create schedule")
                    Log.e("ScheduleNetworkViewModel", "Failed to create schedule: $error")
                }
            } catch (e: Exception) {
                Log.e("ScheduleNetworkViewModel", "Create schedule error", e)
                _createScheduleState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun updateSchedule(scheduleId: Int, scheduleJson: JsonObject) {
        _updateScheduleState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _updateScheduleState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (schedule, error) = networkRepository.updateSchedule(token, scheduleId, scheduleJson)

                if (schedule != null) {
                    _updateScheduleState.value = ApiState.Success(schedule)
                    getSchedules()
                } else {
                    _updateScheduleState.value = ApiState.Error(error ?: "Failed to update schedule")
                }
            } catch (e: Exception) {
                Log.e("ScheduleNetworkViewModel", "Update schedule error", e)
                _updateScheduleState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun deleteSchedule(scheduleId: Int) {
        _deleteScheduleState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _deleteScheduleState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (success, error) = networkRepository.deleteSchedule(token, scheduleId)

                if (success) {
                    _deleteScheduleState.value = ApiState.Success(true)
                    getSchedules()
                } else {
                    _deleteScheduleState.value = ApiState.Error(error ?: "Failed to delete schedule")
                }
            } catch (e: Exception) {
                Log.e("ScheduleNetworkViewModel", "Delete schedule error", e)
                _deleteScheduleState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun resetStates() {
        _schedulesState.value = ApiState.Initial
        _createScheduleState.value = ApiState.Initial
        _updateScheduleState.value = ApiState.Initial
        _deleteScheduleState.value = ApiState.Initial
    }
}

class ClassroomNetworkViewModel(application: Application) : AndroidViewModel(application) {
    private val networkRepository = NetworkRepository(application)

    private val _emptyClassroomsState = MutableStateFlow<ApiState<List<ClassroomApi>>>(ApiState.Initial)
    val emptyClassroomsState: StateFlow<ApiState<List<ClassroomApi>>> = _emptyClassroomsState

    private val _classroomsState = MutableStateFlow<ApiState<List<ClassroomApi>>>(ApiState.Initial)
    val classroomsState: StateFlow<ApiState<List<ClassroomApi>>> = _classroomsState

    fun getEmptyClassrooms(day: String? = null, period: Int? = null) {
        _emptyClassroomsState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _emptyClassroomsState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (classrooms, error) = networkRepository.getEmptyClassrooms(token, day, period)

                if (classrooms != null) {
                    _emptyClassroomsState.value = ApiState.Success(classrooms)
                } else {
                    _emptyClassroomsState.value = ApiState.Error(error ?: "Failed to get empty classrooms")
                }
            } catch (e: Exception) {
                Log.e("ClassroomNetworkViewModel", "Get empty classrooms error", e)
                _emptyClassroomsState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun getClassrooms() {
        _classroomsState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val (classrooms, error) = networkRepository.getDropdownClassrooms()

                if (classrooms != null) {
                    _classroomsState.value = ApiState.Success(classrooms)
                } else {
                    _classroomsState.value = ApiState.Error(error ?: "Failed to get classrooms")
                }
            } catch (e: Exception) {
                Log.e("ClassroomNetworkViewModel", "Get classrooms error", e)
                _classroomsState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun resetStates() {
        _emptyClassroomsState.value = ApiState.Initial
        _classroomsState.value = ApiState.Initial
    }
}

class MasterDataNetworkViewModel(application: Application) : AndroidViewModel(application) {
    private val networkRepository = NetworkRepository(application)

    private val _subjectsState = MutableStateFlow<ApiState<List<SubjectApi>>>(ApiState.Initial)
    val subjectsState: StateFlow<ApiState<List<SubjectApi>>> = _subjectsState

    private val _teachersState = MutableStateFlow<ApiState<List<TeacherApi>>>(ApiState.Initial)
    val teachersState: StateFlow<ApiState<List<TeacherApi>>> = _teachersState

    private val _usersState = MutableStateFlow<ApiState<List<UserApi>>>(ApiState.Initial)
    val usersState: StateFlow<ApiState<List<UserApi>>> = _usersState

    private val _filteredTeachersState = MutableStateFlow<ApiState<List<TeacherApi>>>(ApiState.Initial)
    val filteredTeachersState: StateFlow<ApiState<List<TeacherApi>>> = _filteredTeachersState

    fun getSubjects() {
        _subjectsState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val (subjects, error) = networkRepository.getDropdownSubjects()

                if (subjects != null) {
                    _subjectsState.value = ApiState.Success(subjects)
                } else {
                    _subjectsState.value = ApiState.Error(error ?: "Failed to get subjects")
                }
            } catch (e: Exception) {
                Log.e("MasterDataNetworkViewModel", "Get subjects error", e)
                _subjectsState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun getTeachers() {
        _teachersState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _teachersState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (teachers, error) = networkRepository.getTeachers(token)

                if (teachers != null) {
                    _teachersState.value = ApiState.Success(teachers)
                } else {
                    _teachersState.value = ApiState.Error(error ?: "Failed to get teachers")
                }
            } catch (e: Exception) {
                Log.e("MasterDataNetworkViewModel", "Get teachers error", e)
                _teachersState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun getUsers() {
        _usersState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val token = NetworkUtils.getAuthToken(getApplication())
                if (token == null) {
                    _usersState.value = ApiState.Error("No authentication token found")
                    return@launch
                }

                val (users, error) = networkRepository.getUsers(token)

                if (users != null) {
                    _usersState.value = ApiState.Success(users)
                } else {
                    _usersState.value = ApiState.Error(error ?: "Failed to get users")
                }
            } catch (e: Exception) {
                Log.e("MasterDataNetworkViewModel", "Get users error", e)
                _usersState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun getFilteredTeachersBySubject(subjectId: Int) {
        _filteredTeachersState.value = ApiState.Loading

        viewModelScope.launch {
            try {
                val (teachers, error) = networkRepository.getDropdownTeachersBySubject(subjectId)

                if (teachers != null) {
                    _filteredTeachersState.value = ApiState.Success(teachers)
                } else {
                    _filteredTeachersState.value = ApiState.Error(error ?: "Failed to get teachers by subject")
                }
            } catch (e: Exception) {
                Log.e("MasterDataNetworkViewModel", "Get filtered teachers error", e)
                _filteredTeachersState.value = ApiState.Error(e.localizedMessage ?: "Unknown error")
            }
        }
    }

    fun resetStates() {
        _subjectsState.value = ApiState.Initial
        _teachersState.value = ApiState.Initial
        _usersState.value = ApiState.Initial
        _filteredTeachersState.value = ApiState.Initial
    }
}

sealed class ApiState<out T> {
    object Initial : ApiState<Nothing>()
    object Loading : ApiState<Nothing>()
    data class Success<T>(val data: T) : ApiState<T>()
    data class Error(val message: String) : ApiState<Nothing>()
}

