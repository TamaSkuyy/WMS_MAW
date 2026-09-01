# Dokumen UAT — User Acceptance Test

## Warehouse Management System (WMS) — Mitra Adhi Wasana

---

## 1. Informasi Umum

| Item | Keterangan |
|------|-----------|
| **Nama Aplikasi** | Warehouse Management System (WMS) — Mitra Adhi Wasana |
| **Modul yang Diuji** | Autentikasi, Dashboard, Master Data, Transaksi (Penerimaan & Pengeluaran), Stok, Laporan, Delivery Monitor, TV Dashboard, Manajemen Sistem |
| **Versi Aplikasi** | [isi versi / tanggal build] |
| **Tanggal Pengujian** | [isi tanggal] |
| **Lokasi / URL** | [isi URL aplikasi] |
| **Penyusun Dokumen** | [isi nama developer / tim IT] |
| **Penguji (User)** | [isi nama user / PIC user] |
| **Pendamping Penguji** | [isi nama developer pendamping] |
| **Status Dokumen** | [ ] Draft &nbsp;&nbsp; [ ] Siap Uji &nbsp;&nbsp; [ ] Final |

---

## 2. Riwayat Revisi

| No | Tanggal | Versi | Deskripsi Perubahan | Disusun oleh |
|----|---------|-------|----------------------|--------------|
| 1  | [isi]   | 1.0   | Dokumen awal UAT      | [isi]        |
|    |         |       |                      |              |

---

## 3. Tujuan Dokumen

Dokumen ini merupakan **lembar pengujian penerimaan pengguna (User Acceptance Test / UAT)** yang digunakan untuk:

1. Memverifikasi bahwa seluruh fungsi aplikasi WMS berjalan sesuai kebutuhan dan alur kerja gudang.
2. Mendapatkan **persetujuan resmi dari user/pihak klien** bahwa aplikasi **diterima** (accepted) untuk digunakan (go-live).
3. Mencatat setiap **temuan/defect** yang harus diperbaiki sebelum atau setelah go-live, beserta prioritasnya.
4. Menjadi **dokumen acuan** kesepakatan antara tim pengembang dan user atas hasil pengujian.

---

## 4. Ruang Lingkup Pengujian

Pengujian mencakup modul-modul berikut:

| Kode Modul | Modul | Keterangan |
|------------|-------|-----------|
| A | Autentikasi & Profil | Login, logout, verifikasi email, ubah profil & kata sandi |
| B | Dashboard | Ringkasan metrik, stok menipis, cycle/shopping pending, performa operator |
| C | Master Data — Supplier | CRUD, alamat, jadwal slot pengiriman, import/export |
| D | Master Data — Produk | CRUD, scan QR, stok min/maks, rak default, import/export |
| E | Master Data — Gudang | Rak, Model Kendaraan, Kategori Produk |
| F | Master Data — Organisasi | Jabatan, Lokasi Kerja, Departemen, Karyawan, Shift |
| G | Master Data — Pengiriman | Lokasi Shopping, Slot Pengiriman (C1–C6) |
| H | Transaksi — Penerimaan | Quick Receive, Cycle, Receive, import/export cycle |
| I | Transaksi — Pengeluaran | Shopping, Ship, Bulk Ship, import/export |
| J | Stok | Lihat & cari stok, indikator stok menipis |
| K | Laporan | Receiving, Shopping, Supplier Performance + export |
| L | Monitoring | Delivery Monitor (TV), TV Dashboard |
| M | Sistem | User, Role, Permission, Menu, Notifikasi, Import Status |

> ⚠️ **Di luar lingkup:** pengujian performa beban (load test), uji keamanan penetrasi, dan migrasi data historis (jika tidak diminta).

---

## 5. Lingkungan Pengujian

### 5.1 Perangkat & Browser

