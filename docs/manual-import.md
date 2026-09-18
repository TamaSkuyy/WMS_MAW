# 📥 Manual Book — Import Data (WMS MAW)

**Untuk:** Operator gudang, Leader, Admin / Superadmin
**Versi dokumen:** September 2026 (mengikuti fitur: alur 2 langkah Shopping, Kirim Massal "sekali klik", Stock Opname, Data Order TAM)

> Dokumen ini panduan **semua fitur import** di aplikasi: dari mana tombolnya, format
> filenya, apa yang terjadi setelah di-klik, arti angka & pesan error, sampai
> langkah setelah import. Contekan harian tetap ada di `docs/quick-reference.md`.

---

## 1. Daftar Import yang Tersedia

| # | Modul | Menu & Tombol | File | Hasil | Izin yang dibutuhkan |
|---|-------|---------------|------|-------|----------------------|
| 1 | **Data Order supplier (TAM)** | Transactions > Cycles > **Import Data Order** | Excel/CSV lebar (sheet `EMAIL`, blok `CYCLE 1..N`) | Cycle **draft** per supplier × gelombang | `create cycles` |
| 2 | **Barang Shopping (alur 2 langkah)** | Transactions > Shopping > **2. Import Barang** | Frame Number + Part Number + Quantity | Barang masuk ke frame **draft** yang sudah ada | `import shoppings` 🔒 |
| 3 | **Header Shopping (file)** | *Tombol disembunyikan* (`shoppings/import-headers`) | Line + Frame Number | Frame **draft** (line & frame number) | `import shoppings` 🔒 |
| 4 | **Shopping gabungan (alur lama)** | Transactions > Shopping > **Import Gabungan** | Frame + Part + Qty dalam satu file | Frame draft **beserta** barangnya | `import shoppings` 🔒 |
| 5 | **Cycle (generic)** | Transactions > Cycles > Import | Cycle Number, Supplier, Tanggal, Part, Qty | Cycle draft | `import cycles` |
| 6 | **Produk** | Master Data > Products > Import | PartNumber, Nama, Merek, Model, Supplier, Kategori, Satuan, … | Produk baru | `create products` |
| 7 | **Model Kendaraan, Kategori, Rak, Supplier** | Master Data > masing-masing > Import | lihat §5.2 | Master data baru | `create …` |
| 8 | **User, Karyawan, Departemen, Jabatan, Shift, Lokasi Kerja** | menu terkait > Import | lihat §5.3 | Master data baru | `create …` |
| 9 | **Stock Opname (unggah hasil hitung)** | Transactions > Stock Opname > Upload hasil | template dari aplikasi (Part No, Part Name, RAK, SAP, **Qty Opname**) | Stok disesuaikan + riwayat opname | `stock opname` |

**Download template** selalu tersedia di dalam modal import (tautan **CSV** / **XLSX**) —
pakai template itu supaya nama kolomnya cocok. Untuk Stock Opname, template diunduh
dari halaman *Stock Opname* karena **sudah terisi stok sistem saat ini**.

> 🔒 **Khusus fitur import Shopping (baris 2–4):** tombol **2. Import Barang** dan
> **Import Gabungan** hanya muncul untuk **Leader ke atas** (permission
> `import shoppings`). Operator tetap bisa *1. Input Header* manual dan menambah
> shopping, tetapi tidak bisa memakai fitur import. Kalau tombolnya tidak muncul,
> minta Leader/Superadmin yang mengunggah file.

---

## 2. Cara Kerja Import (sama untuk semua modul)

### 2.1 Tiga langkah di layar

```
1. UPLOAD        2. MAP COLUMNS          3. IMPORTING → HASIL
┌──────────┐    ┌──────────────────┐    ┌───────────────────────┐
│ Drop file│ →  │ System Field  ←  │ →  │ 120 / 120 rows        │
│ / browse │    │ File Column      │    │ ✅ 118 berhasil       │
│          │    │ (otomatis nyocokin)   │ ⏭ 2 duplikat dilewati │
└──────────┘    └──────────────────┘    └───────────────────────┘
```

