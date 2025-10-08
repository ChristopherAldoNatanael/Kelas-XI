package com.christopheraldoo.todoapp.data

import androidx.room.*
import kotlinx.coroutines.flow.Flow

@Dao
interface TodoDao {

    // Get all todos ordered by creation date (newest first)
    @Query("SELECT * FROM todos ORDER BY created_at DESC")
    fun getAllTodos(): Flow<List<Todo>>

    // Get todos by completion status
    @Query("SELECT * FROM todos WHERE is_completed = :isCompleted ORDER BY created_at DESC")
    fun getTodosByStatus(isCompleted: Boolean): Flow<List<Todo>>

    // Get active todos (not completed)
    @Query("SELECT * FROM todos WHERE is_completed = 0 ORDER BY priority DESC, created_at DESC")
    fun getActiveTodos(): Flow<List<Todo>>

    // Get completed todos
    @Query("SELECT * FROM todos WHERE is_completed = 1 ORDER BY created_at DESC")
    fun getCompletedTodos(): Flow<List<Todo>>

    // Get todo by ID
    @Query("SELECT * FROM todos WHERE id = :id")
    fun getTodoById(id: Int): Flow<Todo>

    // Search todos by title or description
    @Query("SELECT * FROM todos WHERE title LIKE '%' || :query || '%' OR description LIKE '%' || :query || '%' ORDER BY created_at DESC")
    fun searchTodos(query: String): Flow<List<Todo>>

    // Get todos by priority
    @Query("SELECT * FROM todos WHERE priority >= :minPriority ORDER BY priority DESC, created_at DESC")
    fun getTodosByPriority(minPriority: Int): Flow<List<Todo>>

    // Insert new todo
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTodo(todo: Todo): Long

    // Insert multiple todos
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTodos(todos: List<Todo>): List<Long>

    // Update existing todo
    @Update
    suspend fun updateTodo(todo: Todo)

    // Update todo completion status
    @Query("UPDATE todos SET is_completed = :isCompleted WHERE id = :id")
    suspend fun updateTodoStatus(id: Int, isCompleted: Boolean)

    // Update todo priority
    @Query("UPDATE todos SET priority = :priority WHERE id = :id")
    suspend fun updateTodoPriority(id: Int, priority: Int)

    // Delete a todo
    @Delete
    suspend fun deleteTodo(todo: Todo)

    // Delete todo by ID
    @Query("DELETE FROM todos WHERE id = :id")
    suspend fun deleteTodoById(id: Int)

    // Delete all completed todos
    @Query("DELETE FROM todos WHERE is_completed = 1")
    suspend fun deleteCompletedTodos()

    // Delete all todos
    @Query("DELETE FROM todos")
    suspend fun deleteAllTodos()

    // Get todo count
    @Query("SELECT COUNT(*) FROM todos")
    fun getTodoCount(): Flow<Int>

    // Get completed todo count
    @Query("SELECT COUNT(*) FROM todos WHERE is_completed = 1")
    fun getCompletedTodoCount(): Flow<Int>

    // Get active todo count
    @Query("SELECT COUNT(*) FROM todos WHERE is_completed = 0")
    fun getActiveTodoCount(): Flow<Int>

    // Get highest priority
    @Query("SELECT MAX(priority) FROM todos")
    fun getMaxPriority(): Flow<Int>

    // Get todos with high priority
    @Query("SELECT * FROM todos WHERE priority > 0 ORDER BY priority DESC, created_at DESC")
    fun getHighPriorityTodos(): Flow<List<Todo>>
}