| Item | Spesifikasi |
|------|------------|
| Perangkat | [isi, misal: PC / Laptop / Tablet] |
| Sistem Operasi | [isi, misal: Windows 11 / Android] |
| Browser | Chrome (rekomendasi), Edge, Firefox — versi terbaru |
| Resolusi Layar | [isi, misal: 1920×1080] |
| Jaringan | [isi, misal: LAN kantor / WiFi] |

### 5.2 Akun Uji

Siapkan minimal 1 akun untuk tiap peran berikut (sesuai kebutuhan user):

| Peran | Hak Akses | Kebutuhan Akun |
|-------|-----------|----------------|
| **Super Admin** | Semua menu termasuk Setup (User, Role, Permission, Menu) | [isi email] |
| **Admin** | Master data + Transaksi + Laporan | [isi email] |
| **User / Operator** | Transaksi + Laporan | [isi email] |

### 5.3 Data Uji yang Disarankan

- 2–3 **supplier** contoh (misal: DWA, MMM) lengkap dengan jadwal slot C1–C6.
- 3–5 **produk** contoh (part number, nama, model kendaraan, kategori, stok min/maks).
- 2–3 **rak** contoh beserta kapasitasnya.
- **Cycle** draft yang belum di-receive dan **Shopping** draft yang belum di-ship.
- File Excel contoh untuk menguji **import** (template sudah disediakan aplikasi).

---

## 6. Metodologi & Kriteria Penilaian

### 6.1 Cara Pengisian

1. Penguji menjalankan **langkah pengujian** pada kolom "Langkah Pengujian".
2. Bandingkan hasil aktual dengan kolom **"Hasil yang Diharapkan"**.
3. Beri tanda pada kolom **Status**: ✅ **Lulus** / ❌ **Gagal** / ➖ **Tidak Diuji (N/A)**.
4. Tulis **hasil aktual** dan **catatan** singkat pada kolom yang tersedia.
5. Temuan yang **Gagal** dicatat juga ke **Log Temuan (Bagian 8)**.

### 6.2 Definisi Status

| Status | Arti |
|--------|------|
| ✅ **Lulus (Pass)** | Fungsi berjalan sesuai hasil yang diharapkan |
| ❌ **Gagal (Fail)** | Fungsi tidak sesuai harapan — buat entri di Log Temuan |
| ➖ **Tidak Diuji (N/A)** | Tidak diuji karena di luar kebutuhan / tidak tersedia datanya |

### 6.3 Prioritas Temuan (Severity)

| Prioritas | Definisi |
|-----------|----------|
| 🔴 **Critical** | Sistem error/berhenti berfungsi, data korup, atau fungsi utama tidak berjalan — harus diperbaiki sebelum go-live |
| 🟠 **Major** | Fungsi berjalan tetapi tidak sesuai kebutuhan / hasil salah — harus diperbaiki sebelum go-live atau disepakati jadwal perbaikannya |
| 🟡 **Minor** | Penyimpangan kecil (tampilan, teks, kosmetik) yang tidak menghambat proses |
| ⚪ **Enhancement** | Saran perbaikan/pengembangan baru di luar kesepakatan awal |

---

## 7. Matriks Test Case

> **Petunjuk:** Isi kolom **Hasil Aktual**, **Status**, dan **Catatan** selama pengujian.

### A. Autentikasi & Profil

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| A-01 | Buka URL aplikasi tanpa login | Sistem menampilkan halaman **Login** | | | |
| A-02 | Login dengan email & password yang benar | Berhasil masuk dan diarahkan ke halaman **Dashboard** | | | |
| A-03 | Login dengan password salah | Muncul pesan error "kredensial tidak cocok", tidak masuk ke sistem | | | |
| A-04 | Klik "Login" dengan form kosong | Muncul pesan validasi "email wajib diisi" dan "password wajib diisi" | | | |
| A-05 | Klik menu **Logout** | Keluar dari sistem dan kembali ke halaman Login; akses halaman internal kembali ditolak | | | |
| A-06 | Klik menu **Profile** | Halaman profil menampilkan nama & email user yang login | | | |
| A-07 | Ubah nama profil lalu **Simpan** | Nama berubah dan tersimpan | | | |
| A-08 | Ubah kata sandi (password lama + baru) lalu simpan | Kata sandi berhasil diganti; login berikutnya dengan sandi baru berhasil | | | |
| A-09 | Buka halaman Dashboard saat email belum diverifikasi | Muncul halaman/peringatan **verifikasi email** | | | |

