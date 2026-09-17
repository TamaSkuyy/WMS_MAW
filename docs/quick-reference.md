# 🏭 Contekan Cepat — Gudang

## Mitra Adhi Wasana

> Cetak 1 halaman A4, laminating, tempel di meja gudang.  
> 📞 Butuh bantuan? Hubungi: **____________________**

---

## 🔄 ALUR HARIAN

```
┌──────────┬──────────────────────────────────────────────────┐
│  PAGI    │ Buka Delivery Monitor → cek jadwal supplier      │
│  07:30   │ hari ini (slot C1–C6)                            │
├──────────┼──────────────────────────────────────────────────┤
│  SIANG   │ Supplier datang → buka HP / scan QR              │
│          │ → Quick Receive atau Cycle Receive               │
│          │ → input barang + pilih rak                       │
├──────────┼──────────────────────────────────────────────────┤
│  SORE    │ Cek Delivery Monitor → pastikan semua DONE ✅    │
│          │ Proses Shopping jika ada kiriman keluar          │
│          │ Cek stok → catat yang perlu restock             │
└──────────┴──────────────────────────────────────────────────┘
```

---

## 📥 TERIMA BARANG (RECEIVING)

### Quick Receive — Terima Langsung
```
1. Menu: Transactions > Receiving > klik "Quick Receive"
2. Pilih Supplier
3. Isi per barang:
   • Produk   → pilih / scan QR
   • Rak      → pilih rak simpan
   • Quantity → jumlah diterima
4. Klik [Simpan]
```
> ✅ Stok otomatis bertambah. Cycle auto-complete.

### Cycle — Terima Terencana
```
BUAT CYCLE:
  1. Receiving > Tambah Cycle
  2. Pilih Supplier + item barang (qty rencana)
  3. Simpan → status "Draft"

RECEIVE (saat barang datang):
  1. Buka cycle yang "Draft"
  2. Klik [Receive]
  3. Input qty aktual + pilih rak
  4. Klik [Konfirmasi]
```
> ⚠️ Tidak bisa di-undo setelah receive!

---

## 📤 KIRIM BARANG (SHOPPING)

### Alur 2 langkah (dari TAM / pusat)
```
LANGKAH 1 — Input HEADER (line + frame number)
1. Menu: Transactions > Shopping > tombol "1. Input Header"
2. Isi Line/Lokasi Tujuan + Frame Number (bisa banyak baris sekaligus:
   tombol "+ 5 Baris", atau tempel/Ctrl+V beberapa frame — satu per baris)
3. [Simpan Header] → tersimpan sebagai DRAFT (belum ada barang)

LANGKAH 2 — Import BARANG (part + qty)
1. Tombol "2. Import Barang" (atau tombol "Lanjut: Import Barang")
2. Pilih file Excel/CSV: kolom Frame Number | Part Number | Quantity
3. [Start Import] → tunggu sampai "Import selesai"
```
> ⚠️ Kalau file barang memuat frame yang **belum** diinput header-nya, baris itu
> ditolak dengan pesan "Frame ... belum terdaftar di WMS". Input header dulu, atau
> centang **"Buat frame otomatis"** di modal import (untuk kondisi mendadak).

### Alur lama (satu file gabungan)
```
1. Tombol "Import Gabungan" → file berisi Frame Number + Part + Qty sekaligus
2. Atau input manual: "Tambah Shopping"
3. Simpan → "Draft"
4. Klik [Ship] → input qty dikirim → [Konfirmasi]
```
> ℹ️ Dua alur di atas sama-sama aktif. Kalau pusat/TAM mengubah urutan kerja,
> tidak perlu hapus alur yang lain — cukup pakai tombol yang sesuai.
> ⚠️ Pastikan stok cukup! Sistem menolak jika stok kurang.

---

## 🖥️ BACA DELIVERY MONITOR

### Warna Status Supplier
| Warna | Status | Arti | Tindakan |
|-------|--------|------|----------|
| 🟢 | **Done** | Semua selesai | ✅ Aman, nggak perlu apa-apa |
| 🟠 | **Live** | Dalam jam slot | ⏳ Tunggu supplier datang |
| 🔴 | **Alert** | Slot lewat, belum lengkap | 🚨 Cek! Mungkin telat/belum input |
| ⚪ | **Standby** | Tidak ada jadwal | — |

