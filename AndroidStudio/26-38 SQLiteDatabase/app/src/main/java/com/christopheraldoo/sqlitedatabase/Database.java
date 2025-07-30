package com.christopheraldoo.sqlitedatabase;

import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;

public class Database extends SQLiteOpenHelper {

    private static final String DATABASE_NAME = "dbtoko";
    private static final int VERSION = 1;

    SQLiteDatabase db;

    public Database(Context context) { // jangan lupa nama method nya harus sama dengan nama class nya
        super(context, DATABASE_NAME, null, VERSION);
        db = this.getWritableDatabase();

    }

    boolean runSQL(String sql) {
        try {
            db.execSQL(sql);
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    Cursor select(String sql) {
        try {
            return db.rawQuery(sql, null);
        } catch (Exception e) {
            return null;
        }
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        // Pindahkan pembuatan tabel ke sini
        String tblbarang = "CREATE TABLE IF NOT EXISTS tblbarang (" +
                "idbarang INTEGER PRIMARY KEY AUTOINCREMENT, " +
                "barang TEXT, " +
                "stok REAL, " +
                "harga REAL" +
                ");";

        db.execSQL(tblbarang);
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        // Handle database upgrade logic here
    }
}
