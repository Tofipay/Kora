package com.tofixtv.app.data.local

import androidx.room.*
import kotlinx.coroutines.flow.Flow

/**
 * A favorited entity — a team, league, player or match the user pinned.
 * Also doubles as a lightweight offline snapshot store for list screens.
 */
@Entity(tableName = "favorites")
data class FavoriteEntity(
    @PrimaryKey val key: String,          // "team:5922", "league:894789", "match:466..."
    val type: String,                     // team | league | player | match
    val refId: Long,
    val title: String,
    val image: String? = null,
    val subtitle: String? = null,
    val addedAt: Long = System.currentTimeMillis()
)

@Dao
interface FavoriteDao {
    @Query("SELECT * FROM favorites ORDER BY addedAt DESC")
    fun all(): Flow<List<FavoriteEntity>>

    @Query("SELECT * FROM favorites WHERE type = :type ORDER BY addedAt DESC")
    fun byType(type: String): Flow<List<FavoriteEntity>>

    @Query("SELECT EXISTS(SELECT 1 FROM favorites WHERE key = :key)")
    suspend fun exists(key: String): Boolean

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun add(item: FavoriteEntity)

    @Query("DELETE FROM favorites WHERE key = :key")
    suspend fun remove(key: String)
}

/** Cached JSON snapshot for a screen (offline read-through backup). */
@Entity(tableName = "snapshots")
data class SnapshotEntity(
    @PrimaryKey val key: String,
    val json: String,
    val savedAt: Long = System.currentTimeMillis()
)

@Dao
interface SnapshotDao {
    @Query("SELECT * FROM snapshots WHERE key = :key LIMIT 1")
    suspend fun get(key: String): SnapshotEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun put(item: SnapshotEntity)
}

@Database(entities = [FavoriteEntity::class, SnapshotEntity::class], version = 1, exportSchema = false)
abstract class AppDatabase : RoomDatabase() {
    abstract fun favorites(): FavoriteDao
    abstract fun snapshots(): SnapshotDao

    companion object {
        @Volatile private var instance: AppDatabase? = null
        fun get(ctx: android.content.Context): AppDatabase =
            instance ?: synchronized(this) {
                instance ?: Room.databaseBuilder(
                    ctx.applicationContext, AppDatabase::class.java, "tofixtv.db"
                ).fallbackToDestructiveMigration().build().also { instance = it }
            }
    }
}