### Baca Tabel Part
```
┌────┬──────────┬────┬───┬───┬──────┐
│ No │ Part No  │ C1 │ C2 │C3 │Status│  ← angka di sel: RENCANA/DITERIMA
├────┼──────────┼────┼───┼───┼──────┤      100/80 = rencana 100, baru 80
│ 1  │ P5188-.. │100 │ — │ — │ ✔ OK │      50/50 = lengkap
│    │          │ 80 │   │   │      │      Tanda "—" = tidak dijadwalkan
│ 2  │ P5162-.. │ —  │ 50│ — │ ✔ OK │
│    │          │    │ 50│   │      │
│ 3  │ P5401-.. │ —  │ — │20 │⚠ -15│  ← kurang 15! perlu ditindak
│    │          │    │   │ 5 │Short │
└────┴──────────┴────┴───┴───┴──────┘
```

### Filter Cepat
- ⚠️ **Shortage/Delayed** → cari part yang belum lengkap
- ✅ **Matched OK** → cek part yang sudah beres
- 📦 **Over-Deliveries** → cek part yang kelebihan

### TV Mode
Klik **[TV Mode]** + **[Rotate ON]** → layar auto-ganti supplier. Cocok untuk TV gudang.

---

## 🔍 CARI STOK

```
Menu: Transactions > Stocks

[Cari nama produk / part number...] 🔍
→ Tampil: Part No, Nama, Rak, Qty
→ Qty kecil ⚠️ = butuh restock
```

---

## 📷 SCAN BARCODE

Semua kolom pencarian (Produk, Stok, Rak, Lokasi, Shift, dll) punya tombol **📷**:

```
1. Klik 📷 di dalam kolom pencarian → izinkan kamera
2. Arahkan kamera ke barcode part
3. Hasil scan otomatis mengisi kolom pencarian (barcode part = part number)
```

- Di form **Tambah/Edit Item Cycle**, tombol 📷 di samping pilihan Produk langsung memilih produk hasil scan (kalau part di luar supplier terpilih → muncul peringatan).
- Di Shopping, tombol scan lokasi/frame/part sudah tersedia masing-masing.
- Scanner USB (keyboard-wedge) tetap jalan: fokuskan kursor ke kolom pencarian, lalu tembakkan barcode.

---

## 🧹 PEMUTIHAN / MENGHAPUS TRANSAKSI

Transaksi yang sudah **Shipped** (Shopping) atau **Completed** (Receiving) tidak
punya tombol hapus per-item — itu disengaja (histori & stok aman). Menghapusnya
lewat halaman **Pemutihan Data** (superadmin):

```
1. Buka: Pemutihan Data (menu superadmin)
2. Isi "Dari Tanggal" dan/atau "Sampai Tanggal"
3. Klik "Pratinjau" → cek jumlah: cycles (diterima) & shoppings (dikirim)
4. Ketik frasa PEMUTIHAN + password Anda → Eksekusi
   (backup database otomatis dibuat lebih dulu)
```

- **Mode tanggal** = hapus riwayat tuntas dalam rentang: cycle `completed`,
  shopping `shipped`/`cripple`/`completed`, stock opname, koreksi, import log,
  notifikasi. **Stok TIDAK diubah** dan antrian/cache tidak disentuh.
- **Mode total** (tanpa tanggal) = semua transaksi dihapus + **stok direset 0**.
- Tanggal acuan: `received_at` cycle & `shipped_at` shopping; kalau data lama
  kosong, dipakai tanggal transaksi (`delivery_date`/`shopping_date`) — jadi data
  lama tetap ikut terhapus.
- Untuk koreksi 1 transaksi (bukan hapus massal), pakai tombol **Koreksi** di
  detail transaksi.

---

## 📄 NAVIGASI TABEL & HAPUS MASSAL

**Navigasi (semua menu):**
- Pilih **Baris** per halaman: 10 (default) / 25 / 50 / 100.
- Tombol **« Pertama**, **Sebelumnya**, **Berikutnya**, **Terakhir »**, dan isi
  **"Ke hal."** + klik **Ke** untuk lompat langsung ke halaman tertentu.

**Hapus massal (khusus SUPERADMIN, di Shopping & Receiving/Cycles):**
```
1. Centang baris yang mau dihapus (atau centang header = pilih semua di halaman itu)
2. Klik "🗑️ Hapus N terpilih"
3. Baca peringatan → centang pernyataan → "Hapus Permanen"
```
- **Semua status** boleh dihapus (draft/shipped/completed).
- **Stok dikoreksi otomatis**: pengiriman shopping dikembalikan ke stok;
  penerimaan cycle dikurangi kembali dari stok. Kalau stok tidak cukup, nilainya
  dijepit 0 dan muncul peringatan jumlah yang tidak bisa dikoreksi.

---

## 🛠️ MASALAH UMUM