### B. Dashboard

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| B-01 | Buka halaman Dashboard | Kartu metrik tampil: Total Produk, Total Stok, Stok Menipis, Stok Berlebih, Cycle Pending, Shopping Pending, Cycle Selesai Hari Ini | | | |
| B-02 | Cek daftar **Stok Menipis** | Menampilkan produk dengan qty < stok minimum (part number, nama, rak, qty, min stok) | | | |
| B-03 | Cek daftar **Cycle Menunggu** | Menampilkan cycle draft terbaru (supplier, nomor cycle, jumlah item, tanggal) | | | |
| B-04 | Cek daftar **Shopping Siap Kirim** | Menampilkan shopping draft terbaru (lokasi tujuan, tanggal, jumlah item) | | | |
| B-05 | Cek bagian **Peringatan Kapasitas Rak** | Rak yang penuh (≥100%) dan hampir penuh (≥80%) tampil dengan persentase pemakaian | | | |
| B-06 | Cek bagian **Performa Operator** | Menampilkan top operator receiving & shopping hari ini (nama & qty) | | | |
| B-07 | Klik shortcut **Delivery Monitor** di dashboard | Terbuka halaman Delivery Monitor | | | |

### C. Master Data — Supplier

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| C-01 | Buka menu **Master Data > Suppliers** | Daftar supplier tampil; fitur pencarian & paginasi berfungsi | | | |
| C-02 | Klik **Tambah Supplier**, isi nama, kontak, email, telepon, lalu **Simpan** | Supplier tersimpan; kode otomatis dibuat jika dikosongkan | | | |
| C-03 | Tambah supplier dengan **kode yang sudah dipakai** | Muncul pesan error validasi kode harus unik | | | |
| C-04 | Klik **Detail** supplier | Menampilkan info lengkap termasuk alamat & jadwal pengiriman | | | |
| C-05 | Klik **Edit** supplier, ubah data lalu **Simpan** | Perubahan tersimpan dengan benar | | | |
| C-06 | Pada edit supplier, centang **jadwal slot** (misal C1 & C3) lalu simpan | Jadwal tersimpan; supplier muncul di Delivery Monitor pada slot tersebut | | | |
| C-07 | Tambahkan **alamat** supplier (jalan, kota, provinsi, kode pos, negara) | Alamat tersimpan dan tampil di detail supplier | | | |
| C-08 | Klik **Hapus** supplier (yang tidak punya transaksi) | Muncul konfirmasi; setelah disetujui supplier terhapus dari daftar | | | |
| C-09 | Klik **Download Template Import** supplier | File Excel template terunduh dengan format kolom yang benar | | | |
| C-10 | Import supplier dari Excel (upload → preview → konfirmasi) | Data tervalidasi di preview; setelah konfirmasi supplier masuk daftar | | | |
| C-11 | Klik **Export** supplier | File Excel berisi data supplier terunduh | | | |

