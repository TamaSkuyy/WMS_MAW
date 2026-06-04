# Import/Export Example Files

## User Import Template

Dua format template tersedia untuk import user:

### Format yang Didukung
- **CSV** (`users-import-template.csv`) — Bisa dibuka dengan Notepad, VS Code, atau spreadsheet editor
- **XLSX** (`users-import-template.xlsx`) — Format Excel, bisa dibuka dengan Microsoft Excel, Google Sheets, atau LibreOffice Calc

### Kolom yang Wajib Diisi

| Kolom      | Tipe   | Wajib | Aturan Validasi                  |
|------------|--------|-------|----------------------------------|
| `name`     | string | YA    | Maksimal 255 karakter            |
| `email`    | string | YA    | Format email valid, unik         |
| `password` | string | YA    | Minimal 8 karakter               |

### Aturan Import
1. **Baris pertama** harus berisi header kolom (nama kolom tidak harus persis sama — kamu bisa mapping ulang di UI)
2. **Email** harus unik — jika email sudah ada di database, baris akan di-skip
3. **Password** akan otomatis di-hash saat disimpan ke database
4. **Role** tidak termasuk dalam import — user baru tidak akan memiliki role sampai di-assign manual
5. Maksimal ukuran file: **10 MB**
6. Format file yang didukung: `.xlsx`, `.xls`, `.csv`

### Contoh Isi File

| name             | email                    | password        |
|------------------|--------------------------|-----------------|
| John Doe         | john.doe@example.com     | password123     |
| Jane Smith       | jane.smith@example.com   | securepass456   |
| Bob Johnson      | bob.johnson@example.com  | mysecret789     |
| Alice Williams   | alice.w@example.com      | alice2024safe   |
| Charlie Brown    | charlie.b@example.com    | browniePass99   |

### Cara Menggunakan
1. Download salah satu template di atas
2. Isi data user sesuai kolom yang ada
3. Di halaman Users, klik tombol **Import**
4. Upload file yang sudah diisi
5. Mapping kolom jika diperlukan (biasanya otomatis terdeteksi)
6. Klik **Start Import** untuk memulai proses import

### Format Export
Export mendukung 3 format output:
- **Excel (.xlsx)** — Default
- **CSV** 
- **PDF** — Menggunakan template HTML sederhana

Kolom yang di-export: `Name`, `Email`, `Role`, `Created At`