1. **Upload** — pilih/lepas file (`.xlsx`, `.xls`, `.csv`, **maks 10 MB**).
   Baris pertama file **wajib** berisi nama kolom (header).
2. **Map Columns** — tabel pemetaan *System Field ← File Column*. Sistem mencoba
   mencocokkan **otomatis**: nama kolom file disamakan dengan **nama field sistem**
   (mis. `part_number` ↔ "Part Number"), lalu dengan **label Indonesianya**
   (mis. `unit` ↔ "Satuan"), lalu kalau salah satu diawali yang lain
   (`model_kendaraan` ↔ "Model"). Jadi template aplikasi umumnya terpetakan semua;
   sisanya tinggal dipilih manual dari dropdown. Field bertanda **wajib** harus
   terpetakan, kalau tidak tombol import tidak aktif.
3. **Importing → Hasil** — proses berjalan di **antrian (queue)**, bukan di browser.
   Modal menampilkan progress, lalu ringkasan: **berhasil / dilewati (duplikat) / error**.

### 2.2 Yang terjadi di belakang layar

- Import **tidak** memblokir browser: file disimpan, dicatat di tabel `import_logs`
  (`pending` → `processing` → `completed` / `failed`), lalu diproses worker antrian.
- Karena itu **queue worker harus hidup**. Kalau progress diam di *"Menunggu antrian"*
  atau 0/N terus, lapor admin (`./deploy-production.sh --check-queue`).
- Proses tahan file besar: batas waktu job 30 menit, progress dilaporkan tiap 100 baris.
- Setelah selesai, pembuat import menerima **notifikasi** di lonceng aplikasi.
- Pesan error disimpan **maksimal 200 baris pertama**; kalau lebih, muncul keterangan
  "…error lain tidak ditampilkan". Perbaiki file lalu import ulang.

### 2.3 Arti angka hasil import

| Angka | Arti | Tindakan |
|-------|------|----------|
| **Berhasil** (processed) | Baris benar-benar masuk ke database | – |
| **Dilewati** (skipped) | Baris **duplikat** (sudah ada di sistem) — bukan error | Tidak perlu apa-apa |
| **Error** | Baris tidak valid; ada nomor baris + alasan | Perbaiki baris itu, import ulang (baris yang sudah masuk otomatis dilewati) |

### 2.4 Aturan anti-duplikat (penting supaya tidak dobel)

Setiap modul punya **kunci unik**. Kalau kuncinya sudah ada di sistem, baris itu
**dilewati**, bukan ditambahkan lagi:

| Modul | Kunci unik |
|-------|-----------|
| Produk | `part_number` |
| Model Kendaraan | `name` + `brand` |
| Kategori Produk / Departemen / Jabatan / Lokasi Kerja | `name` |
| Rak | `code` |
| Supplier | `name` |
| Shift | `code` |
| Karyawan | `nik` |
| User | `email` |
| Shopping | `frame_number` (per frame: 1 shopping) |
| Cycle (file Data Order) | kombinasi **supplier × tanggal × nomor CYCLE** |
| Stock Opname | tidak memakai kunci — hasil opname **menggantikan** qty sistem |

> Untuk import yang salah file: import ulang file yang benar **aman** — baris yang
> sudah masuk akan dilewati, jadi tidak menumpuk.

### 2.5 Aturan & batasan file

| Aturan | Keterangan |
|--------|-----------|
| Format | `.xlsx`, `.xls`, `.csv` (**bukan** `.xlsm`/PDF) |
| Ukuran | maks **10 MB** |
| Header | baris ke-1 file = nama kolom |
| Baris kosong | dibuang otomatis (biasanya sisa baris kosong di bawah data) |
| Tanggal | format bebas: `17/09/2026`, `2026-09-17`, atau serial Excel (`46273`) — otomatis dibaca |
| Angka & teks | `qty` harus angka bulat ≥ 1 (kecuali Stock Opname yang boleh 0) |
| Relasi | ditulis **nama/kode**, bukan ID (mis. Supplier = "PT Mitra Jaya", Rak = "A-01") |
| Multi baris | satu file boleh memuat banyak frame/part sekaligus (satu baris = satu part per frame) |

