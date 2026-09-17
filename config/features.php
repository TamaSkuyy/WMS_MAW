<?php

/**
 * Katalog fitur yang bisa dinyalakan/dimatikan superadmin dari halaman
 * Pengaturan (/settings). Setiap entri jadi satu switch di UI.
 *
 * Cara menambah toggle baru:
 *   1. Tambahkan entri di sini (key, label, description, default).
 *   2. Di kode frontend, baca lewat shared prop `features.<key>`
 *      (atau `Features::enabled('<key>')` di backend).
 *
 * Nilai efektif: setting di DB (`feature.<key>`) kalau sudah pernah diubah,
 * kalau belum pakai `default` di bawah.
 */
return [
    'shopping_vehicle_model_column' => [
        'label' => 'Kolom Model Kendaraan & Suffix — form Shopping',
        'description' => 'Menampilkan kolom Model Kendaraan dan Suffix pada daftar produk, kartu mobile, dan daftar Barang Dipilih di form Tambah/Edit Shopping (fitur versi lama).',
        'default' => true,
    ],
];
