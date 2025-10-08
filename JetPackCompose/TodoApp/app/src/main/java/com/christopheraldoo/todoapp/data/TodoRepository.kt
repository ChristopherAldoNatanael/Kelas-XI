package com.christopheraldoo.todoapp.data

import androidx.lifecycle.LiveData
import androidx.lifecycle.asLiveData
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map

class TodoRepository(private val todoDao: TodoDao) {

    // Get all todos
    val allTodos: Flow<List<Todo>> = todoDao.getAllTodos()

    // Get active todos (not completed)
    val activeTodos: Flow<List<Todo>> = todoDao.getActiveTodos()

    // Get completed todos
    val completedTodos: Flow<List<Todo>> = todoDao.getCompletedTodos()

    // Get todo count
    val todoCount: Flow<Int> = todoDao.getTodoCount()

    // Get active todo count
    val activeTodoCount: Flow<Int> = todoDao.getActiveTodoCount()

    // Get completed todo count
    val completedTodoCount: Flow<Int> = todoDao.getCompletedTodoCount()

    // Get high priority todos
    val highPriorityTodos: Flow<List<Todo>> = todoDao.getHighPriorityTodos()

    // Insert new todo
    suspend fun insertTodo(todo: Todo): Long {
        return todoDao.insertTodo(todo)
    }

    // Insert multiple todos
    suspend fun insertTodos(todos: List<Todo>): List<Long> {
        return todoDao.insertTodos(todos)
    }

    // Update existing todo
    suspend fun updateTodo(todo: Todo) {
        todoDao.updateTodo(todo)
    }

    // Update todo completion status
    suspend fun updateTodoStatus(id: Int, isCompleted: Boolean) {
        todoDao.updateTodoStatus(id, isCompleted)
    }

    // Update todo priority
    suspend fun updateTodoPriority(id: Int, priority: Int) {
        todoDao.updateTodoPriority(id, priority)
    }

    // Toggle todo completion status
    suspend fun toggleTodoStatus(todo: Todo) {
        todoDao.updateTodoStatus(todo.id, !todo.isCompleted)
    }

    // Delete a todo
    suspend fun deleteTodo(todo: Todo) {
        todoDao.deleteTodo(todo)
    }

    // Delete todo by ID
    suspend fun deleteTodoById(id: Int) {
        todoDao.deleteTodoById(id)
    }

    // Delete all completed todos
    suspend fun deleteCompletedTodos() {
        todoDao.deleteCompletedTodos()
    }

    // Delete all todos
    suspend fun deleteAllTodos() {
        todoDao.deleteAllTodos()
    }

    // Get todo by ID
    fun getTodoById(id: Int): Flow<Todo> {
        return todoDao.getTodoById(id)
    }

    // Search todos
    fun searchTodos(query: String): Flow<List<Todo>> {
        return todoDao.searchTodos(query)
    }

    // Get todos by priority
    fun getTodosByPriority(minPriority: Int): Flow<List<Todo>> {
        return todoDao.getTodosByPriority(minPriority)
    }

    // Get todos by completion status
    fun getTodosByStatus(isCompleted: Boolean): Flow<List<Todo>> {
        return todoDao.getTodosByStatus(isCompleted)
    }

    // Get filtered todos based on TodoFilter enum
    fun getFilteredTodos(filter: TodoFilter): Flow<List<Todo>> {
        return when (filter) {
            TodoFilter.ALL -> allTodos
            TodoFilter.ACTIVE -> activeTodos
            TodoFilter.COMPLETED -> completedTodos
        }
    }

    // Get filtered todos based on TodoFilter enum (reactive version)
    fun getFilteredTodosFlow(filterFlow: StateFlow<TodoFilter>): LiveData<List<Todo>> {
        // For simplicity, just return all todos for now
        // In a real implementation, you would combine the flows properly
        return allTodos.asLiveData()
    }

    // Get todos sorted by priority and status
    fun getTodosByPriorityAndStatus(): Flow<List<Todo>> {
        return allTodos.map { todos ->
            todos.sortedWith(
                compareBy<Todo> { !it.isCompleted }
                    .thenByDescending { it.priority }
                    .thenByDescending { it.createdAt }
            )
        }
    }

    // Get completion statistics
    fun getCompletionStats(): Flow<Pair<Int, Int>> {
        return todoDao.getTodoCount().map { total ->
            Pair(total, 0) // We'll need to calculate completed separately
        }
    }

    // Check if todo exists
    suspend fun todoExists(id: Int): Boolean {
        return try {
            todoDao.getTodoById(id).map { it != null }
            true
        } catch (e: Exception) {
            false
        }
    }
}
