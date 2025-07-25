package com.christopheraldoo.sharedprefrences;

import androidx.appcompat.app.AppCompatActivity;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends AppCompatActivity {

    // Deklarasi variabel untuk komponen UI
    private EditText etName, etAge;
    private Button btnSave, btnLoad, btnClear;
    private TextView tvResult;
    
    // SharedPreferences
    private SharedPreferences sharedPreferences;
    private SharedPreferences.Editor editor;
    
    // Konstanta untuk nama file dan key
    private static final String PREF_NAME = "UserData";
    private static final String KEY_NAME = "name";
    private static final String KEY_AGE = "age";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        
        // Inisialisasi komponen UI
        initViews();
        
        // Inisialisasi SharedPreferences
        sharedPreferences = getSharedPreferences(PREF_NAME, MODE_PRIVATE);
        editor = sharedPreferences.edit();
        
        // Set event listener untuk tombol
        setClickListeners();
        
        // Load data saat aplikasi pertama kali dibuka
        loadData();
    }
    
    private void initViews() {
        etName = findViewById(R.id.etName);
        etAge = findViewById(R.id.etAge);
        btnSave = findViewById(R.id.btnSave);
        btnLoad = findViewById(R.id.btnLoad);
        btnClear = findViewById(R.id.btnClear);
        tvResult = findViewById(R.id.tvResult);
    }
    
    private void setClickListeners() {
        btnSave.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                saveData();
            }
        });
        
        btnLoad.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                loadData();
            }
        });
        
        btnClear.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                clearData();
            }
        });
    }
    
    private void saveData() {
        String name = etName.getText().toString().trim();
        String ageStr = etAge.getText().toString().trim();
        
        if (name.isEmpty() || ageStr.isEmpty()) {
            Toast.makeText(this, "Mohon isi semua field", Toast.LENGTH_SHORT).show();
            return;
        }
        
        int age = Integer.parseInt(ageStr);
        
        // Simpan data ke SharedPreferences
        editor.putString(KEY_NAME, name);
        editor.putInt(KEY_AGE, age);
        editor.apply(); // atau editor.commit()
        
        Toast.makeText(this, "Data berhasil disimpan", Toast.LENGTH_SHORT).show();
        
        // Clear input fields
        etName.setText("");
        etAge.setText("");
    }
    
    private void loadData() {
        // Ambil data dari SharedPreferences
        String name = sharedPreferences.getString(KEY_NAME, "Tidak ada nama");
        int age = sharedPreferences.getInt(KEY_AGE, 0);
        
        // Tampilkan data
        if (age == 0) {
            tvResult.setText("Belum ada data tersimpan");
        } else {
            tvResult.setText("Nama: " + name + "\nUmur: " + age + " tahun");
        }
    }
    
    private void clearData() {
        // Hapus semua data dari SharedPreferences
        editor.clear();
        editor.apply();
        
        // Reset tampilan
        tvResult.setText("Data telah dihapus");
        etName.setText("");
        etAge.setText("");
        
        Toast.makeText(this, "Data berhasil dihapus", Toast.LENGTH_SHORT).show();
    }
}