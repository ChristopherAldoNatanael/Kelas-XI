package com.christopheraldoo.sqlitedatabase;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import java.util.List;

public class BarangAdapter extends RecyclerView.Adapter<BarangAdapter.ViewHolder> {

    Context context;
    List<Barang> barangList;
    OnItemActionListener listener;

    // Interface untuk komunikasi dengan MainActivity
    public interface OnItemActionListener {
        void onUbahClicked(Barang barang);

        void onHapusClicked(Barang barang);
    }

    public BarangAdapter(List<Barang> barangList, Context context, OnItemActionListener listener) {
        this.barangList = barangList;
        this.context = context;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup viewGroup, int viewType) {
        View v = LayoutInflater.from(viewGroup.getContext()).inflate(R.layout.item_barang, viewGroup, false);
        return new ViewHolder(v);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        Barang barang = barangList.get(position);

        holder.tvBarang.setText(barang.getBarang());
        holder.tvStok.setText("Stok: " + barang.getStok());

        // Format harga dengan benar
        try {
            double hargaDouble = Double.parseDouble(barang.getHarga());
            int hargaInt = (int) hargaDouble;
            holder.tvHarga.setText("Rp " + hargaInt);
        } catch (NumberFormatException e) {
            holder.tvHarga.setText("Rp " + barang.getHarga());
        }

        holder.tvMenu.setText("⋮");

        holder.tvMenu.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {

                android.app.AlertDialog.Builder builder = new android.app.AlertDialog.Builder(context);
                builder.setTitle("Pilih Aksi untuk " + barang.getBarang());

                String[] options = { "Ubah", "Hapus" };
                builder.setItems(options, new android.content.DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(android.content.DialogInterface dialog, int which) {
                        switch (which) {
                            case 0: // Ubah
                                if (listener != null) {
                                    listener.onUbahClicked(barang);
                                }
                                break;
                            case 1: // Hapus
                                if (listener != null) {
                                    listener.onHapusClicked(barang);
                                }
                                break;
                        }
                    }
                });

                builder.show();
            }
        });
    }

    @Override
    public int getItemCount() {
        return barangList.size();
    }

    public class ViewHolder extends RecyclerView.ViewHolder {

        TextView tvBarang, tvStok, tvHarga, tvMenu;

        public ViewHolder(@NonNull View itemView) {
            super(itemView);

            tvBarang = itemView.findViewById(R.id.tvBarang);
            tvStok = itemView.findViewById(R.id.tvStok);
            tvHarga = itemView.findViewById(R.id.tvHarga);
            tvMenu = itemView.findViewById(R.id.tvMenu);
        }
    }
}
