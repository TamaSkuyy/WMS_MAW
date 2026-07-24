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

```
1. Menu: Transactions > Shopping > "Tambah Shopping"
2. Pilih Partner + item (qty rencana)
3. Simpan → "Draft"
4. Klik [Ship] → input qty dikirim → [Konfirmasi]
```
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

## 🛠️ MASALAH UMUM

| Masalah | Solusi |
|---------|--------|
| Barang sudah diterima tapi stok tidak bertambah | Refresh halaman |
| Cycle tidak muncul di Delivery Monitor | Supplier belum punya jadwal slot → Edit Supplier, centang slot |
| Tidak bisa klik Receive | Cycle harus status "Draft" |
| Salah input jumlah | ❌ Tidak bisa di-undo → lapor admin |
| Nama supplier tidak ada di dropdown | Tambah dulu di Master Data > Suppliers |
| Produk tidak ada | Tambah dulu di Master Data > Products |

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