---

## 3. Import Data Order (file lebar dari supplier / TAM)

Dipakai untuk mengubah **file pesanan supplier** menjadi **cycle draft** di aplikasi.

### 3.1 Format file yang dikenali

```
No | Part Number | Part Name | Model | Supplier | CYCLE 1 | CYCLE 2 | ... | CYCLE N | <kolom lain> | CYCLE 1 | ...
```

- Sheet yang dibaca: **`EMAIL`** (sesuai file standar TAM).
- Baris judul/aturan di atas header **dilewati otomatis**; urutan kolom boleh berbeda.
- Qty diambil dari **blok `CYCLE 1..N` yang pertama**. Kalau ada blok `CYCLE` kedua
  (biasanya berisi huruf D/N penanda shift), itu **bukan** qty.
- Supplier dicocokkan dari kolom **Supplier** (kode/nama di master Supplier),
  produk dari **Part Number**. Baris tanpa qty (semua cycle 0) dilewati.
- Maksimal **20.000 baris data** per file.

### 3.2 Langkah

```
1. Transactions > Cycles > tombol "Import Data Order"
2. Pilih file + isi "Tanggal Pengiriman" (tanggal dokumen)
3. Klik Preview → muncul ringkasan: jumlah supplier, cycle, part, qty per gelombang,
   daftar baris yang dilewati (part tidak dikenal, dsb)
4. Pilih mode: "Tambah" (append) atau "Ganti" (replace)  ← lihat §3.3
5. Konfirmasi → cycle DRAFT terbentuk
6. Lanjut TERIMA BARANG di menu Receiving (Quick Receive / Cycle)
```

### 3.3 Mode "Tambah" vs "Ganti" (dan deteksi file identik)

File supplier sering di-update, tapi data lama masih ada di dalamnya:

| Kondisi | Perilaku |
|---------|----------|
| File **identik** untuk tanggal yang sama | Import **dilewati** — tidak membuat duplikat |
| File **berubah** | Pilih **Ganti (replace)**: cycle **draft** hasil import tanggal itu dihapus lalu dibuat ulang dari file terbaru |
| Mode **Tambah (append)** | Selalu menambah cycle baru (dipakai kalau memang ada tambahan gelombang) |

> 🔒 Cycle yang **sudah diterima** (`receiving`/`completed`) **tidak pernah** dihapus
> oleh mode replace. Jadi data penerimaan & stok aman.
> 📌 Setiap cycle hasil import diberi catatan `Import Data Order — ref XXXXXXXX`
> sehingga import ulang bisa dideteksi.

---

## 4. Import Barang Shopping (alur 2 langkah) — **alur utama pengiriman**

Alur resmi dari TAM/pusat: **header dulu, barang kemudian**.

### 4.1 Alur lengkap

```
LANGKAH 1 (operator) — Input HEADER
  Transactions > Shopping > "1. Input Header"
  Isi Line/Lokasi + Frame Number. Default 1 baris:
    • klik 📷 di kolom Frame Number untuk SCAN barcode — tiap scan otomatis
      menyiapkan baris baru, jadi bisa scan berurutan tanpa klik
    • atau tempel (Ctrl+V) banyak frame sekaligus (satu frame per baris)
    • tombol "+ 1 Baris" / "+ 5 Baris" untuk input manual banyak
  → tersimpan sebagai DRAFT (belum ada barang)

LANGKAH 2 (Leader ke atas) — Import BARANG   ← file dari pusat/TAM
  Transactions > Shopping > "2. Import Barang" (butuh permission `import shoppings`)
  File: Frame Number | Part Number | Quantity (+ Confirmed / Cripple / Modify Date)
  → bila frame belum ada: baris ERROR ("Frame ... belum terdaftar di WMS")
     kecuali opsi "Buat frame otomatis" dicentang

LANGKAH 3 (Leader) — KIRIM SEMUA (sekali klik)
  Banner atas > "🚀 Kirim Semua (N frame)"
  → sistem cek stok semua frame dulu, lalu semua di-shipped sekaligus
```