### D. Master Data — Produk

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| D-01 | Buka menu **Master Data > Products** | Daftar produk tampil; pencarian (part number/nama) & paginasi berfungsi | | | |
| D-02 | Klik **Tambah Produk**, isi part number, nama, model kendaraan, supplier, kategori, satuan, lalu **Simpan** | Produk tersimpan dan tampil di daftar | | | |
| D-03 | Tambah produk dengan **part number duplikat** | Muncul pesan error part number harus unik | | | |
| D-04 | Isi **stok minimum & maksimum** produk | Nilai tersimpan; produk muncul di daftar Stok Menipis saat qty < min | | | |
| D-05 | Tentukan **rak default** pada produk | Rak default tersimpan dan terbawa saat transaksi | | | |
| D-06 | Gunakan **scan QR** untuk input part number | Kamera terbuka; hasil scan terisi otomatis pada kolom part number | | | |
| D-07 | Klik **Edit** produk, ubah data lalu **Simpan** | Perubahan tersimpan dengan benar | | | |
| D-08 | Klik **Hapus** produk (yang tidak dipakai transaksi) | Produk terhapus setelah konfirmasi | | | |
| D-09 | Klik **Download Template** & import produk dari Excel | Template terunduh; data produk hasil import tampil di daftar | | | |
| D-10 | Klik **Export** produk | File Excel berisi data produk terunduh | | | |

### E. Master Data — Gudang (Rak, Model Kendaraan, Kategori Produk)

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| E-01 | Buka **Racks**, tambah rak (kode + zona + kapasitas) | Rak tersimpan; tampil dengan kapasitasnya | | | |
| E-02 | Cek **Peringatan Kapasitas** rak di dashboard sesuai isi stok rak | Persentase pemakaian rak sesuai perhitungan isi stok | | | |
| E-03 | Tambah **Model Kendaraan** (contoh: Fortuner, Avanza) | Merek otomatis terisi **Toyota**; suffix opsional | | | |
| E-04 | Edit / hapus **Model Kendaraan** | Perubahan/hapus berfungsi dengan konfirmasi | | | |
| E-05 | Tambah **Kategori Produk** (contoh: Body Parts, Electrical) | Kategori tersimpan dan tersedia di dropdown produk | | | |
| E-06 | Export / import **Rak, Model Kendaraan, Kategori** | Template & hasil import/export berfungsi | | | |

### F. Master Data — Organisasi (Jabatan, Lokasi Kerja, Departemen, Karyawan, Shift)

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| F-01 | Tambah **Jabatan (Job Position)** | Jabatan tersimpan; jika ada relasi role, role terhubung dengan benar | | | |
| F-02 | Tambah **Lokasi Kerja** & **Departemen** | Keduanya tersimpan dan tampil di dropdown karyawan | | | |
| F-03 | Buka **Karyawan (Employees)**, tambah karyawan (nama, jabatan, departemen, lokasi, shift) | Karyawan tersimpan | | | |
| F-04 | Pada karyawan, klik **Generate User** | Akun user otomatis dibuat untuk karyawan tsb. dan bisa login | | | |
| F-05 | Edit / hapus **Karyawan** | Berfungsi dengan konfirmasi | | | |
| F-06 | Tambah **Shift** (nama, kode, jam mulai–selesai, status) | Shift tersimpan dan tampil | | | |
| F-07 | Export / import **data organisasi & karyawan** | Template & hasil import/export berfungsi | | | |

### G. Master Data — Pengiriman (Lokasi Shopping, Slot Pengiriman)

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| G-01 | Tambah **Lokasi Shopping** (nama + barcode) | Lokasi tersimpan; barcode tampil | | | |
| G-02 | Edit / hapus **Lokasi Shopping** | Berfungsi dengan konfirmasi | | | |
| G-03 | Buka **Slot Pengiriman** (C1–C6) | 6 slot tampil dengan jam default (C1: 07:30–09:30 … C6: 17:30–19:30) | | | |
| G-04 | Ubah jam salah satu slot lalu **Simpan** | Jam slot berubah dan dipakai pada perhitungan status Delivery Monitor | | | |

