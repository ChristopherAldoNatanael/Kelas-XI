package com.christopheraldoo.todoapp.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.asLiveData
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import com.christopheraldoo.todoapp.data.Todo
import com.christopheraldoo.todoapp.data.TodoDatabase
import com.christopheraldoo.todoapp.data.TodoRepository
import com.christopheraldoo.todoapp.data.TodoFilter
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.launch
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map

class TodoViewModel(private val repository: TodoRepository) : ViewModel() {

    // Flow for UI observation (to use with collectAsState)
    val allTodos: Flow<List<Todo>> = repository.allTodos
    val activeTodos: Flow<List<Todo>> = repository.activeTodos
    val completedTodos: Flow<List<Todo>> = repository.completedTodos
    val todoCount: Flow<Int> = repository.todoCount
    val activeTodoCount: Flow<Int> = repository.activeTodoCount
    val completedTodoCount: Flow<Int> = repository.completedTodoCount

    // Current filter state
    private val _currentFilter = MutableStateFlow(TodoFilter.ALL)
    val currentFilter: StateFlow<TodoFilter> = _currentFilter

    // Filtered todos based on current filter
    val filteredTodos: LiveData<List<Todo>> = repository.getFilteredTodos(TodoFilter.ALL).asLiveData()

    // Loading state
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading

    // Error state
    private val _errorMessage = MutableLiveData<String?>()
    val errorMessage: LiveData<String?> = _errorMessage

    // Add new todo
    fun addTodo(title: String, description: String = "", priority: Int = 0) {
        if (title.isNotBlank()) {
            viewModelScope.launch {
                try {
                    _isLoading.value = true
                    _errorMessage.value = null

                    val newTodo = Todo(
                        title = title.trim(),
                        description = description.trim(),
                        priority = priority
                    )
                    repository.insertTodo(newTodo)
                } catch (e: Exception) {
                    _errorMessage.value = "Failed to add todo: ${e.message}"
                } finally {
                    _isLoading.value = false
                }
            }
        }
    }

    // Update existing todo
    fun updateTodo(todo: Todo) {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _errorMessage.value = null
                repository.updateTodo(todo)
            } catch (e: Exception) {
                _errorMessage.value = "Failed to update todo: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    // Update todo title and description
    fun updateTodoDetails(id: Int, title: String, description: String) {
        if (title.isNotBlank()) {
            viewModelScope.launch {
                try {
                    _errorMessage.value = null
                    // Get current todo from the database first
                    repository.getTodoById(id).collect { currentTodo ->
                        if (currentTodo != null) {
                            val updatedTodo = currentTodo.copy(
                                title = title.trim(),
                                description = description.trim()
                            )
                            repository.updateTodo(updatedTodo)
                        }
                    }
                } catch (e: Exception) {
                    _errorMessage.value = "Failed to update todo: ${e.message}"
                }
            }
        }
    }

    // Toggle todo completion status
    fun toggleTodoStatus(todo: Todo) {
        viewModelScope.launch {
            try {
                _errorMessage.value = null
                repository.toggleTodoStatus(todo)
            } catch (e: Exception) {
                _errorMessage.value = "Failed to toggle todo: ${e.message}"
            }
        }
    }

    // Update todo priority
    fun updateTodoPriority(id: Int, priority: Int) {
        viewModelScope.launch {
            try {
                _errorMessage.value = null
                repository.updateTodoPriority(id, priority)
            } catch (e: Exception) {
                _errorMessage.value = "Failed to update priority: ${e.message}"
            }
        }
    }

    // Delete a todo
    fun deleteTodo(todo: Todo) {
        viewModelScope.launch {
            try {
                _errorMessage.value = null
                repository.deleteTodo(todo)
            } catch (e: Exception) {
                _errorMessage.value = "Failed to delete todo: ${e.message}"
            }
        }
    }

    // Delete todo by ID
    fun deleteTodoById(id: Int) {
        viewModelScope.launch {
            try {
                _errorMessage.value = null
                repository.deleteTodoById(id)
            } catch (e: Exception) {
                _errorMessage.value = "Failed to delete todo: ${e.message}"
            }
        }
    }

    // Delete all completed todos
    fun deleteCompletedTodos() {
        viewModelScope.launch {
            try {
                _errorMessage.value = null
                repository.deleteCompletedTodos()
            } catch (e: Exception) {
                _errorMessage.value = "Failed to delete completed todos: ${e.message}"
            }
        }
    }

    // Delete all todos
    fun deleteAllTodos() {
        viewModelScope.launch {
            try {
                _errorMessage.value = null
                repository.deleteAllTodos()
            } catch (e: Exception) {
                _errorMessage.value = "Failed to delete all todos: ${e.message}"
            }
        }
    }

    // Set filter
    fun setFilter(filter: TodoFilter) {
        _currentFilter.value = filter
    }

    // Search todos
    fun searchTodos(query: String): LiveData<List<Todo>> {
        return repository.searchTodos(query).asLiveData()
    }

    // Get todo by ID
    fun getTodoById(id: Int): LiveData<Todo> {
        return repository.getTodoById(id).asLiveData()
    }

    // Clear error message
    fun clearErrorMessage() {
        _errorMessage.value = null
    }

    // Refresh data (useful for pull-to-refresh)
    fun refreshTodos() {
        // Since we're using Flow, data is automatically refreshed
        // This method can be used for additional refresh logic if needed
    }
}