### 4.2 Kolom file & pemetaan

| System Field | Wajib? | Isi |
|--------------|--------|-----|
| `Frame Number` | ✅ | nomor frame — **harus sama** dengan yang diinput di header |
| `Part Number` | ✅ | harus ada di Master Data > Produk |
| `Quantity` | ✅ | angka bulat ≥ 1 |
| `Confirmed` | – | kosong/`TRUE` = dikirim; `FALSE`/`0`/`No` → baris **error** ("Baris tidak terkonfirmasi") |
| `Cripple` | – | `Ya`/`TRUE`/`1` = part tidak lengkap → shopping diproses sebagai **CRIPPLE**; diambil dari baris pertama frame |
| `Modify Date` | – | tanggal dokumen; kosong → tanggal hari ini |

Kolom boleh diurutkan berbeda; tinggal sesuaikan di langkah **Map Columns**.
**Satu baris = satu part**; kalau satu frame punya 12 part, berarti 12 baris
dengan Frame Number yang sama.

### 4.3 Dua opsi di modal "Import Barang"

| Opsi | Kapan dipakai |
|------|---------------|
| **Line / Lokasi Tujuan** (opsional) | Mengisi lokasi untuk **frame baru** yang dibuat otomatis |
| **Buat frame otomatis** | Centang **hanya** kalau permintaan mendadak & header belum sempat diinput. Normalnya **jangan** dicentang — biar alur header → barang tetap rapi |

### 4.4 Setelah import: Kirim Massal (tugas Leader)

Di halaman Shopping, banner atas menampilkan jumlah frame draft + tombol
**🚀 Kirim Semua (N frame)**:

1. Muncul pratinjau **lookup & matching stok** untuk semua frame:
   `Siap kirim: N frame | Dilewati: M frame | Total: Q pcs`
2. Kalau ada yang dilewati, klik **"Lihat M frame yang dilewati & alasannya"**
   (mis. `Stok kurang: P5162-0KA08 di rak A-01 — butuh 20, tersedia 5 (kurang 15)`).
3. Klik **🚀 Kirim Semua** → semua frame yang siap langsung **shipped**; yang kurang
   stok **tetap draft** dan bisa dikirim lagi nanti.
4. Ringkasan hasil muncul: **Terkirim / Dilewati / Gagal** + daftar alasannya.

Detail lengkap: `docs/quick-reference.md` bagian *KIRIM BARANG (SHOPPING)* dan
`docs/panduan-transaksi.md` bagian *B2. Kirim Massal*.

### 4.5 Import Gabungan (alur lama — tetap tersedia)

Satu file berisi **frame + part + qty** sekaligus (kolom sama dengan §4.2).
Bedanya: file ini **membuat frame baru sendiri** (tidak perlu input header).
Berguna kalau pusat mengirim file lengkap dan header belum ada.

Aturan yang tetap berlaku:
- Tombol **Import Gabungan** hanya muncul untuk **Leader ke atas** (permission `import shoppings`).
- Frame yang sudah ada dan masih **draft** → barangnya **digabung** (part yang sama dilewati).
- Frame yang sudah **shipped/cripple** → dianggap pesanan baru, dibuatkan shopping baru.
- Menggabung ke draft butuh izin **`edit shoppings`**; kalau tidak punya, baris error
  "Frame … sudah ada (draft) — tidak punya izin edit untuk menggabungkan".

---

## 5. Import Master Data

### 5.1 Produk (paling sering dipakai)

Template: `import-template-product.xlsx` (tombol CSV/XLSX di modal).

