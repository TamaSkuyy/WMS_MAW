# Delivery Monitor

TV/monitor dashboard real-time untuk memantau pengiriman part dari supplier ke gudang, dibagi ke 6 slot waktu tetap per hari (C1-C6). Diakses di `/delivery-monitor` (halaman publik, tanpa login, cocok untuk layar yang dipasang permanen di gudang).

## Konsep Dasar

Ada 3 lapisan data yang dipisah dengan sengaja:

| Lapisan | Tabel | Sifat |
|---|---|---|
| Jam slot | `delivery_slots` | Master tetap, 6 baris (C1-C6), bisa diedit adminnya |
| Jadwal supplier | `supplier_delivery_schedules` | Template berulang: supplier X dijadwalkan di slot mana saja, berlaku tiap hari otomatis |
| Data aktual | `cycles` + `cycle_items` | Data receiving asli yang sudah ada di sistem, hanya ditambah `delivery_date` & `delivery_slot_id` |

Kenapa dipisah begini: `cycle_number` (nomor urut penerimaan per supplier) itu **bukan** hal yang sama dengan slot C1-C6 (jendela waktu). `cycle_number` cuma penomoran urut yang bertambah terus (1, 2, 3, ...) setiap kali ada cycle baru untuk supplier tsb — tidak reset harian dan tidak dijamin cuma 6x sehari. Karena itu, "slot mana yang sedang berjalan" tidak bisa diturunkan dari `cycle_number` — perlu konsep slot yang berdiri sendiri.

## Alur Data (Input → Tampil di Layar)

1. **Setup sekali** (admin): atur jam tiap slot di `/delivery-slots`, lalu assign supplier ke slot yang relevan lewat halaman Edit Supplier (checkbox C1-C6). Ini template yang berlaku terus tiap hari, tidak perlu diulang.
2. **Staff terima barang** seperti biasa — lewat form Create Cycle manual atau scan QR (Quick Receive). Tidak ada langkah tambahan.
3. **Otomatis di backend** (`CycleController::store()` dan `quickReceiveStore()`): setiap cycle baru dibuat, sistem:
   - Set `delivery_date` = tanggal hari ini
   - Cari slot mana yang cocok dengan jam sekarang (`DeliverySlot::currentForTime()`) → simpan sebagai `delivery_slot_id`
4. **Saat Delivery Monitor dibuka**, `DeliveryMonitorController::index()` memanggil `DeliveryMonitorSnapshotBuilder::build($date)` yang membaca semua data di atas dan menghitung nilai-nilai turunan (lihat bawah) — semuanya dihitung ulang tiap request, **tidak ada yang disimpan permanen**.
5. **Live update**: halaman listen channel Reverb `warehouse.stock`. Setiap ada penerimaan barang baru (event `StockChanged`, sudah otomatis broadcast dari alur receiving yang ada), Delivery Monitor reload data (`suppliers`, `cycles`, `parts`, `receipts`) tanpa refresh manual.

## Nilai yang Dihitung Otomatis (Tidak Disimpan)

**Status Supplier** — dibandingkan jadwal (langkah 1) vs cycle hari ini (langkah 3):

| Status | Kondisi |
|---|---|
| `standby` | Tidak ada slot yang dijadwalkan untuk supplier ini |
| `done` | Semua slot terjadwal hari ini sudah terpenuhi (qty diterima ≥ qty rencana) |
| `alert` | Ada slot yang jam selesainya sudah lewat tapi belum terpenuhi |
| `live` | Masih ada slot berjalan/mendatang, belum ada yang telat |

**Status tiap Part per Slot** — dari `CycleItem.quantity` (rencana) vs `received_quantity` (aktual):

| Status | Kondisi |
|---|---|
| `pending` | Belum ada yang diterima sama sekali |
| `shortage` | Diterima < rencana |
| `matched` | Diterima = rencana |
| `over` | Diterima > rencana |

## Komponen Halaman

- **Header** — dropdown pilih supplier, date picker, jam live, toggle TV Mode (fullscreen) & Rotate (auto-ganti fokus supplier tiap 8 detik saat TV Mode aktif) & Dark Mode, badge OTIF % (on-time-in-full, dihitung dari seluruh receipts hari itu)
- **Supplier Grid** — kartu semua supplier dengan warna status (live=oranye, alert=merah, done=hijau, standby=abu-abu), pencarian & filter tab
- **Panel Detail Supplier** — fokus ke 1 supplier: progress slot yang sedang berjalan, total fulfillment hari itu, daftar part terjadwal di slot saat ini
- **Tabel Part** — semua part lintas supplier terjadwal, kolom C1-C6 (rencana/aktual tiap slot), status total per part, pencarian, filter kategori & status, di-virtualize (kuat untuk 1500+ baris), tombol "Reset Receipts" (cuma reset tampilan browser, tidak menyentuh database)

## Halaman Admin Terkait

| Halaman | Fungsi |
|---|---|
| Master Data → Jadwal Slot Pengiriman (`/delivery-slots`) | Edit jam mulai/selesai & label tiap slot C1-C6 |
| Master Data → Suppliers → Edit | Assign supplier ke slot mana saja (checkbox), lewat section "Jadwal Pengiriman (Slot Harian)" |

## Batasan Saat Ini

- Date picker di header belum menarik ulang data dari server saat tanggal diganti — masih state lokal saja (belum ada re-fetch). Kalau mau lihat data tanggal lain secara live, ini perlu ditambahkan (`router.get` dengan query param `date`, yang controllernya sudah mendukung).
- Tidak ada riwayat/laporan historis di dalam halaman ini — fokusnya cuma "hari ini".

## File-File Terkait

- Backend: `app/Http/Controllers/DeliveryMonitorController.php`, `app/Services/DeliveryMonitor/DeliveryMonitorSnapshotBuilder.php`, `app/Models/DeliverySlot.php`, `app/Models/SupplierDeliverySchedule.php`
- Frontend: `resources/js/Pages/DeliveryMonitor/`
- Spec & plan pembuatan: `docs/superpowers/specs/2026-07-14-delivery-monitor-design.md`, `docs/superpowers/specs/2026-07-15-delivery-monitor-real-data-design.md`, `docs/superpowers/plans/2026-07-15-delivery-monitor-real-data-implementation.md`
