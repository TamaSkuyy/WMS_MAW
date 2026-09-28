# Desain UI/UX Mobile-Friendly — Input Header Shopping (Langkah 1)

- **Tanggal:** 2026-09-18
- **Status:** Diminta user (permintaan: "input header di shopping mobile friendly")
- **Cakupan:** `resources/js/Pages/Transactions/Shopping/Headers.tsx` (fokus), pendukung `Components/ScanButton.tsx` & `Tailadmin/.../input/InputField.tsx`
- **Perangkat:** HP/handheld prioritas (≤ 480px); tablet & desktop tetap
- **Pola acuan:** `docs/superpowers/specs/2026-08-14-shopping-mobile-uiux-design.md` (dual rendering kartu/tabel + footer sticky)

## Latar Belakang

Halaman **Input Header (Langkah 1)** menampilkan baris frame dalam tabel dengan
scroll horizontal (`min-w-[220px]` per kolom) dan tombol simpan di bagian bawah
halaman. Di HP akibatnya: scroll ke samping untuk mengisi, tombol simpan jauh dari
jempol, dan tombol scan 📷 masih ukuran kecil (`size="sm"`, ±36px) — di bawah
standar area sentuh 44px.

Tujuan: **menghilangkan scroll horizontal di HP** dan membuat alur isi-cepat
(scan / tempel banyak frame) nyaman satu tangan. **Logika bisnis tidak diubah**
(scan, dedupe, paste, Enter pindah baris, submit ke `shoppings.headers.store`).

## Bagian 1 — Layout & Responsive

- **Dual rendering** (pola sama dengan `Shopping/Create.tsx`):
  - `≥ 768px` (`hidden md:block`): tetap tabel 5 kolom (#, Line/Lokasi, Frame
    Number, Cripple, hapus).
  - `< 768px` (`md:hidden`): **kartu per frame** — nomor baris + status
    ("Terisi"/"Baris baru"), input Frame Number besar + tombol 📷 44px,
    Line/Lokasi full-width, baris Cripple berlabel, tombol hapus 44px.
- Kartu yang sudah terisi diberi highlight (`brand-50`) supaya progres kelihatan.
- **Footer aksi sticky** (`sticky bottom-4`) berisi `+ 1 Baris`, `+ 5 Baris`,
  `Reset`, jumlah frame siap, dan tombol **Simpan Header** — selalu terjangkau.
- Info tanggal shopping + total frame draft tetap di atas, diberi kartu abu.

## Bagian 2 — Input & Perilaku (tidak berubah, hanya dipertegas)

- **Enter** di kolom Frame Number: pindah ke baris berikutnya; di baris terakhir
  menambah 1 baris baru (dukung scanner barcode USB). Handler dipindah dari
  `<tr>` ke input frame supaya Enter di combobox Line/Lokasi tidak lagi
  menambah baris.
- **Scan 📷**: mengisi baris, menolak frame duplikat, lalu otomatis menyiapkan
  baris berikutnya dan memfokuskan inputnya.
- **Tempel banyak frame**:
  - Desktop: `Ctrl+V` di kolom Frame Number (perilaku lama).
  - Tambahan: tombol **"📋 Tempel dari Clipboard"** memakai
    `navigator.clipboard.readText()` — praktis di HP. Kalau izin/browser tidak
    mendukung, tampil pesan agar tempel manual (tidak error).
- **Fokus baris** diarahkan ke kontainer yang sedang tampil (`tableRef` /
  `cardsRef`), karena kedua layout ada di DOM bersamaan.
- Validasi "belum ada frame" memakai **Alert inline**, bukan `alert()` browser.
- Tidak ada perubahan payload/route: `shopping_date` + `rows[]`
  (`frame_number`, `shopping_location_id`, `is_cripple`).

## Bagian 3 — Area Sentuh & Keyboard HP

- Tombol scan varian baru `size="lg"` di `ScanButton` → 44×44 px (dipakai di kartu HP).
- Input Frame Number HP: `autoComplete="off"`, `autoCapitalize="characters"`,
  `autoCorrect="off"`, `spellCheck={false}`, `enterKeyHint="next"`,
  `inputMode="text"`, `text-base` (16px, mencegah zoom otomatis iOS).
- Atribut native itu diteruskan lewat prop opsional baru di
  `InputField` (`inputMode`, `autoComplete`, `autoCapitalize`, `autoCorrect`,
  `spellCheck`, `enterKeyHint`, `onKeyDown`) — default tidak berubah sehingga
  halaman lain tidak terpengaruh.

## File

- **Ubah:** `resources/js/Pages/Transactions/Shopping/Headers.tsx`
- **Ubah (aditif):** `resources/js/Components/ScanButton.tsx` (prop `size`),
  `resources/js/Tailadmin/components/form/input/InputField.tsx` (passthrough
  atribut mobile + `onKeyDown`)
- **Pakai ulang:** `SearchableSelect`, `Checkbox`, `Button`, `Alert`, `QrScanner`

## Verifikasi

- `npx tsc --noEmit` — hanya warning `baseUrl` lama.
- `npm run build` sukses.
- Cek visual: HP 390px (kartu + footer sticky + scan 44px), tablet 768px & desktop
  1280px (kembali tabel), dark mode.
- Perilaku lama terjaga: scan auto baris baru, tolak duplikat, paste multi-baris,
  Enter pindah baris, tombol "Lanjut: Import Barang" (khusus Leader) tetap ada.

## Non-Goals

- Tidak mengubah backend / controller / route / payload submit.
- Tidak mengubah halaman Index, Create, Edit, atau Show.
- Tidak menambah departemen/alat baru (mis. PWA, offline).