### H. Transaksi — Penerimaan (Quick Receive & Cycle)

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| H-01 | Buka **Transactions > Receiving** | Daftar cycle tampil dengan filter status & pencarian | | | |
| H-02 | Klik **Quick Receive**, pilih supplier, tambah produk + rak + qty, lalu **Simpan** | Cycle otomatis dibuat berstatus **Completed**; stok produk bertambah di rak yang dipilih | | | |
| H-03 | Buka **Stocks**, cek produk dari Quick Receive | Qty produk bertambah sesuai input | | | |
| H-04 | Klik **Tambah Cycle**, pilih supplier, tambah item (produk + qty rencana), lalu **Simpan** | Cycle tersimpan berstatus **Draft**; nomor cycle otomatis berurutan per supplier (C1, C2, …) | | | |
| H-05 | Buka detail cycle draft | Tampil supplier, tanggal & slot pengiriman otomatis, daftar item, dan catatan | | | |
| H-06 | Klik **Receive** pada cycle draft; isi qty diterima sesuai rencana + pilih rak, lalu **Konfirmasi** | Status cycle menjadi **Completed**; stok bertambah; tanggal/jam penerimaan tercatat | | | |
| H-07 | Receive dengan qty diterima **kurang** dari rencana | Cycle tetap selesai dengan status **Shortage** (kekurangan tercatat) | | | |
| H-08 | Receive dengan qty diterima **lebih** dari rencana | Cycle selesai dengan status **Over** (kelebihan tercatat) | | | |
| H-09 | Receive **sebagian** item (tidak semua item sekaligus) | Cycle berstatus **Receiving**, bisa dilanjutkan receive berikutnya sampai lengkap | | | |
| H-10 | Coba **Receive** ulang cycle yang sudah Completed | Sistem **menolak**; cycle yang sudah selesai tidak bisa diubah | | | |
| H-11 | Edit cycle **draft** (ubah qty rencana / tambah item) | Perubahan tersimpan selama masih draft | | | |
| H-12 | Hapus cycle **draft** | Cycle terhapus setelah konfirmasi | | | |
| H-13 | Import cycle dari Excel (template → preview → konfirmasi) | Data cycle & item hasil import tampil di daftar | | | |
| H-14 | Export cycle | File Excel data cycle terunduh | | | |
| H-15 | Pada cycle, tentukan **PIC/Carrier** (petugas penerima) | PIC tersimpan dan tampil di detail cycle | | | |

### I. Transaksi — Pengeluaran (Shopping & Ship)

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| I-01 | Buka **Transactions > Shopping** | Daftar shopping tampil dengan filter status & pencarian lokasi | | | |
| I-02 | Klik **Tambah Shopping**, pilih lokasi tujuan, tanggal, tambah item (produk + qty), lalu **Simpan** | Shopping tersimpan berstatus **Draft** | | | |
| I-03 | Klik **Ship** pada shopping draft; verifikasi qty kirim lalu **Konfirmasi** | Status menjadi **Shipped**; stok berkurang; tanggal & petugas kirim tercatat | | | |
| I-04 | Ship dengan qty **melebihi stok tersedia** | Sistem **menolak** dengan pesan stok tidak mencukupi | | | |
| I-05 | Tandai shopping sebagai **Cripple** (barang tidak lengkap) lalu ship | Status menjadi **Cripple** (bukan Shipped) tetapi stok tetap berkurang | | | |
| I-06 | Isi **No. Rangka (Frame Number)** pada shopping | Nomor rangka tersimpan dan tampil di daftar | | | |
| I-07 | Gunakan **Kirim Massal (Bulk Ship)**: pilih beberapa shopping draft lalu kirim | Semua shopping terpilih terproses sekaligus dengan status sesuai | | | |
| I-08 | Edit shopping **draft** | Perubahan tersimpan selama masih draft | | | |
| I-09 | Hapus shopping **draft** | Shopping terhapus setelah konfirmasi | | | |
| I-10 | Import shopping dari Excel | Data shopping hasil import tampil di daftar | | | |