| Kolom di template | Wajib | Catatan |
|-------------------|-------|---------|
| `PartNumber` | ✅ | kunci unik — kalau sudah ada, baris **dilewati** |
| `Nama` | ✅ | nama produk |
| `Merek` | ✅ | harus cocok dengan **brand** di Master Model Kendaraan (mis. `Toyota`) |
| `Model` | ✅ | nama model di master (mis. `Avanza`) — dicari pasangan **Merek + Model** |
| `Supplier` | ✅ | **nama** supplier di master |
| `Kategori` | ✅ | **nama** kategori di master |
| `Satuan` | ✅ | mis. `pcs`, `set`, `box` |
| `Deskripsi` | – | teks bebas |
| `Aktif` | – | kosong = `TRUE` (aktif). Isi `0`/`FALSE` untuk nonaktif |
| `Rak` | – | **kode** rak di master (mis. `A-01`) sebagai rak default |

> ⚠️ Yang paling sering bikin error: **Merek + Model tidak ada di master**, atau
> **Supplier/Kategori** ditulis dengan singkatan yang tidak ada di master.
> **Lengkapi master Model Kendaraan, Supplier, dan Kategori dulu** sebelum import produk.

### 5.2 Master data lain

| Modul | Kolom template | Wajib |
|-------|----------------|-------|
| **Model Kendaraan** | `Name`, `Brand`, `Suffix` | Name |
| **Kategori Produk** | `Name`, `Description` | Name |
| **Rak** | `Code`, `Zone`, `Capacity` | Code, Zone |
| **Supplier** | `Code`, `Name`, `Contact Person`, `Email`, `Phone`, `Street`, `City`, `State`, `Postal Code`, `Country` | Name, Email, Street, City, State, Postal Code, Country |

### 5.3 Data karyawan & user

| Modul | Kolom template | Wajib | Catatan |
|-------|----------------|-------|---------|
| **User** | `Name`, `Email`, `Password` | ketiganya | password minimal 8 karakter |
| **Karyawan** | `Name`, `Nik`, `Job Position Id`, `Work Location Id`, `Department Id`, `Shift Id`, `Phone`, `Email`, `Status` | Name | kolom relasi diisi **nama** (bukan ID) meski judul kolomnya "…Id"; `Status` = `Aktif` / `Nonaktif` (kosong → otomatis `Aktif`) |
| **Departemen** | `Name` | Name | – |
| **Jabatan** | `Name`, `Level` | Name | – |
| **Shift** | `Name`, `Code`, `Start Time`, `End Time`, `Status` | Name, Code, jam, Status | jam format `HH:MM` (mis. `07:30`) |
| **Lokasi Kerja** | `Name` | Name | – |

---

## 6. Import Cycle (template generik)

Dipakai kalau penerimaan **tidak** memakai file lebar Data Order.

| Kolom | Wajib | Catatan |
|-------|-------|---------|
| `Cycle Number` | ✅ | nomor gelombang **dari file**; kalau nomor itu sudah terpakai hari itu, sistem memakai nomor bebas berikutnya |
| `Supplier Name` | ✅ | boleh **kode** atau **nama** supplier |
| `Delivery Date` | ✅ | tanggal dokumen |
| `Part Number` | ✅ | harus ada di master produk |
| `Quantity` | ✅ | **baris dengan qty `0` otomatis dilewati** (artinya tidak ada pesanan) |
| `Notes` | – | catatan cycle |

Pengelompokan: satu cycle dibuat per **supplier × tanggal × nomor cycle**;
baris-baris berikutnya dengan kombinasi sama menambah item ke cycle itu.
Nomor cycle selalu **reset tiap tanggal per supplier** (aturan domain aplikasi).

---

## 7. Stock Opname (template + unggah hasil hitung)

Berbeda dari import lain: **file-nya dari aplikasi** (sudah terisi stok sistem),
operator mengisi kolom fisik, lalu diunggah kembali.

