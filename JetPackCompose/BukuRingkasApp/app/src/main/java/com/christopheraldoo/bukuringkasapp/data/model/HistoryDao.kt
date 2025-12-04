package com.christopheraldoo.bukuringkasapp.data.model

import androidx.room.Dao
import androidx.room.Delete
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import kotlinx.coroutines.flow.Flow

/**
 * Data Access Object untuk HistoryItem
 * Mengelola operasi database untuk riwayat ringkasan
 */
@Dao
interface HistoryDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(historyItem: HistoryItem): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(items: List<HistoryItem>)

    @Update
    suspend fun update(historyItem: HistoryItem)

    @Delete
    suspend fun delete(historyItem: HistoryItem)

    @Query("DELETE FROM history WHERE id = :id")
    suspend fun deleteById(id: Long)

    @Query("SELECT * FROM history WHERE id = :id")
    suspend fun getById(id: Long): HistoryItem?

    @Query("SELECT * FROM history ORDER BY createdAt DESC")
    fun getAll(): Flow<List<HistoryItem>>

    @Query("SELECT * FROM history WHERE subject = :subject ORDER BY createdAt DESC")
    fun getBySubject(subject: String): Flow<List<HistoryItem>>

    @Query("SELECT * FROM history WHERE grade = :grade ORDER BY createdAt DESC")
    fun getByGrade(grade: Int): Flow<List<HistoryItem>>

    @Query("SELECT COUNT(*) FROM history")
    fun getCount(): Flow<Int>

    @Query("SELECT COUNT(*) FROM history WHERE subject = :subject")
    fun getCountBySubject(subject: String): Flow<Int>

    @Query("DELETE FROM history")
    suspend fun deleteAll()

    @Query("DELETE FROM history WHERE createdAt < :timestamp")
    suspend fun deleteOlderThan(timestamp: Long)
}