### J. Stok

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| J-01 | Buka **Transactions > Stocks** | Daftar stok tampil (part number, nama produk, rak, qty) | | | |
| J-02 | Cari stok berdasarkan nama produk / part number | Hasil pencarian sesuai kata kunci | | | |
| J-03 | Cek produk dengan qty di bawah minimum | Muncul indikator/penanda **stok rendah** | | | |
| J-04 | Cek informasi rak pada tiap baris stok | Rak penyimpanan tampil sesuai data | | | |

### K. Laporan

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| K-01 | Buka **Receiving Report**, atur filter tanggal | Data penerimaan sesuai rentang tanggal tampil; ringkasan (total transaksi, total qty, produk unik) tampil | | | |
| K-02 | Filter Receiving Report per **supplier** dan **status** | Data tersaring sesuai pilihan | | | |
| K-03 | Klik **Export Excel** pada Receiving Report | File .xlsx terunduh dan isinya sesuai data di layar | | | |
| K-04 | Klik **Export PDF** pada Receiving Report | File .pdf terunduh dan isinya sesuai data di layar | | | |
| K-05 | Buka **Shopping Report**, atur filter tanggal & partner | Data pengeluaran sesuai filter tampil | | | |
| K-06 | Klik **Export** pada Shopping Report | File terunduh sesuai format yang dipilih | | | |
| K-07 | Buka **Supplier Performance** | Metrik tampil: total cycle, **on-time rate (OTIF)**, item tidak lengkap, rekap per supplier | | | |
| K-08 | Filter Supplier Performance per supplier & periode | Data & metrik tersaring sesuai pilihan | | | |
| K-09 | Klik **Export** pada Supplier Performance | File laporan terunduh sesuai format | | | |

### L. Monitoring — Delivery Monitor & TV Dashboard

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| L-01 | Buka halaman **Delivery Monitor** (`/delivery-monitor`) **tanpa login** | Halaman terbuka (publik, khusus layar TV) | | | |
| L-02 | Pilih **supplier** pada dropdown | Panel & tabel berganti ke supplier terpilih | | | |
| L-03 | Ganti **tanggal** monitoring | Data menyesuaikan tanggal yang dipilih (default hari ini) | | | |
| L-04 | Aktifkan **TV Mode** | Tampilan memenuhi layar (sidebar hilang) | | | |
| L-05 | Aktifkan **Rotate ON** | Supplier berganti otomatis setiap beberapa detik | | | |
| L-06 | Cek **status warna** tiap supplier | Hijau (Done), Oranye (Live), Merah (Alert), Abu-abu (Standby) sesuai kondisi slot | | | |
| L-07 | Cek **progress bar** per slot (rencana vs diterima) | Persentase sesuai perbandingan qty diterima/rencana | | | |
| L-08 | Klik **Buka Ledger** pada supplier | Riwayat lengkap cycle supplier tampil | | | |
| L-09 | Gunakan **filter Parts Table** (All / Active Planned / Shortage / Matched / Over) | Daftar part tersaring sesuai pilihan | | | |
| L-10 | Buka **TV Dashboard** (`/tv-dashboard`) | Slide berganti otomatis (stok, aktivitas hari ini) | | | |

### M. Sistem — User, Role, Permission, Menu, Notifikasi

| ID | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status | Catatan |
|----|-------------------|----------------------|--------------|--------|---------|
| M-01 | Buka **Setup > Users** | Daftar user tampil (hanya Super Admin) | | | |
| M-02 | Tambah **user baru**, tetapkan **role**, lalu simpan | User tersimpan; bisa login dengan akun tsb. | | | |
| M-03 | Ubah **role** user lalu simpan | Hak akses user berubah sesuai role baru | | | |
| M-04 | Hapus user | User terhapus setelah konfirmasi | | | |
| M-05 | Buka **Setup > Roles**, tambah/edit role | Role tersimpan | | | |
| M-06 | Atur **permission** pada role (centang/lepas hak akses) | Hak akses tersimpan sesuai centangan | | | |
| M-07 | Login sebagai user **tanpa** permission menu tertentu, lalu buka menu tsb. | Muncul **403 / akses ditolak**, menu tidak tampil | | | |
| M-08 | Buka **Setup > Permissions** | Daftar permission tampil; tambah/edit/hapus berfungsi | | | |
| M-09 | Buka **Setup > Menu Management** | Daftar menu sidebar tampil; tambah/edit/urutkan menu berfungsi | | | |
| M-10 | Kirim notifikasi uji (**Test Broadcast**) sebagai Super Admin | Notifikasi realtime muncul di lonceng notifikasi tanpa refresh | | | |
| M-11 | Klik notifikasi → **Tandai dibaca**; lalu **Tandai semua dibaca** | Status notifikasi berubah menjadi dibaca | | | |
| M-12 | Buka **Import Status** | Riwayat proses import tampil beserta status sukses/gagal | | | |
| M-13 | Import user dari Excel (template) & **Export** user | Import & export user berfungsi | | | |

