# Panduan Training Staff — Warehouse Management System

## Mitra Adhi Wasana

> **Tujuan:** Melatih staff gudang baru agar mampu menggunakan sistem dalam 3 hari  
> **Metode:** Teori + Demonstrasi + Praktik + Uji Kompetensi  
> **Durasi:** 3 hari (@ 4-5 jam/hari)  
> **Peralatan:** Laptop/PC dengan akses internet, akun training, data dummy

---

## Daftar Isi

- [Hari 1: Pengenalan & Master Data](#hari-1-pengenalan--master-data)
- [Hari 2: Transaksi Penerimaan](#hari-2-transaksi-penerimaan)
- [Hari 3: Transaksi Lanjutan & Evaluasi](#hari-3-transaksi-lanjutan--evaluasi)
- [Lampiran: Form Kompetensi](#lampiran-form-kompetensi)

---

## Hari 1: Pengenalan & Master Data

### Tujuan
Setelah hari 1, peserta mampu: login, navigasi menu, memahami dashboard, dan menginput data master.

### Sesi 1.1 — Pengenalan (30 menit)

**Teori:**
- Apa itu WMS dan kenapa kita pakai
- Arsitektur sederhana (input → database → laporan)
- Perbedaan Master Data vs Transaksi

**Demonstrasi trainer:**
1. Buka browser → login ke sistem
2. Tunjukkan Dashboard: metric cards, quick links, data ringkasan
3. Navigasi sidebar: Master Data vs Transactions vs Reports

**Praktik peserta:**
```
□ Login dengan akun training yang diberikan
□ Kenali 3 area: sidebar (kiri), header (atas), konten (tengah)
□ Buka Dashboard → klik "Delivery Monitor"
□ Kembali ke Dashboard
```

### Sesi 1.2 — Supplier (45 menit)

**Teori:**
- Supplier = pemasok barang
- Setiap supplier punya: nama, kode, kontak, alamat, jadwal slot

**Demonstrasi trainer:**
1. Buka `Master Data > Suppliers`
2. Tambah supplier baru: isi semua field
3. Edit supplier: tambah jadwal slot (centang C1-C3)
4. Tunjukkan import via Excel

**Praktik peserta:**
```
□ Tambah 3 supplier baru:
  • PT. Sumber Jaya (kode: SJ)
  • CV Maju Bersama (kode: MB, jadwal slot C1 & C2)
  • UD Karya Mandiri (kode: KM, jadwal slot C3, C4, C5)
□ Edit supplier SJ → tambah alamat lengkap
□ Cek daftar supplier di tabel index
```

### Sesi 1.3 — Produk & Kategori (45 menit)

**Teori:**
- Produk = barang yang dikelola
- Part Number = kode unik setiap produk
- Kategori = pengelompokan produk
- Model Kendaraan = mobil yang terkait dengan part

**Demonstrasi trainer:**
1. Tambah Kategori: `Body Parts`, `Electrical`, `Engine`
2. Tambah Model Kendaraan: `Fortuner` (suffix: `VRZ`), `Avanza` (suffix: `G`)
3. Tambah Produk dengan part number, nama, model, supplier, kategori

**Praktik peserta:**
```
□ Tambah 3 kategori
□ Tambah 3 model kendaraan (minimal)
□ Tambah 5 produk dengan data lengkap:
  • Pastikan part number unik (pakai format P<4angka>-<kode>)
  • Pilih supplier, model, dan kategori yang sudah dibuat
□ Coba search & filter di halaman index produk
```

### Sesi 1.4 — Rak (30 menit)

**Teori:**
- Rak = lokasi fisik penyimpanan di gudang
- Format kode: `<zona>-<nomor>` (contoh: A-01)

**Praktik peserta:**
```
□ Tambah 5 rak: A-01, A-02, B-01, B-02, C-01
□ Pastikan zona terisi (A, B, C)
□ Cek daftar rak di tabel
```

### Review Hari 1 (30 menit)
```
□ Trainer: tanya jawab, klarifikasi konsep
□ Peserta: catat pertanyaan / kesulitan
□ PR: hafalkan menu-menu utama
```

---

## Hari 2: Transaksi Penerimaan

### Tujuan
Setelah hari 2, peserta mampu: menerima barang via Quick Receive dan Cycle, memahami status & stok.

### Sesi 2.1 — Konsep Cycle & Slot (30 menit)

**Teori:**
- Cycle = satu kali penerimaan dari supplier
- Slot C1-C6 = jadwal waktu pengiriman
- Bedanya Quick Receive vs Cycle Biasa
- Status cycle: Draft → Receiving → Completed

**Demonstrasi trainer:**
1. Buka Delivery Monitor → tunjukkan slot C1-C6
2. Buka daftar Cycle → tunjukkan kolom status

### Sesi 2.2 — Quick Receive (45 menit)

**Teori:**
- Quick Receive = terima langsung, tanpa rencana
- Stok otomatis bertambah setelah simpan

**Demonstrasi trainer:**
1. `Transactions > Receiving > Quick Receive`
2. Pilih supplier → tambah 3 item → pilih rak → simpan
3. Buka halaman Stocks → tunjukkan stok bertambah

**Praktik peserta:**
```
□ Scenario: Supplier "SJ" kirim 3 produk mendadak
□ Buka Quick Receive, terima:
  • Produk A: 50 pcs → rak A-01
  • Produk B: 30 pcs → rak B-01
  • Produk C: 20 pcs → rak A-02
□ Cek stok di Transactions > Stocks
  → pastikan semua produk muncul dengan qty benar

□ Scenario: Supplier "MB" kirim 1 produk
□ Buka Quick Receive, terima:
  • Produk D: 100 pcs → rak B-02
□ Cek stok lagi → pastikan bertambah
```

### Sesi 2.3 — Cycle Biasa (60 menit)

**Teori:**
- Cycle = rencana dulu, receive kemudian
- Bisa partial receive (diterima bertahap)
- Nomor cycle auto-increment per supplier

**Demonstrasi trainer:**
1. Buat cycle untuk supplier "KM" dengan 3 item
2. Tunjukkan cycle tersimpan sebagai "Draft"
3. Buka cycle → klik Receive
4. Input qty aktual (beberapa dikurangi) → konfirmasi
5. Tunjukkan status berubah jadi "Completed"

**Praktik peserta:**
```
□ Scenario: Besok supplier KM akan kirim
□ Buat Cycle untuk supplier KM:
  • Produk A: rencana 200 pcs
  • Produk E: rencana 150 pcs
□ Simpan → cek status "Draft"

□ Scenario: Hari ini barangnya datang (sebagian)
□ Buka cycle tadi → klik Receive
  • Produk A: diterima 200 pcs → rak A-01
  • Produk E: diterima 130 pcs → rak B-01 (catatan: "20 box kurang")
□ Konfirmasi → cek stok bertambah

□ Buat 2 cycle lagi untuk supplier berbeda (latihan mandiri)
```

### Sesi 2.4 — Delivery Monitor (45 menit)

**Teori:**
- Dashboard real-time untuk pantau pengiriman hari ini
- Status warna: Done (hijau), Live (orange), Alert (merah)
- Parts table: lihat semua part + status per slot

**Praktik peserta:**
```
□ Buka Delivery Monitor
□ Pilih supplier satu per satu → amati status warnanya
□ Cari supplier yang statusnya "Done" → kenapa?
□ Cari supplier yang statusnya "Alert" → apa yang harus dilakukan?
□ Gunakan filter "Shortage/Delayed" di Parts Table
□ Aktifkan TV Mode + Rotate ON → lihat auto-rotate
□ Klik "Ledger" pada salah satu supplier → lihat riwayat cycle
```

### Review Hari 2 (30 menit)
```
□ Tanya jawab: Quick Receive vs Cycle — kapan pakai yang mana?
□ Peserta demonstrasikan: buat cycle + receive (dinilai trainer)
□ PR: pahami perbedaan status di Delivery Monitor
```

---

## Hari 3: Transaksi Lanjutan & Evaluasi

### Tujuan
Setelah hari 3, peserta mampu: melakukan pengeluaran barang, membaca laporan, dan menangani masalah umum.

### Sesi 3.1 — Shopping / Pengeluaran (45 menit)

**Teori:**
- Shopping = barang keluar dari gudang
- Alur: Buat → Draft → Ship → Stok berkurang
- Sistem menolak jika stok tidak cukup

**Demonstrasi trainer:**
1. Buat shopping baru → pilih partner + item
2. Simpan → "Draft"
3. Klik Ship → input qty dikirim → konfirmasi
4. Tunjukkan stok berkurang

**Praktik peserta:**
```
□ Scenario: Ada permintaan kirim ke "Bengkel Jaya"
□ Buat Shopping:
  • Partner: Bengkel Jaya
  • Produk A: 30 pcs
  • Produk B: 15 pcs
□ Simpan → cek status "Draft"

□ Scenario: Barang siap dikirim
□ Buka shopping → klik Ship
  • Produk A: dikirim 30 pcs
  • Produk B: dikirim 10 pcs (sisa 5)
□ Konfirmasi → cek stok berkurang

□ Coba buat shopping dengan qty > stok → harusnya ditolak
```

### Sesi 3.2 — Stok & Laporan (30 menit)

**Praktik peserta:**
```
□ Buka Transactions > Stocks
□ Cari produk spesifik → cek qty & rak
□ Catat 3 produk dengan stok terendah

□ Buka Reports > Receiving Report
□ Filter by: supplier, tanggal, produk
□ Export ke Excel → buka filenya

□ Buka Reports > Shopping Report
□ Export ke PDF
```

### Sesi 3.3 — Troubleshooting (30 menit)

**Teori + Praktik:**
```
□ Skenario 1: "Stok tidak bertambah setelah receive"
  → Refresh halaman, cek di Stocks

□ Skenario 2: "Supplier tidak muncul di Delivery Monitor"
  → Edit supplier, centang jadwal slot

□ Skenario 3: "Tidak bisa klik Receive"
  → Cek status cycle (harus Draft)

□ Skenario 4: "Salah input jumlah receive"
  → Diskusi: apa yang harus dilakukan? (lapor admin)

□ Skenario 5: "Produk tidak ada di dropdown"
  → Tambah dulu di Master Data > Products
```

### Sesi 3.4 — Simulasi Akhir (60 menit)

**Simulasi 1 Hari Kerja Penuh:**

```
PAGI (15 menit)
□ Buka Dashboard → cek ringkasan
□ Buka Delivery Monitor → catat supplier yang dijadwalkan hari ini
□ Identifikasi: siapa yang Live? siapa yang Standby?

SIANG (30 menit)  
□ Supplier SJ datang → terima via Quick Receive (5 produk)
□ Supplier MB datang → buka cycle draft kemarin → receive
□ Supplier KM telat → cek Delivery Monitor → status "Alert"
□ Cek stok semua produk yang baru diterima

SORE (15 menit)
□ Ada permintaan kirim → buat Shopping + Ship
□ Buka Delivery Monitor → pastikan semua "Done" atau catat yang "Alert"
□ Export laporan harian (receiving + shopping)
```

### Sesi 3.5 — Uji Kompetensi (30 menit)

Peserta harus menyelesaikan tugas berikut dalam 30 menit **tanpa bantuan trainer**:

```
□ Tugas 1: Tambah 1 supplier baru + 2 produk baru (10 menit)
□ Tugas 2: Terima barang via Quick Receive (5 menit)  
□ Tugas 3: Buat cycle draft + receive sebagian (10 menit)
□ Tugas 4: Cek Delivery Monitor, catat supplier "Alert" (5 menit)
```

**Kriteria lulus:** Semua tugas selesai dalam waktu, data benar, stok sesuai.

---

## Lampiran: Form Kompetensi

### Checklist Kompetensi Peserta

**Nama:** ______________ | **Tanggal:** ______________ | **Trainer:** ______________

| # | Kompetensi | ✅/❌ | Catatan |
|---|-----------|-------|--------|
| 1 | Login & navigasi menu | | |
| 2 | Tambah supplier (lengkap) | | |
| 3 | Edit supplier + jadwal slot | | |
| 4 | Tambah produk (lengkap) | | |
| 5 | Import produk via Excel | | |
| 6 | Tambah rak | | |
| 7 | Quick Receive (3+ item) | | |
| 8 | Buat Cycle draft | | |
| 9 | Receive Cycle (partial) | | |
| 10 | Cek stok setelah receive | | |
| 11 | Baca Delivery Monitor (status) | | |
| 12 | Gunakan filter Parts Table | | |
| 13 | Buat Shopping + Ship | | |
| 14 | Export laporan ke Excel | | |
| 15 | Tangani 3 skenario error | | |

**Hasil:** □ LULUS □ Perlu Latihan Ulang  
**Tanda Tangan Trainer:** ______________

---

> 📅 Panduan ini diperbarui: Juli 2026  
> 📞 Pertanyaan? Hubungi trainer / admin sistem
