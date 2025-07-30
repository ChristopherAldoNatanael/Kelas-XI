package com.christopheraldoo.konversisuhu;

import android.os.Bundle;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

public class MainActivity extends AppCompatActivity {

    Spinner spinner;
    EditText etNilai;
    TextView tvHasil;

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
        
        load(); // Panggil method load() untuk inisialisasi spinner
//        isiSpinner();
    }

    public void load () {   
        spinner = findViewById(R.id.spinner);
        etNilai = findViewById(R.id.etNilai);
        tvHasil = findViewById(R.id.etHasil);
    }

    /*
    public void isiSpinner () {
        String[] isi = {"Celcius To Reamur", "Celcius To Fahrenheit", "Celcius To Kelvin"};
        ArrayAdapter<String> adapter = new ArrayAdapter<String>(this,android.R.layout.simple_spinner_item,isi);
        spinner.setAdapter(adapter);
    }

    // ada dua cara untuk mengisi pilihan dari Spinner menggunakan public void di atas ini atau menggunakan android::entries="@array/pilihan" di activity_main.xml untuk @array/pilihan mengambil dari values strings.xml
    */
    
    public void btnKonversi(View view) {
        String pilihan = spinner.getSelectedItem().toString();

        if(etNilai.getText().toString().equals("")){
            Toast.makeText(this, "Nilai tidak boleh kosong", Toast.LENGTH_SHORT).show();

        }else {
            // Celsius conversions
            if (pilihan.equals("Celcius To Reamur")) {
                cToR();
            }
            if (pilihan.equals("Celcius To Fahrenheit")) {
                cToF();
            }
            if (pilihan.equals("Celcius To Kelvin")) {
                cToK();
            }
            
            // Reamur conversions
            if (pilihan.equals("Reamur To Celcius")) {
                rToC();
            }
            if (pilihan.equals("Reamur To Fahrenheit")) {
                rToF();
            }
            if (pilihan.equals("Reamur To Kelvin")) {
                rToK();
            }
            
            // Fahrenheit conversions
            if (pilihan.equals("Fahrenheit To Celcius")) {
                fToC();
            }
            if (pilihan.equals("Fahrenheit To Reamur")) {
                fToR();
            }
            if (pilihan.equals("Fahrenheit To Kelvin")) {
                fToK();
            }
            
            // Kelvin conversions
            if (pilihan.equals("Kelvin To Celcius")) {
                kToC();
            }
            if (pilihan.equals("Kelvin To Reamur")) {
                kToR();
            }
            if (pilihan.equals("Kelvin To Fahrenheit")) {
                kToF();
            }
        }
    }

    // Celsius conversions
    public void cToR () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (4.0/5.0) * suhu;
        tvHasil.setText(hasil+"");
    }

    public void cToF () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (9.0/5.0) * suhu + 32;
        tvHasil.setText(hasil+"");
    }

    public void cToK () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = suhu + 273.15;
        tvHasil.setText(hasil+"");
    }

    // Reamur conversions
    public void rToC () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (5.0/4.0) * suhu;
        tvHasil.setText(hasil+"");
    }

    public void rToF () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (9.0/4.0) * suhu + 32;
        tvHasil.setText(hasil+"");
    }

    public void rToK () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (5.0/4.0) * suhu + 273.15;
        tvHasil.setText(hasil+"");
    }

    // Fahrenheit conversions
    public void fToC () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (5.0/9.0) * (suhu - 32);
        tvHasil.setText(hasil+"");
    }

    public void fToR () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (4.0/9.0) * (suhu - 32);
        tvHasil.setText(hasil+"");
    }

    public void fToK () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (5.0/9.0) * (suhu - 32) + 273.15;
        tvHasil.setText(hasil+"");
    }

    // Kelvin conversions
    public void kToC () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = suhu - 273.15;
        tvHasil.setText(hasil+"");
    }

    public void kToR () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (4.0/5.0) * (suhu - 273.15);
        tvHasil.setText(hasil+"");
    }

    public void kToF () {
        double suhu = Double.parseDouble(etNilai.getText().toString());
        double hasil = (9.0/5.0) * (suhu - 273.15) + 32;
        tvHasil.setText(hasil+"");
    }
}