# Resurvey ALDA App - Mobile Collection Subsystem

Sistem Resurvey ALDA merupakan _sub-system_ berbasis _web app_ dari core aplikasi _Mobile Collection_ (Suzuki Finance). Platform ini khusus ditugaskan untuk menangani pengelolaan dan monitoring kunjungan _re-survey_ nasabah (ALDA/Alternative Data) oleh PIC Lapangan.

## 1. Arsitektur Frontend & Styling (Clean UI)

Sistem menggunakan pendekatan **Centralized CSS Variables** untuk menjamin konsistensi dan skalabilitas antar-modul web mobile.

- **File Utama:** `public/assets/css/styles.css`
- **Implementasi:** Seluruh _hardcoded styling_ dan `<style>` tags pada _head_ halaman dihilangkan. UI diatur menggunakan variabel `:root` (seperti `--primary`, `--surface`, `--radius-md`).
- **Design Pattern:** Dioptimalkan untuk dimensi mobile (`max-width: 480px`) dengan simulasi _Bottom Sheet_ untuk _Modal Popup_.

## 2. Arsitektur Backend & Database (SQL Server)

Aplikasi menerapkan standar keamanan tingkat institusi keuangan dan memisahkan fungsi-fungsi esensial ke lapisan database melalui **Stored Procedure (SP)** untuk mencegah SQL Injection dan mengurangi logic beban di PHP.

### Struktur Tabel Master

- `[MASTER_ALDA]`: Bertindak sebagai _Single Source of Truth_ nasabah/kontrak statis yang ditarik dari _core system_.
- `[MASTER_ALDA_PIC]`: Tabel autentikasi lapangan (Karyawan yang login). Menyimpan hierarki area, cabang, nama, dan hak akses aktif (`IS_ACTIVE`).

### Struktur Tabel Transaksional (Snapshot & Audit Trail)

- `[ALDA_PENUGASAN]`: Menggunakan metode _Data-Snapshot_. Data _Assignment_ akan merekam informasi persis seperti pada momen penugasan, sehingga perubahan di `MASTER_ALDA` di masa depan tidak merusak histori tugas lama.
- `[ALDA_PENUGASAN_HISTORY]`: Bertindak sebagai _Audit Trail_ yang otomatis mendokumentasikan perpindahan status (`OLD_STATUS` -> `NEW_STATUS`), pemindahan hak milik _PIC_, hingga pencatatan _Changed By_ & _Alasan_.

## 3. Modul & Fungsionalitas Halaman

### Autentikasi (`login.php` & `logout.php`)

Menggunakan **NIK** sebagai kredensial _(Email dependency dihilangkan)_. Login mengarah ke `SP_LOGIN_RESURVEY_ALDA` untuk memastikan `IS_ACTIVE = 1`. Flow dijaga secara terpusat. Jika session habis, user otomatis dilempar ke login melalui file _router/redirector_ (`index.php`).

### Dashboard (`dashboard.php`)

Sinkronisasi real-time. Jika admin menonaktifkan akun PIC, session otomatis dihancurkan (_kick_) saat itu juga, menjamin keamanan hak eksekusi aplikasi mobile.

### Tugas Baru (`tugas-baru.php`)

- **Filtering Ownership:** Dipasangkan dengan `SP_ALDA_PIC_GET_TASKS` yang secara ketat memfilter query _SQL_ hanya untuk PIC yang _login_.
- **Fitur Detail:** Menggunakan _Modal Popup_ interaktif yang dipopulasi via JSON untuk memberikan detail ringkas navigasi nasabah.
- **Fitur Proses:** Terintegrasi dengan `SP_ALDA_PIC_UPDATE_STATUS`. Jika status nasabah ditekan _Proses_, status penugasan berubah di DB dari `ASSIGNED` menjadi `ON_PROCESS`. Record akan seketika hilang dari halaman ini (disinkronkan _real-time_).

### Halaman Tahap Pengembangan (Under Construction Placeholder)

Beberapa integrasi form laporan maupun media saat ini masih dalam fase konseptual/R&D.

- `tugas-proses.php`: (_In Development_) Akan menampung antrean yang baru saja di-_Proses_ pada menu Tugas Baru untuk keperluan navigasi GPS/Mapping lanjutan.
- `tugas-sedang-berjalan.php`: (_In Development_) Akan dikembangkan untuk menampung task di mana petugas sudah menekan tombol "Check-in" di lokasi nasabah (Geotagging integrasi).
- `upload.php`: (_In Development_) Fitur untuk lampiran foto rumah/surat jalan hasil resurvey.
- `selesai.php`: (_In Development_) Tabulasi histori penugasan dengan status statis/final (`COMPLETED` atau `CANCELLED`).

## 4. Panduan Environment Setup

1. **PHP:** Menggunakan PHP versi `8.3.x`.
2. **Database:** SQL Server 2008.
3. **Ekstensi:** Instal _Drivers_ ekstensi `sqlsrv` & `pdo_sqlsrv`.
4. Sesuaikan kredensial server pada file `config/connection.php`.
5. Eksekusi file SP di folder `config/SQL/` untuk membangkitkan struktur dan relasi _stored procedure_ terbaru.