```
1. Transactions > Stock Opname > tombol Template (unduh .xlsx)
   kolom: Part No | Part Name | RAK | SAP (qty sistem) | Qty Opname (kosong)
   (bisa difilter per zona / rak saat mengunduh)
2. Tim gudang menghitung fisik → isi kolom "Qty Opname"
3. Upload kembali → muncul PRATINJAU: per baris statusnya
      same  = qty sama
      diff  = beda (ditampilkan "Stok naik X" / "Stok turun X")
      new   = ada di fisik tapi belum ada baris stok → akan dibuat
      invalid/not found = rak/part tidak dikenal (dilewati)
4. Klik Terapkan → stok disesuaikan + tercatat di Riwayat Opname
```

> 🔴 **PENTING — isi kolom `RAK`!** Kalau kolom RAK kosong/hilang, **semua baris
> dianggap RELAY** dan aplikasi membuat baris stok RELAY **baru** padahal stok part
> itu sudah ada di rak → **stok jadi dobel**. Aplikasi sekarang memberi peringatan
> tegas di pratinjau:
> `⚠ produk ini SUDAH punya stok di A1:50 — kalau barangnya sama, JANGAN diterapkan`.
> Kalau perbaikan data telanjur dibutuhkan, admin menjalankan
> `php artisan stocks:audit` (lihat `docs/production-server-setup.md` §9.7).

---

## 8. Pesan Error Umum & Solusinya

| Pesan | Penyebab | Solusi |
|-------|----------|--------|
| `The file field is required` / `Unsupported file format` | File belum dipilih / bukan xlsx-xls-csv | Pilih ulang file; simpan ulang dari Excel sebagai `.xlsx` atau `.csv` |
| `The file may not be greater than 10240 kilobytes` | File > 10 MB | Pecah file jadi beberapa bagian |
| `Kolom ... wajib dipetakan` / tombol import mati | Ada field wajib belum dipilih di langkah *Map Columns* | Lengkapi pemetaan (field bertanda wajib) |
| `Part Number "X" tidak ditemukan` | Part belum ada di master produk | Import/tambah produk dulu, atau perbaiki typo |
| `Product dengan part_number "X" tidak ditemukan` | sama (import cycle) | idem |
| `Supplier dengan kode/nama "X" tidak ditemukan` | Supplier belum ada / salah tulis | Import/tambah supplier dulu |
| `Model kendaraan "Toyota X" tidak ditemukan` | Kombinasi brand+model belum ada | Tambah di Master Data > Model Kendaraan |
| `Frame "X" belum terdaftar di WMS` | Alur 2 langkah: header belum diinput | **Input Header** dulu, atau centang **"Buat frame otomatis"** |
| `Frame X sudah ada (draft) — tidak punya izin edit untuk menggabungkan` | Import gabungan ke frame draft, user tanpa izin `edit shoppings` | Minta user berizin, atau pakai alur 2 langkah |
| `Baris tidak terkonfirmasi (Confirmed = false)` | Kolom Confirmed bernilai FALSE/0/No | Hapus kolom itu, atau isi `TRUE` |
| `Qty Opname kosong / tidak valid` | Kolom qty opname kosong | Isi angka hasil hitung fisik (0 boleh) |
| `Kode rak tidak dikenal di sistem` | Kode rak di file tidak ada di master | Perbaiki kode rak / tambah rak |
| `File kosong` / `Tidak ada baris data di file` | Semua baris kosong / format salah | Pastikan header di baris pertama & ada baris data |
| Progress diam di **"Menunggu antrian"** | Queue worker mati | Lapor admin: `./deploy-production.sh --check-queue` |
| Status **failed** di hasil import | Proses berhenti (mis. file rusak) | Buka pesan error di hasil import, perbaiki, import ulang |
| Baris berhasil sedikit, error ribuan | Salah pemetaan kolom | Cek *Map Columns*, pastikan kolom file ↔ field sistem benar |
| `… error lain tidak ditampilkan` | Error > 200 baris | Perbaiki file (biasanya 1 penyebab sama), import ulang |
| Semua baris **dilewati (duplikat)** | Data memang sudah ada | Tidak perlu diulang — cek daftar/modulnya |