---

## 8. Log Temuan (Defect Log)

> Catat setiap hasil pengujian yang **Gagal** di sini. Kolom **Status** diisi: Open / In Progress / Fixed / Verified / Closed.

| No | Tanggal | ID Test | Modul | Deskripsi Temuan | Langkah Reproduksi | Prioritas | Status | Penanggung Jawab | Catatan |
|----|---------|---------|-------|------------------|--------------------|-----------|--------|------------------|---------|
| 1  |         |         |       |                  |                    |           |        |                  |         |
| 2  |         |         |       |                  |                    |           |        |                  |         |
| 3  |         |         |       |                  |                    |           |        |                  |         |

---

## 9. Ringkasan Hasil UAT

| Modul | Jumlah Test Case | ✅ Lulus | ❌ Gagal | ➖ Tidak Diuji | Keterangan |
|-------|------------------|----------|---------|---------------|------------|
| A. Autentikasi & Profil | | | | | |
| B. Dashboard | | | | | |
| C. Master Data — Supplier | | | | | |
| D. Master Data — Produk | | | | | |
| E. Master Data — Gudang | | | | | |
| F. Master Data — Organisasi | | | | | |
| G. Master Data — Pengiriman | | | | | |
| H. Transaksi — Penerimaan | | | | | |
| I. Transaksi — Pengeluaran | | | | | |
| J. Stok | | | | | |
| K. Laporan | | | | | |
| L. Monitoring | | | | | |
| M. Sistem | | | | | |
| **TOTAL** | | | | | |

**Kesimpulan:**
- [ ] ✅ **DITERIMA** — Seluruh test case lulus (atau temuan prioritas Critical/Major sudah diperbaiki & diverifikasi). Aplikasi **siap go-live**.
- [ ] ⏸️ **DITERIMA DENGAN CATATAN** — Lulus dengan catatan; temuan minor/enhancement akan ditindaklanjuti setelah go-live.
- [ ] ❌ **DITOLAK** — Masih terdapat temuan Critical/Major yang belum diperbaiki.

---

## 10. Pernyataan Penerimaan (Sign-off)

Dengan menandatangani dokumen ini, pihak **pengguna (user/klien)** menyatakan telah melakukan pengujian penerimaan terhadap aplikasi **Warehouse Management System (WMS) — Mitra Adhi Wasana** dan menyetujui hasil pengujian sebagaimana tercantum dalam dokumen ini.

| | Nama | Jabatan | Tanda Tangan | Tanggal |
|---|------|---------|--------------|---------|
| **Penguji (User)** | | | | |
| **PIC User / Kepala Gudang** | | | | |
| **Developer / Tim IT** | | | | |

> ⚠️ **Catatan:** Dokumen ini merupakan template yang siap diisi. Bagian yang bertanda `[isi ...]` dan kolom kosong perlu dilengkapi sebelum diserahkan ke user. Setelah dokumen final, disarankan dikonversi ke **PDF/Word** untuk ditandatangani.

---

*Dokumen ini disusun oleh tim pengembang WMS — Mitra Adhi Wasana.*