| Masalah | Solusi |
|---------|--------|
| Barang sudah diterima tapi stok tidak bertambah | Refresh halaman |
| Cycle tidak muncul di Delivery Monitor | Supplier belum punya jadwal slot → Edit Supplier, centang slot |
| Tidak bisa klik Receive | Cycle harus status "Draft" |
| Salah input jumlah | ❌ Tidak bisa di-undo → lapor admin |
| Nama supplier tidak ada di dropdown | Tambah dulu di Master Data > Suppliers |
| Produk tidak ada | Tambah dulu di Master Data > Products |
| Import Barang: "Frame ... belum terdaftar di WMS" | Input header dulu (tombol "1. Input Header"), atau centang "Buat frame otomatis" saat import |
| Import Barang: "Part Number ... tidak ditemukan" | Produk belum ada di Master Data > Products |
| Import jalan lama / progress diam di "Menunggu antrian" | Queue worker belum jalan → lapor admin (`./deploy-production.sh --check-queue`) |
| Halaman error 502 (Bad Gateway) tiba-tiba | Catat halaman & jam kejadian, lapor admin → jalankan `./deploy-production.sh --diagnose` (lihat `docs/production-server-setup.md` §9.5) |
| Export Excel/PDF: "Data terlalu besar untuk format X" | Persempit rentang tanggal, atau pakai **Export CSV** (tanpa batas, bisa untuk data setahun) |
| Halaman error 500 "Allowed memory size ... exhausted" | Catat halaman apa yang dibuka lalu lapor admin (lihat `docs/production-server-setup.md` §9.4) |

---

## 🏷️ SLOT PENGIRIMAN (C1–C6)

| Slot | Jam | Label |
|------|-----|-------|
| C1 | 07:30 – 09:30 | Pagi 1 |
| C2 | 09:30 – 11:30 | Pagi 2 |
| C3 | 11:30 – 13:30 | Siang 1 |
| C4 | 13:30 – 15:30 | Siang 2 |
| C5 | 15:30 – 17:30 | Sore 1 |
| C6 | 17:30 – 19:30 | Sore 2 |

> Slot menentukan kapan supplier dijadwalkan. Dikelola di Master Data > Jadwal Slot.

---

## ⚙️ PENGATURAN (KHUSUS SUPERADMIN)

Menu: **Setup > Pengaturan** (`/settings`). Berisi switch **on/off** fitur aplikasi —
berlaku untuk semua user.

| Fitur | Fungsi | Default |
| --- | --- | --- |
| **Kolom Model Kendaraan & Suffix — form Shopping** | Menampilkan kolom **Model** dan **Suffix** di daftar produk saat Tambah/Edit Shopping (fitur versi lama). Input **Model Kendaraan (opsional)** + **Suffix (opsional)** di kartu Informasi Shopping tetap ada walau switch ini mati. | ON |

> ℹ️ **Model Kendaraan + Suffix di form Shopping = combobox OPSIONAL yang bisa dicari**
> (boleh dikosongkan). Pilihannya diambil dari **Master Data > Model Kendaraan** *dan* nilai
> yang pernah diinput operator, jadi user tidak perlu hafal — ketik `for` lalu pilih
> `Toyota Fortuner`. Nilai baru tetap boleh diketik.
> Fungsinya dua: (1) menyaring daftar produk (cocok sebagian, tanpa peduli huruf besar/kecil),
> dan (2) **ikut tersimpan** di transaksi (`shoppings.vehicle_model_label` + `vehicle_suffix`),
> tampil di **Detail Shopping**.
> Di **Detail** dan **Reports > Shopping (tabel + Export)** juga ada kolom Model & Suffix
> **per item** yang diturunkan dari produk — jadi satu frame boleh berisi part beberapa model.

Cara pakai: geser switch → **Simpan Pengaturan**. Perubahan langsung terlihat
setelah halaman di-refresh. Kalau bingung kenapa kolom hilang/muncul, cek halaman
ini dulu.

---

## 📱 AKSES CEPAT

| Halaman | URL / Path |
|---------|-----------|
| Dashboard | `/dashboard` |
| Delivery Monitor | `/delivery-monitor` |
| Quick Receive | `/cycles/quick-receive` |
| Receiving (Cycles) | `/cycles` |
| Shopping | `/shoppings` |
| Stocks | `/stocks` |
| Laporan Receiving | `/reports/receiving` |
| Laporan Shopping | `/reports/shopping` |

---

```
                        ╔══════════════════════════════╗
                        ║  SIMPAN CONTEKAN INI! 👆    ║
                        ║  Tempel di area gudang      ║
                        ╚══════════════════════════════╝
```