---

## 9. Checklist Operator

**Sebelum import**
- [ ] Baris pertama file = nama kolom (header), tidak ada baris judul di atasnya
- [ ] Pakai **template terbaru** dari tombol unduh (untuk produk/shopping/opname)
- [ ] Master data pendukung sudah ada (produk, supplier, model kendaraan, kategori, rak)
- [ ] Untuk Shopping alur 2 langkah: **header (frame) sudah diinput**
- [ ] Untuk Stock Opname: kolom **RAK** terisi
- [ ] File < 10 MB & format xlsx/xls/csv

**Setelah import**
- [ ] Buka ringkasan import: cek **Berhasil / Dilewati / Error**
- [ ] Baca & perbaiki baris yang error (nomor baris + alasannya ada di modal)
- [ ] Cek hasil di daftar modulnya (mis. jumlah frame draft, produk baru)
- [ ] Alur shopping: lanjut **🚀 Kirim Semua** (Leader)
- [ ] Alur receiving: lanjut **Terima Barang** supaya stok bertambah
- [ ] Pastikan **stok masuk akal** (Transactions > Stocks) — pakai filter/mencari

**Yang sebaiknya dihindari**
- ❌ Mengganti nama kolom jadi tidak jelas (`qty1`, `kolom A`) — pemetaan otomatis gagal
- ❌ Menaruh 2 tabel berbeda di satu sheet
- ❌ Input ulang file yang sama berkali-kali untuk "memastikan" (tetap dilewati sebagai duplikat, tapi buang waktu)
- ❌ Menerapkan hasil Stock Opname yang pratinjaunya memberi peringatan RELAY
- ❌ Import produk sebelum master Model Kendaraan/Supplier/Kategori lengkap

---

## 10. Lampiran — Template & Endpoint

Semua tautan di bawah bisa diakses dari tombol di UI (tidak perlu diketik manual).

| Modul | Template | Endpoint import |
|-------|----------|-----------------|
| Produk | `products/import-template` | `POST products/import` |
| Rak | `racks/import-template` | `POST racks/import` |
| Supplier | `suppliers/import-template` | `POST suppliers/import` |
| Model Kendaraan | `vehicle-models/import-template` | `POST vehicle-models/import` |
| Kategori Produk | `product-categories/import-template` | `POST product-categories/import` |
| User | `users/import-template` | `POST users/import` |
| Karyawan | `employees/import-template` | `POST employees/import` |
| Departemen | `departments/import-template` | `POST departments/import` |
| Jabatan | `job-positions/import-template` | `POST job-positions/import` |
| Shift | `shifts/import-template` | `POST shifts/import` |
| Lokasi Kerja | `work-locations/import-template` | `POST work-locations/import` |
| Cycle (generic) | `cycles/import-template` | `POST cycles/import` |
| Cycle (Data Order) | – | `POST cycles/data-order/preview` → `cycles/data-order/apply` |
| Shopping (barang) | `shoppings/import-items-template` | `POST shoppings/import-items` |
| Shopping (gabungan) | `shoppings/import-template` | `POST shoppings/import` |
| Shopping (header) | `shoppings/import-headers-template` | `POST shoppings/import-headers` |
| Stock Opname | `stock-opname/template` | `POST stock-opname/preview` → `stock-opname/apply` |

Status/progress import dibaca dari `GET /import-status/{id}` (dipakai polling modal).
Export kebalikannya: setiap modul master & laporan punya tombol **Export** (xlsx/csv/pdf;
untuk data besar gunakan **CSV** — lihat §9.4 `docs/production-server-setup.md`).

---

## Lihat juga

- `docs/quick-reference.md` — contekan harian (alur receiving, shopping, stok, opname)
- `docs/panduan-transaksi.md` — panduan transaksi lengkap (termasuk kirim massal)
- `docs/production-server-setup.md` — perawatan server, queue, error produksi (§9)
- `docs/delivery-monitor.md` — konsep slot C1–C6 vs nomor cycle
