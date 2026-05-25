# Resurvey ALDA App - Mobile Collection Subsystem

Sistem Resurvey ALDA merupakan _sub-system_ berbasis _web app_ dari core aplikasi _Mobile Collection_ (Suzuki Finance). Platform ini khusus ditugaskan untuk menangani pengelolaan dan monitoring kunjungan _re-survey_ nasabah (ALDA/Alternative Data) oleh PIC Lapangan.

## 1. Arsitektur Frontend & Clean Styling

Sistem mengadopsi standar **Clean Architecture** pada sisi frontend dengan memusatkan seluruh desain UI ke dalam _Centralized CSS Design Tokens_.

- **File Utama:** `public/assets/css/styles.css`
- **Konsep:** Seluruh inline styles (`<style>`) dihilangkan dari file PHP. Kami menggunakan _CSS Variables (`:root`)_ dan Class Utilities untuk mendefinisikan layout mobile (`max-width: 480px`), skema warna, bayangan, tipografi, serta struktur layout modular seperti `Modal Bottom Sheet` dan `Task Card`.

## 2. Arsitektur Backend, Flow Sistem & Database (SQL Server)

Sistem bergantung pada SQL Server untuk logika bisnis berat melalui pemanggilan **Stored Procedure (SP)** untuk memastikan keamanan dari SQL Injection, meminimalisir overhead PHP, dan mendokumentasikan log rekam jejak setiap aksi secara otonom di database.

### 2.1. Struktur Tabel Transaksional & Audit

- `[MASTER_ALDA]`: _Single Source of Truth_. Tabel core/inti yang menampung daftar nasabah statis.
- `[MASTER_ALDA_PIC]`: Autentikasi dan hak hierarki PIC Karyawan lapangan.
- `[ALDA_PENUGASAN]`: Menggunakan metode _Data-Snapshot_. Setiap record menangkap keadaan nasabah tepat pada saat tugas dialokasikan. Status yang dimuat di sini menentukan perpindahan data di aplikasi PIC.
- `[ALDA_PENUGASAN_HISTORY]`: _Audit Trail System_. Setiap mutasi (pergantian PIC, pergantian status, alasan batal) akan terekam ke tabel ini beserta _Version Number_-nya.

### 2.2. Daftar Stored Procedure Esensial

- `SP_LOGIN_RESURVEY_ALDA`: Autentikasi login berdasarkan NIK aktif dari tabel `MASTER_ALDA_PIC`.
- `SP_ALDA_GET_PENUGASAN`: Menarik list penugasan nasabah (Digunakan pada Dashboard CMS/Website Admin).
- `SP_ALDA_RECORD_PENUGASAN`: Monitoring penugasan di sisi Backend CMS. Mendukung filter Cabang, NIK, dan Date.
- `SP_ALDA_PIC_GET_TASKS`: Memfilter dan memuat list penugasan _khusus untuk PIC yang sedang login_ di aplikasi mobile (Berbasis Status: `ASSIGNED`, `ON_PROCESS`, dst).
- `SP_ALDA_UPDATE_PIC`: Modul di halaman CMS admin untuk melakukan Re-Assign/Pergantian PIC di tengah jalan.
- `SP_ALDA_PIC_UPDATE_STATUS`: **Core Module** untuk PIC Mobile. Menangani mutasi status penugasan secara live (Contoh: `ASSIGNED` -> `ON_PROCESS`). Prosedur ini otomatis memicu insert Audit Log ke `ALDA_PENUGASAN_HISTORY`.

## 3. Modul & Flow Aplikasi Mobile (Folder `public/`)

### Autentikasi (`login.php` & `logout.php`)

Menggunakan **NIK** sebagai kredensial via `SP_LOGIN_RESURVEY_ALDA`. Akun PIC yang dinonaktifkan oleh admin tidak dapat mem-bypass sesi yang ada (kick protection).

### Dashboard Utama (`dashboard.php`)

Pintu masuk navigasi modul. Menampilkan greetings nasabah dan shortcut menuju menu antrean dan tabulasi penyelesaian tugas.

### Antrean Tugas Baru (`tugas-baru.php`)

- **Load Task:** Menampilkan tugas berstatus `ASSIGNED` yang khusus diperuntukkan untuk PIC Session Login.
- **Fitur Detail Modal:** Dilengkapi Bottom Sheet Modal interaktif dengan manipulasi DOM JSON tanpa reload page untuk menampilkan info alamat dan rincian nominal tagihan secara penuh.
- **Fitur Proses:** Terintegrasi form POST ke `SP_ALDA_PIC_UPDATE_STATUS`. Menekan _Proses_ akan mengganti status DB menjadi `ON_PROCESS`. Item tersebut akan otomatis terhapus dari laman ini dan berpindah ke tab _Tugas Proses_.

### Tugas Sedang Diproses (`tugas-proses.php`)

- Menampilkan seluruh antrean yang sedang ditangani (`ON_PROCESS`).
- Menggunakan arsitektur layout CSS yang sama dengan Tugas Baru (`.task-card`, `.modal-overlay`), namun membuang tombol _Proses_ agar pengguna tidak bisa trigger duplikat.

### Halaman Tahap Pengembangan (Under Construction)

- `tugas-sedang-berjalan.php`: Dirancang untuk menampung integrasi _Geotagging Check-in_ bila PIC telah tiba di lokasi.
- `upload.php`: Antarmuka formulir rekam _evidence_ (Lampiran foto survei tempat dan dokumen/form yang telah diisi).
- `selesai.php`: Rekapitulasi akhir/histori pekerjaan yang berstatus `COMPLETED` atau `CANCELLED`.

## 4. Environment Setup & Development

1. **PHP Core:** Proyek ini menggunakan PHP `8.3.x`.
2. **Database:** Microsoft SQL Server 2008.
3. **Ekstensi & Driver:** Pastikan ekstensi `sqlsrv` dan `pdo_sqlsrv` yang kompatibel dengan versi PHP dan OS telah di-load di `php.ini`.
4. **Setup Database:** Impor tabel dan eksekusi skrip seluruh `SP_*.sql` yang ada dalam folder `config/SQL/`.
5. **Kredensial:** Konfigurasi koneksi pada file `config/connection.php`.
