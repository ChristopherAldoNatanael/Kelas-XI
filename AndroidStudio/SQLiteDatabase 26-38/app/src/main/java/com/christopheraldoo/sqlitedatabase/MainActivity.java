package com.christopheraldoo.sqlitedatabase;

import android.database.Cursor;
import android.os.Bundle;
import android.view.View;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import java.util.ArrayList;
import java.util.List;

public class MainActivity extends AppCompatActivity implements BarangAdapter.OnItemActionListener {

    Database db;
    EditText etBarang, etStok, etHarga;
    TextView tvPilihan;

    List<Barang> dataBarang = new ArrayList<Barang>();
    BarangAdapter adapter;
    RecyclerView rvBarang;

    private String selectedId = ""; // Untuk menyimpan ID barang yang dipilih

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_main);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });

        // Inisialisasi database di onCreate
        load();
        selectData();
    }

    public void load() {
        db = new Database(this);

        etBarang = findViewById(R.id.etBarang);
        etStok = findViewById(R.id.etStok);
        etHarga = findViewById(R.id.etHarga);
        tvPilihan = findViewById(R.id.tvPilihan);
        rvBarang = findViewById(R.id.rvBarang);

        rvBarang.setLayoutManager(new LinearLayoutManager(this));
        rvBarang.setHasFixedSize(true);
    }

    public void pesan(String isi) {
        Toast.makeText(this, isi, Toast.LENGTH_SHORT).show();
    }

    public void selectData() {
        String sql = "SELECT * FROM tblbarang ORDER BY barang ASC";
        Cursor cursor = db.select(sql);

        if (cursor != null && cursor.getCount() > 0) {
            dataBarang.clear();
            while (cursor.moveToNext()) {
                String idBarang = cursor.getString(0);
                String barang = cursor.getString(1);
                String stok = cursor.getString(2);
                String harga = cursor.getString(3);

                dataBarang.add(new Barang(idBarang, barang, stok, harga));
            }

            // Setup adapter dengan listener
            adapter = new BarangAdapter(dataBarang, this, this);
            rvBarang.setAdapter(adapter);

            cursor.close();
        } else {
            pesan("Data kosong");
            if (cursor != null)
                cursor.close();
        }
    }

    public void simpan(View v) {
        String barang = etBarang.getText().toString();
        String stok = etStok.getText().toString();
        String harga = etHarga.getText().toString();
        String pilihan = tvPilihan.getText().toString();

        if (barang.isEmpty() || stok.isEmpty() || harga.isEmpty()) {
            pesan("Data Kosong");
        } else {
            if (pilihan.equals("Insert")) {
                String sql = "INSERT INTO tblbarang(barang,stok,harga) VALUES ('" + barang + "'," + stok + "," + harga
                        + ")";
                if (db.runSQL(sql)) {
                    pesan("Insert berhasil");
                    selectData();
                } else {
                    pesan("Insert gagal");
                }
            } else if (pilihan.equals("Update")) {
                String sql = "UPDATE tblbarang SET barang='" + barang + "', stok=" + stok + ", harga=" + harga
                        + " WHERE idbarang=" + selectedId;
                if (db.runSQL(sql)) {
                    pesan("Update berhasil");
                    selectData();
                } else {
                    pesan("Update gagal");
                }
            }
        }

        // Clear form setelah simpan
        etBarang.setText("");
        etStok.setText("");
        etHarga.setText("");
        tvPilihan.setText("Insert");
        selectedId = "";
    }

    // Implementasi interface OnItemActionListener
    @Override
    public void onUbahClicked(Barang barang) {
        // Isi form dengan data barang yang dipilih
        etBarang.setText(barang.getBarang());
        etStok.setText(barang.getStok());
        etHarga.setText(barang.getHarga());
        tvPilihan.setText("Update");
        selectedId = barang.getIdBarang();

        pesan("Data siap diubah, tekan tombol Simpan");
    }

    @Override
    public void onHapusClicked(Barang barang) {
        // Konfirmasi hapus
        android.app.AlertDialog.Builder builder = new android.app.AlertDialog.Builder(this);
        builder.setTitle("Konfirmasi Hapus");
        builder.setMessage("Yakin ingin menghapus " + barang.getBarang() + "?");

        builder.setPositiveButton("Ya", new android.content.DialogInterface.OnClickListener() {
            @Override
            public void onClick(android.content.DialogInterface dialog, int which) {
                String sql = "DELETE FROM tblbarang WHERE idbarang=" + barang.getIdBarang();
                if (db.runSQL(sql)) {
                    pesan("Data berhasil dihapus");
                    selectData(); // Refresh data
                } else {
                    pesan("Gagal menghapus data");
                }
            }
        });

        builder.setNegativeButton("Batal", null);
        builder.show();
    }
}