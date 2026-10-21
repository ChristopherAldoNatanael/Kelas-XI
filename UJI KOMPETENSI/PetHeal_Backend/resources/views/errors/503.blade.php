@extends('errors.layout')

@section('title', 'Sedang dalam pemeliharaan')
@section('code', '503')
@section('heading', 'Kami sedang melakukan pemeliharaan')
@section('message', 'PetHeal sedang diperbarui agar tetap cepat dan aman. Semua jadwal dan data Anda tetap tersimpan. Silakan kembali beberapa menit lagi.')
@section('status', 'Pemeliharaan terjadwal')
@section('hint', 'Jika Anda admin, hubungi tim teknis sebelum membuka kembali layanan.')
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Muat ulang</a>
@endsection
