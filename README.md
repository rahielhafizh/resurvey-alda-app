# Resurvey ALDA: Spesifikasi Arsitektur & Teknikal

**Resurvey ALDA** adalah aplikasi web yang mengelola penugasan resurvey nasabah **ALDA** milik PT Suzuki Finance Indonesia. Sistem ini menghubungkan database terkait nomor kontrak kendaraan, nasabah, dan PIC, memungkinkan supervisor (PIC Head) untuk menugaskan, memperbarui, dan memantau penugasan untuk mengelola beban kerja secara real-time. Seluruh interaksi database dieksekusi melalui stored procedure.

---

## Daftar Isi

1. [Konteks Bisnis](#1-konteks-bisnis)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Skema Database](#3-skema-database)
4. [Alur Data End-to-End](#4-alur-data-end-to-end)
5. [Autentikasi & Manajemen Sesi](#5-autentikasi--manajemen-sesi)
6. [Referensi Modul](#6-referensi-modul)
7. [Katalog Stored Procedure](#7-katalog-stored-procedure)
8. [Model Siklus Hidup Task & Status](#8-model-siklus-hidup-task--status)
9. [Normalisasi & Pengayaan Data](#9-normalisasi--pengayaan-data)
10. [Postur Keamanan](#10-postur-keamanan)
11. [Dependensi Teknis](#11-dependensi-teknis)
12. [Kendala Sistem & Modul Stub](#12-kendala-sistem--modul-stub)
13. [Panduan Deployment](#13-panduan-deployment)

---

## 1. Konteks Bisnis

- **Domain Operasional.**
  PT Suzuki Finance Indonesia mengelola portofolio kontrak pembiayaan kendaraan yang memasuki kategori ALDA. Ketika sebuah kontrak memasuki kategori ini, supervisor dapat menugaskan kontrak tersebut kepada PIC yang akan mengunjungi nasabah, memvalidasi informasi di lokasi, dan memberikan laporan hasil penugasan yang dilakukan.

- **Penegakan Proses.**
  Progesi status terdiri dari — `ASSIGNED` → `IN_PROGRESS` → `COMPLETED` — dikelola dalam database melalui eksekusi stored procedure, bukan pemrograman di aplikasi. Metode pengelolaan langsung dalam database memastikan konsistensi dalam sistem.

- **Tata Kelola Terpusat.**
  Supervisor/PIC Head dapat melakukan kontrol penugasan, pemindahan, dan pembatalan melalui website Mobile Collection.

---

## 2. Arsitektur Sistem

- **Model Tiga Layer.**
  Sistem mengimplementasikan arsitektur server-rendered antara lapisan presentasi, aplikasi, dan data. Layer aplikasi PHP untuk manajemen session, validasi input, rendering HTML, dan pengiriman stored procedure; tidak ada logika bisnis yang dieksekusi di sisi browser.

- **Layer Data.**
  Seluruh operasi terkait database `MOBILE_COLLECTION` dieksekusi melalui stored procedure. Layer aplikasi tidak mengeksekusi query terhadap tabel secara langsung.

---

## 3. Skema Database

### 3.1 `MASTER_ALDA` — Direktori Detail Nasabah

Tabel referensi kontrak pembiayaan kendaraan aktif. Kolom `NOMOR_KONTRAK` berfungsi sebagai kunci primer.

| Kolom                         | Tipe            | Keterangan                                              |
| ----------------------------- | --------------- | ------------------------------------------------------- |
| `AREA`                        | `VARCHAR(50)`   | Nama wilayah regional.                                  |
| `BRANCH_ID`                   | `VARCHAR(20)`   | ID cabang dalam detail kontrak.                         |
| `BRANCH_NAME`                 | `VARCHAR(200)`  | Nama cabang dalam detail kontrak.                       |
| `PORTFOLIO`                   | `VARCHAR(20)`   | Klasifikasi (2W/4W).                                    |
| `NOMOR_KONTRAK`               | `VARCHAR(50)`   | Identifikasi utama; Mewakili setiap kontrak pembiayaan. |
| `CUSTOMER_NAME`               | `VARCHAR(300)`  | Nama lengkap nasabah.                                   |
| `GO_LIVE_DATE`                | `DATETIME`      | Tanggal aktivasi kontrak pembiayaan.                    |
| `TANGGAL_BAYAR_ANGS_TERAKHIR` | `DATETIME`      | Tanggal pembayaran angsuran terakhir.                   |
| `MERK_KENDARAAN`              | `VARCHAR(100)`  | Merek kendaraan.                                        |
| `TYPE_KENDARAAN`              | `VARCHAR(200)`  | Model atau varian kendaraan.                            |
| `TAHUN_KENDARAAN`             | `INT`           | Tahun produksi kendaraan.                               |
| `CUSTOMER_PHONE`              | `VARCHAR(50)`   | Nomor telepon nasabah.                                  |
| `LEGAL_ADDRESS`               | `VARCHAR(max)`  | Alamat domisili nasabah.                                |
| `CONTRACT_STATUS`             | `VARCHAR(50)`   | Status kontrak pembiayaan saat ini.                     |
| `AMOUNT_TO_BE_PAID`           | `decimal(18,2)` | Jumlah tagihan yang masih harus dibayar oleh nasabah.   |

---

### 3.2 `MASTER_ALDA_PIC` — Direktori Detail PIC

Tabel referensi seluruh PIC setiap cabang beserta atribut organisasional. Kolom `NIK` berfungsi sebagai kunci primer.

| Kolom              | Tipe           | Keterangan                                     |
| ------------------ | -------------- | ---------------------------------------------- |
| `AREA`             | `VARCHAR(100)` | Nama area operasional PIC.                     |
| `CABANG`           | `VARCHAR(100)` | Nama cabang operasional PIC.                   |
| `BRANCH_ID`        | `VARCHAR(10)`  | ID cabang operasional PIC.                     |
| `NIK`              | `VARCHAR(50)`  | Nomor Induk Karyawan; Identifikasi primer PIC. |
| `NAMA`             | `VARCHAR(200)` | Nama lengkap PIC.                              |
| `JABATAN`          | `VARCHAR(100)` | Jabatan PIC.                                   |
| `PILAR`            | `VARCHAR(100)` | Pilar / divisi organisasional PIC.             |
| `LOKASI_FISIK`     | `VARCHAR(100)` | Lokasi kantor fisik PIC.                       |
| `LOKASI_PEKERJAAN` | `VARCHAR(100)` | Lokasi operasional kerja PIC.                  |
| `IS_ACTIVE`        | `BIT`          | Flag status aktif. Nilai `1`/`0`. Default `1`. |

---

### 3.3 `ALDA_PENUGASAN` — Tabel Penugasan Aktif

Tabel operasional utama yang merepresentasikan penugasan kontrak yang aktif. Setiap row merepresentasikan satu penugasan yang aktif untuk satu kontrak. Memastikan maksimal satu penugasan per nomor kontrak pada waktu bersamaan. Reassignment akan memperbarui row yang ada, bukan menambah row baru; Status sebelumnya dipreservasi dalam `ALDA_PENUGASAN_HISTORY`. Atribut PIC dan data nasabah didenormalisasi ke dalam tabel ini pada saat penugasan.

| Kolom                         | Tipe                   | Keterangan                                                                             |
| ----------------------------- | ---------------------- | -------------------------------------------------------------------------------------- |
| `PENUGASAN_ID`                | `BIGINT`               | Auto-increment oleh basis data.                                                        |
| `CONTRACT_NO`                 | `VARCHAR(50)` `UNIQUE` | Identifikasi utama; Mewakili setiap kontrak pembiayaan.                                |
| `SUBMISSION_ID`               | `BIGINT`               | Identifikasi batch sesuai DATETIME penugasan, diformat sebagai `yyyyMMddHHmmssmmm`.    |
| `STATUS`                      | `VARCHAR(20)`          | Status penugasan; `ASSIGNED`, `IN_PROGRESS`, `COMPLETED`, atau `CANCELLED`.            |
| `ASSIGN_VERSION`              | `INT`                  | Indikator optimistic-concurrency. Default `1`.                                         |
| `NOTES`                       | `VARCHAR(500)`         | Catatan tambahan Supervisor saat pembatalan atau reassignment.                         |
| `PIC_NIK`                     | `VARCHAR(50)`          | NIK PIC yang ditugaskan.                                                               |
| `PIC_NAME`                    | `VARCHAR(200)`         | Nama lengkap PIC yang ditugaskan.                                                      |
| `PIC_JABATAN`                 | `VARCHAR(100)`         | Jabatan PIC yang ditugaskan.                                                           |
| `PIC_PILAR`                   | `VARCHAR(100)`         | Pilar organisasional PIC yang ditugaskan.                                              |
| `PIC_LOKASI_FISIK`            | `VARCHAR(100)`         | Lokasi fisik PIC yang ditugaskan.                                                      |
| `PIC_LOKASI_PEKERJAAN`        | `VARCHAR(100)`         | Lokasi kerja PIC yang ditugaskan.                                                      |
| `PIC_AREA`                    | `VARCHAR(100)`         | Wilayah regional PIC yang ditugaskan.                                                  |
| `PIC_CABANG`                  | `VARCHAR(100)`         | Nama cabang PIC yang ditugaskan.                                                       |
| `PIC_BRANCH_ID`               | `VARCHAR(10)`          | Identifikasi cabang PIC yang ditugaskan.                                               |
| `AREA`                        | `VARCHAR(50)`          | Wilayah kontrak nasabah.                                                               |
| `BRANCH_ID`                   | `VARCHAR(20)`          | Identifikasi cabang kontrak nasabah;.                                                  |
| `BRANCH_NAME`                 | `VARCHAR(200)`         | Nama cabang kontrak nasabah;.                                                          |
| `PORTFOLIO`                   | `VARCHAR(20)`          | Klasifikasi 2W/4W kontrak nasabah;.                                                    |
| `CUSTOMER_NAME`               | `VARCHAR(300)`         | Nama lengkap nasabah.                                                                  |
| `LEGAL_ADDRESS`               | `VARCHAR(max)`         | Alamat legal nasabah.                                                                  |
| `CUSTOMER_PHONE`              | `VARCHAR(50)`          | Nomor telepon nasabah.                                                                 |
| `GO_LIVE_DATE`                | `DATETIME`             | Tanggal aktivasi kontrak.                                                              |
| `TANGGAL_BAYAR_ANGS_TERAKHIR` | `DATETIME`             | Tanggal pembayaran angsuran terakhir.                                                  |
| `MERK_KENDARAAN`              | `VARCHAR(100)`         | Merek kendaraan.                                                                       |
| `TYPE_KENDARAAN`              | `VARCHAR(200)`         | Model kendaraan.                                                                       |
| `TAHUN_KENDARAAN`             | `INT`                  | Tahun produksi kendaraan.                                                              |
| `CONTRACT_STATUS_SNAPSHOT`    | `VARCHAR(50)`          | Status pada `MASTER_ALDA` saat penugasan dilakukan.                                    |
| `AMOUNT_TO_BE_PAID`           | `decimal(18,2)`        | Jumlah tagihan outstanding.                                                            |
| `CREATED_AT`                  | `DATETIME`             | Timestamp pembuatan penugasan; tidak dapat diubah setelah dibuat. Default `GETDATE()`. |
| `CREATED_BY`                  | `VARCHAR(200)`         | Identitas Supervisor yang membuat penugasan.                                           |
| `UPDATED_AT`                  | `DATETIME`             | Timestamp pembaruan penugasan terakhir pada nomor kontrak.                             |
| `UPDATED_BY`                  | `VARCHAR(200)`         | Identitas Supervisor yang memperbarui penugasan.                                       |

---

### 3.4 `ALDA_PENUGASAN_HISTORY`

Tabel catatan kondisi setiap aksi terkait penugasan terhadap nomor kontrak terjadi. Mencakup nilai sebelum dan sesudah perubahan. `CHANGE_TYPE` bernilai `REASSIGN`, `STATUS_CHANGE`, atau `CANCEL`.

| Kolom                         | Tipe            | Keterangan                                                               |
| ----------------------------- | --------------- | ------------------------------------------------------------------------ |
| `HISTORY_ID`                  | `BIGINT`        | Surrogate primary key untuk catatan history.                             |
| `PENUGASAN_ID`                | `BIGINT`        | Referensi foreign key ke data `ALDA_PENUGASAN` yang perbarui.            |
| `CONTRACT_NO`                 | `VARCHAR(50)`   | Identifikasi utama; Mewakili setiap kontrak pembiayaan.                  |
| `CHANGE_TYPE`                 | `VARCHAR(20)`   | `REASSIGN`, `STATUS_CHANGE`, atau `CANCEL`.                              |
| `CHANGE_REASON`               | `VARCHAR(500)`  | Alasan perubahan yang dilakukan Supervisor (Opsional)                    |
| `CHANGED_AT`                  | `DATETIME`      | Timestamp perubahan terkait penugasan. Default `GETDATE()`.              |
| `CHANGED_BY`                  | `VARCHAR(200)`  | Identitas Supervisor yang melakukan perubahan.                           |
| `STATUS_BEFORE`               | `VARCHAR(20)`   | Status penugasan sesaat, sebelum pembaruan data.                         |
| `STATUS_AFTER`                | `VARCHAR(20)`   | Status penugasan yang diterapkan oleh pembaruan data.                    |
| `ASSIGN_VERSION_BEFORE`       | `INT`           | Nilai `ASSIGN_VERSION`, sebelum pembaruan data.                          |
| `ASSIGN_VERSION_AFTER`        | `INT`           | Nilai `ASSIGN_VERSION` setelah pembaruan data.                           |
| `PIC_NIK_BEFORE`              | `VARCHAR(50)`   | NIK PIC pemegang penugasan, sebelum pembaruan data.                      |
| `PIC_NAME_BEFORE`             | `VARCHAR(200)`  | Nama lengkap PIC, sebelum pembaruan data.                                |
| `PIC_JABATAN_BEFORE`          | `VARCHAR(100)`  | Jabatan PIC, sebelum pembaruan data.                                     |
| `PIC_PILAR_BEFORE`            | `VARCHAR(100)`  | Pilar organisasional PIC, sebelum pembaruan data.                        |
| `PIC_LOKASI_FISIK_BEFORE`     | `VARCHAR(100)`  | Lokasi fisik PIC, sebelum pembaruan data.                                |
| `PIC_LOKASI_PEKERJAAN_BEFORE` | `VARCHAR(100)`  | Lokasi kerja PIC, sebelum pembaruan data.                                |
| `PIC_AREA_BEFORE`             | `VARCHAR(100)`  | Wilayah regional PIC, sebelum pembaruan data.                            |
| `PIC_CABANG_BEFORE`           | `VARCHAR(100)`  | Cabang PIC, sebelum pembaruan data.                                      |
| `PIC_NIK_AFTER`               | `VARCHAR(50)`   | NIK PIC yang ditugaskan setelah pembaruan data; `NULL` untuk pembatalan. |
| `PIC_NAME_AFTER`              | `VARCHAR(200)`  | Nama lengkap PIC setelah pembaruan data; `NULL` untuk pembatalan.        |
| `PIC_JABATAN_AFTER`           | `VARCHAR(100)`  | Jabatan PIC, setelah pembaruan data.                                     |
| `PIC_PILAR_AFTER`             | `VARCHAR(100)`  | Pilar organisasional PIC, setelah pembaruan data.                        |
| `PIC_LOKASI_FISIK_AFTER`      | `VARCHAR(100)`  | Lokasi fisik PIC, setelah pembaruan data.                                |
| `PIC_LOKASI_PEKERJAAN_AFTER`  | `VARCHAR(100)`  | Lokasi kerja PIC, setelah pembaruan data.                                |
| `PIC_AREA_AFTER`              | `VARCHAR(100)`  | Wilayah regional PIC, setelah pembaruan data.                            |
| `PIC_CABANG_AFTER`            | `VARCHAR(100)`  | Cabang PIC, setelah pembaruan data.                                      |
| `CONTRACT_STATUS_SNAPSHOT`    | `VARCHAR(50)`   | Status nomor kontrak dari `MASTER_ALDA` saat pembaruan data.             |
| `AMOUNT_TO_BE_PAID`           | `decimal(18,2)` | Jumlah tagihan yang masih harus dibayar oleh nasabah.                    |

---

### 3.5 `ALDA_STATUS_REF` — Referensi Kode Status

Tabel metadata terkait setiap kode status dengan atribut operasional. Stored procedure akan menentukan apakah status mengizinkan pembaruan data lebih lanjut sebelum mengeksekusi operasi.

| Kolom          | Tipe           | Keterangan                                                                                                        |
| -------------- | -------------- | ----------------------------------------------------------------------------------------------------------------- |
| `STATUS_CODE`  | `VARCHAR(20)`  | Identifikasi status yang digunakan dalam `ALDA_PENUGASAN`                                                         |
| `STATUS_LABEL` | `VARCHAR(100)` | Label tampilan status; dikembalikan oleh `SP_ALDA_GET_PENUGASAN` sebagai `ASSIGN_STATUS_LABEL`.                   |
| `DESCRIPTION`  | `VARCHAR(300)` | Deskripsi status dan makna operasionalnya.                                                                        |
| `IS_FINAL`     | `BIT`          | Flag status terminal. Ketika bernilai `1`, stored procedure memblokir perubahan apapun lebih lanjut. Default `0`. |
| `SORT_ORDER`   | `INT`          | Nilai integer untuk pengurutan status pada daftar UI dan laporan. Default `0`.                                    |
| `IS_ACTIVE`    | `BIT`          | Mengontrol apakah kode status tersedia untuk digunakan dalam operasi penugasan. Default `1`.                      |

---

## 4. Alur Data End-to-End

### 4.1 Pembuatan Task — Back-Office

```
Supervisor mengidentifikasi kontrak yang memenuhi syarat untuk penugasan.
    Tidak ada data penugasan aktif, penugasan sebelumnya telah dibatalkan (CANCELLED), atau telah mencapai status terminal (IS_FINAL = 1), adalah kondisi nomor kontrak dapat diberikan penugasan baru.

         │
         ▼

SP_ALDA_TASKLIST_PENUGASAN(@BRANCH_ID, ...)
    Stored procedure mengeksekusi pengambilan data MASTER_ALDA dan LEFT JOIN dengan ALDA_PENUGASAN untuk memastikan ketersediaan penugasan. Mengidentifikasi nomor kontrak tanpa data penugasan, penugasan berstatus CANCELLED atau final, memastikan hanya kontrak yang memenuhi syarat operasional yang dikembalikan ke daftar task supervisor.

         │
         ▼

Supervisor memilih PIC dari SP_ALDA_DROPDOWN_PIC(@BRANCH_ID).
    Stored procedure mengambil data PIC dari MASTER_ALDA_PIC dan mengembalikan atribut lengkap terkait seluruh PIC yang berafiliasi dengan cabang yang ditentukan, memastikan alokasi penugasan yang terkontrol
    dan selaras untuk setiap cabang.

         │
         ▼

SP_ALDA_SUBMIT_ASSIGN(@usercreate, @pic_nik, @nomor_kontrak, @notes)
    Stored procedure menginisiasi alur pengiriman penugasan dan menjalankan validasi terhadap MASTER_ALDA dan MASTER_ALDA_PIC untuk memverifikasi eksistensi kontrak dan registrasi PIC.

         │
         ▼

Tidak Ada Record Penugasan Sebelumnya.
    Apabila tidak ada penugasan sebelumnya dalam ALDA_PENUGASAN, prosedur melakukan INSERT dengan STATUS = 'ASSIGNED' dan ASSIGN_VERSION = 1, menetapkan penugasan awal dan tanggung jawab kontrak untuk PIC.

         │
         ▼

Record Penugasan Non-Final atau CANCELLED.
    Apabila data penugasan yang ada berstatus non-final atau CANCELLED, prosedur menyisipkan data history untuk melakukan UPDATE data pada row yang ada, mereset status dan atribut PIC sesuai kondisi baru.

         │
         ▼

Pembuatan SUBMISSION_ID.
    SUBMISSION_ID dibuat menggunakan GETDATE() yang diformat sebagai yyyyMMddHHmmssmmm dan di-cast ke BIGINT.
```

---

### 4.2 Autentikasi & Pemuatan Dashboard

```
PIC memasukkan NIK dan password melalui halaman login aplikasi.

         │
         ▼

SP_LOGIN_RESURVEY_ALDA(@p_NIK, @p_Password)
    Stored procedure mengeksekusi verifikasi kredensial terhadap MASTER_ALDA_PIC: mengonfirmasi eksistensi NIK, memvalidasi IS_ACTIVE = 1, dan mengautentikasi password terhadap kredensial yang sudah ditetapkan. Eksekusi yang berhasil akan mengembalikan LoginStatus, NIK, dan NAMA.

         │
         ▼

Inisialisasi Sesi PHP.
    Pada LoginStatus = 1, aplikasi menetapkan status sesi terautentikasi dengan menetapkan user_logged_in = true, user_nik, dan user_name sebagai variabel sesi, mempreservasi konteks autentikasi dan mengaktifkan persistensi sesi di seluruh halaman aplikasi.

         │
         ▼

Re-Validasi Sesi dashboard.php.
    Pada setiap pemuatan dashboard, SELECT INLINE dilakukan terkait MASTER_ALDA_PIC untuk validasi ulang sesi. Proses ini mengevaluasi kembali IS_ACTIVE, merefresh user_name dari data terbaru, dan menghapus sesi secara langsung jika IS_ACTIVE = 0, mencegah akses berkelanjutan oleh akun non aktif.

         │
         ▼

SP_ALDA_PIC_TASKLIST_SUMMARY(@PIC_NIK)
    Stored procedure mengeksekusi agregasi terhadap ALDA_PENUGASAN untuk menurunkan beban kerja, mengembalikan jumlah TUGAS_BARU (ASSIGNED), TUGAS_PROSES (IN_PROGRESS), dan TUGAS_BERJALAN (COMPLETED) sebagai nilai badge real-time pada dashboard PIC.
```

---

### 4.3 TASK PROGRESS — ASSIGNED → IN_PROGRESS

```
PIC mengakses halaman Tugas Baru.

         │
         ▼

SP_ALDA_PIC_GET_TASKS(@PIC_NIK, 'ASSIGNED')
    Stored procedure melakukan join ALDA_PENUGASAN ke MASTER_ALDA berdasarkan nomor kontrak dan NIK, mengambil nilai MERK, TYPE, dan TAHUN, menerapkan fallback untuk CUSTOMER_NAME, LEGAL_ADDRESS, CUSTOMER_PHONE, dan AMOUNT_TO_BE_PAID terhadap MASTER_ALDA.

         │
         ▼

PIC mengkonfirmasi penugasan ke tahap proses (POST: action = proses_tugas, penugasan_id = N).
    PHP memvalidasi penugasan_id sebelum stored procedure apapun diinvokasi. Input yang gagal validasi ditolak dengan respons error.

         │
         ▼

SP_ALDA_PIC_UPDATE_STATUS(@PENUGASAN_ID, @PIC_NIK, 'IN_PROGRESS')
    Stored procedure memverifikasi data penugasan dan status PIC melalui pencocokan NIK, operasi tidak akan dijalankan jika STATUS saat ini sudah sama dengan nilai pembaruan. Pada permintaan valid, prosedur akan memasukkan data STATUS_CHANGE ke ALDA_PENUGASAN_HISTORY dan memperbarui STATUS, ASSIGN_VERSION, UPDATED_AT, serta UPDATED_BY di ALDA_PENUGASAN.

         │
         ▼

Jika success, PHP akan melanjutkan penugasan ke halaman Tugas Proses dengan pemberitahuan pop up.
```

---

### 4.4 REASSIGNMENT (MENGGANTI PIC)

```
Supervisor menginisiasi pemindahan PIC untuk penugasan dengan kondisi aktif.

         │
         ▼

SP_ALDA_UPDATE_PIC(@usercreate, @pic_nik_new, @nomor_kontrak, @notes)
    Stored procedure memverifikasi penugasan tersedia dan IS_FINAL dalam ALDA_STATUS_REF = 0, memblokir reassignment data terminal apapun. Prosedur me-resolve set atribut lengkap PIC dari MASTER_ALDA_PIC dan memperbarui seluruh kolom snapshot data nasabah dari MASTER_ALDA.

         │
         ▼

Penyisipan Record History.
    Record REASSIGN ditambahkan ke ALDA_PENUGASAN_HISTORY, mencakup status sebelum perubahan termasuk atribut PIC, status sebelumnya, dan ASSIGN_VERSION.

         │
         ▼

Pembaruan Record Penugasan.
    Tabel ALDA_PENUGASAN diperbarui in-place: seluruh kolom PIC digantikan oleh atribut PIC masuk yang telah di-resolve, seluruh kolom snapshot nasabah direfresh dari MASTER_ALDA, STATUS direset ke 'ASSIGNED', ASSIGN_VERSION diinkrementasi, dan SUBMISSION_ID baru dibuat menggantikan ID sebelumnya.
```

---

### 4.5 CANCELLED (MEMBATALKAN/MENGHAPUS PENUGASAN)

```
Supervisor melakukan pembatalan atau menghapus penugasan aktif.

         │
         ▼

SP_ALDA_CANCEL_ASSIGN(@usercreate, @nomor_kontrak, @cancel_reason)
    Stored procedure mengambil kondisi penugasan saat ini untuk nomor kontrak tujuan dan mengembalikan respons gagal jika tidak ada data aktif, memproses data IS_FINAL dalam tabel ALDA_STATUS_REF untuk memblokir pembatalan penugasan yang telah mencapai status terminal.

         │
         ▼

Penyisipan Record History.
    Record CANCEL disisipkan ke ALDA_PENUGASAN_HISTORY, mencakup status sebelum perubahan dan alasan pembatalan yang disuplai operator, menetapkan entri audit yang lengkap dan dapat dikueri, sebelum pembaruan data status.

         │
         ▼

Pembaruan Record Penugasan.
    ALDA_PENUGASAN diperbarui dengan STATUS = 'CANCELLED' dan alasan pembatalan ditulis ke NOTES (Opsional). Setiap exception dicatat ke dbo.ERROR_LOG, dan dikembalikan sebagai pesan success = 0.
```

---

## 5. Autentikasi & Manajemen Sesi

- **Alur Autentikasi.**
  Autentikasi dieksekusi melalui `SP_LOGIN_RESURVEY_ALDA`. Layer PHP menerima result set dan bertindak berdasarkan integer `LoginStatus`; tidak ada logika kredensial independen yang dieksekusi di lapisan aplikasi. Tahap 1 memverifikasi NIK dalam `MASTER_ALDA_PIC`, mengembalikan `LoginStatus = -1` jika tidak ada data yang cocok. Tahap 2 memverifikasi `IS_ACTIVE = 1`; akun tidak aktif mengembalikan `LoginStatus = -1`. Tahap 3 membandingkan password yang disuplai terhadap string kredensial statis, mengembalikan `LoginStatus = 1` jika sesuai, atau `LoginStatus = 0` jika tidak sesuai.

- **Penetapan Sesi.**
  Pada `LoginStatus = 1`, aplikasi menulis tiga variabel sesi: `$_SESSION['user_logged_in'] = true`, `$_SESSION['user_nik']`, dan `$_SESSION['user_name']`. Setiap halaman akan menjalankan guard session pada awal pemrosesan file, memverifikasi `user_logged_in === true` dan keberadaan `user_nik` sebelum memproses halaman.

- **Re-Validasi Sesi.**
  Halaman dashboard menjalankan `SELECT` inline terhadap `MASTER_ALDA_PIC` pada setiap pemuatan halaman untuk mengonfirmasi status `IS_ACTIVE` PIC terautentikasi terhadap data master terkini. Apabila akun telah dinonaktifkan sejak sesi ditetapkan, sesi akan dihapus dan pengguna diarahkan ke halaman login.

- **Penghapusan Sesi.**
  Logout memanggil `session_unset()` diikuti oleh `session_destroy()` kemudian redirect ke halaman login.

> **Catatan Keamanan.**
> `SP_LOGIN_RESURVEY_ALDA` memvalidasi kredensial terhadap string plaintext hardcoded tanpa penyimpanan kredensial per pengguna, hashing, atau salting. Mekanisme ini memerlukan evaluasi dan peningkatan dalam iterasi berikutnya dari sistem.

---

## 6. Referensi Modul

| File                        | Route                        | Auth Diperlukan | Deskripsi                                                                               |
| --------------------------- | ---------------------------- | --------------- | --------------------------------------------------------------------------------------- |
| `index.php`                 | `/`                          | Tidak           | Redirect entry point. Pengguna terautentikasi diteruskan ke halaman dashboard.          |
| `login.php`                 | `/login.php`                 | Tidak           | Merender formulir login dan memproses pengiriman POST dengan `SP_LOGIN_RESURVEY_ALDA`. |
| `dashboard.php`             | `/dashboard.php`             | Ya              | Menampilkan ringkasan beban kerja dengan `SP_ALDA_PIC_TASKLIST_SUMMARY`.               |
| `tugas-baru.php`            | `/tugas-baru.php`            | Ya              | Menampilkan seluruh task berstatus `ASSIGNED` untuk PIC dengan `SP_ALDA_PIC_GET_TASKS`.    |
| `tugas-proses.php`          | `/tugas-proses.php`          | Ya              | Menampilkan seluruh task berstatus `IN_PROGRESS` untuk PIC dengan `SP_ALDA_PIC_GET_TASKS`. |
| `tugas-sedang-berjalan.php` | `/tugas-sedang-berjalan.php` | Ya              | Modul belum diimplementasikan pada rilis ini.                                           |
| `selesai.php`               | `/selesai.php`               | Ya              | Modul belum diimplementasikan pada rilis ini.                                           |
| `upload.php`                | `/upload.php`                | Ya              | Modul belum diimplementasikan pada rilis ini.                                           |
| `logout.php`                | `/logout.php`                | Tidak           | Memanggil `session_unset()` dan `session_destroy()`.                                    |

---

## 7. Stored Procedure

Seluruh prosedur berada dalam database `MOBILE_COLLECTION`. Prosedur write mengikuti pola : validasi prakondisi, penyisipan data history, kemudian eksekusi pembaruan data. Seluruh operasi write mengembalikan result set `success (bit)` dan `message (varchar)`.

---

### `SP_LOGIN_RESURVEY_ALDA`

| Parameter     | Tipe           | Arah |
| ------------- | -------------- | ---- |
| `@p_NIK`      | `VARCHAR(50)`  | IN   | 
| `@p_Password` | `VARCHAR(255)` | IN   |

**Mengembalikan:** `LoginStatus INT`, `Message VARCHAR(255)`, `NIK VARCHAR(50)`, `NAMA VARCHAR(200)`

Mengeksekusi setiap tahap autentikasi. Mengembalikan `LoginStatus = 1` jika autentikasi sesuai, `LoginStatus = -1` atau `0` dengan `Message` jika autentikasi tidak sesuai.

---

### `SP_ALDA_PIC_TASKLIST_SUMMARY`

| Parameter  | Tipe          | Arah | 
| ---------- | ------------- | ---- | 
| `@PIC_NIK` | `VARCHAR(50)` | IN   | 

**Mengembalikan:** `TUGAS_BARU INT`, `TUGAS_PROSES INT`, `TUGAS_BERJALAN INT`

---

### `SP_ALDA_PIC_GET_TASKS`

| Parameter  | Tipe          | Arah | Keterangan                                                                                                   |
| ---------- | ------------- | ---- | ------------------------------------------------------------------------------------------------------------ |
| `@PIC_NIK` | `VARCHAR(50)` | IN   | NIK PIC yang tasknya sedang diambil.                                                                         |
| `@STATUS`  | `VARCHAR(20)` | IN   | Filter status; harus merupakan nilai valid dari `ALDA_PENUGASAN.STATUS` (contoh: `ASSIGNED`, `IN_PROGRESS`). |

Melakukan join `ALDA_PENUGASAN` ke `MASTER_ALDA` pada `CONTRACT_NO` dan  NIK. Mengambil nilai `MERK_KENDARAAN`, `TYPE_KENDARAAN`, dan `TAHUN_KENDARAAN`. Menerapkan fallback untuk `CUSTOMER_NAME`, `LEGAL_ADDRESS`, `CUSTOMER_PHONE`, dan `AMOUNT_TO_BE_PAID`. Mengembalikan `CREATED_AT` dengan `TANGGAL_ASSIGN` bersama seluruh field task inti termasuk `PENUGASAN_ID`, `CONTRACT_NO`, `STATUS`, `ASSIGN_VERSION`, dan `NOTES`.

---

### `SP_ALDA_PIC_UPDATE_STATUS`

| Parameter       | Tipe          | Arah | Keterangan                                                                                          |
| --------------- | ------------- | ---- | --------------------------------------------------------------------------------------------------- |
| `@PENUGASAN_ID` | `BIGINT`      | IN   | Surrogate key data penugasan yang akan diperbarui ke tahap proses.                                                  |
| `@PIC_NIK`      | `VARCHAR(50)` | IN   | NIK PIC yang akan melakukan proses penugasan; diverifikasi terhadap data penugasan.                     |
| `@NEW_STATUS`   | `VARCHAR(20)` | IN   | Nilai status target;`IN_PROGRESS`. |

**Mengembalikan:** `success (bit)`, `message (varchar)`

Mencocokkan table `ALDA_PENUGASAN` berdasarkan `PENUGASAN_ID` NIK untuk memverifikasi eksistensi data dan kepemilikan PIC secara bersamaan. Operasi akan ditolak jika data tidak ditemukan atau jika status saat ini sudah sama dengan `@NEW_STATUS`. Request yang valid akan menginkrementasi `ASSIGN_VERSION`, memperbarui data `STATUS_CHANGE` ke `ALDA_PENUGASAN_HISTORY` termasuk kondisi sebelum dan sesudah perubahan, dan memperbarui `STATUS`, `ASSIGN_VERSION`, `UPDATED_AT`, serta `UPDATED_BY` dalam `ALDA_PENUGASAN`.
---

### `SP_ALDA_SUBMIT_ASSIGN`

| Parameter        | Tipe           | Arah | Keterangan                                                                |
| ---------------- | -------------- | ---- | ------------------------------------------------------------------------- |
| `@usercreate`    | `VARCHAR(200)` | IN   | Identitas supervisor yang membuat atau memperbarui penugasan.               |
| `@pic_nik`       | `VARCHAR(50)`  | IN   | NIK PIC yang akan menerima penugasan terkait nomor kontrak.                             |
| `@nomor_kontrak` | `VARCHAR(50)`  | IN   | Nomor kontrak dari `MASTER_ALDA` yang akan ditugaskan untuk PIC.                    |
| `@notes`         | `VARCHAR(500)` | IN   | Catatan opsional; default ke `'Assigned via Web'` jika tidak disuplai. |

**Mengembalikan:** `success (bit)`, `message (varchar)`, `submission_id BIGINT`

Memvalidasi eksistensi kontrak dalam `MASTER_ALDA` dan PIC dalam `MASTER_ALDA_PIC`. Membuat `SUBMISSION_ID`, melakukan resolve terkait atribut PIC dan snapshot nasabah secara lengkap, jika tidak ada data sebelumnya akan menyisipkan row baru dengan `STATUS = 'ASSIGNED'` dan `ASSIGN_VERSION = 1`. Apabila data sebelumnya berada dalam status non-final atau `CANCELLED`, akan dimasukkan history `REASSIGN` dan dilakukan UPDATE pada row yang sudah ada. 
---

### `SP_ALDA_CANCEL_ASSIGN`

| Parameter        | Tipe           | Arah | 
| ---------------- | -------------- | ---- | 
| `@usercreate`    | `VARCHAR(200)` | IN   |
| `@nomor_kontrak` | `VARCHAR(50)`  | IN   |
| `@cancel_reason` | `VARCHAR(500)` | IN   |

**Mengembalikan:** `success (bit)`, `message (varchar)`

Mengambil kondisi penugasan saat ini untuk nomor kontrak terkait dan mengembalikan respons gagal jika tidak ada penugasan aktif. Memproses nilai `IS_FINAL` dalam tabel `ALDA_STATUS_REF` untuk memblokir pembatalan penugasan. Jika pembatalan penugasan sukses akan dimasukkan data `CANCEL` ke `ALDA_PENUGASAN_HISTORY`, kemudian menetapkan `STATUS = 'CANCELLED'` dan alasan pembatalan ke `NOTES`.

---

### `SP_ALDA_UPDATE_PIC`

| Parameter        | Tipe           | Arah | 
| ---------------- | -------------- | ---- |
| `@usercreate`    | `VARCHAR(200)` | IN   | 
| `@pic_nik_new`   | `VARCHAR(200)` | IN   |
| `@nomor_kontrak` | `VARCHAR(50)`  | IN   |
| `@notes`         | `VARCHAR(500)` | IN   |

**Mengembalikan:** `success (bit)`, `message (varchar)`

Memvalidasi penugasan yang ada tersedia dan tidak berada dalam status final. Memverifikasi PIC dalam `MASTER_ALDA_PIC`, merefresh seluruh kolom snapshot nasabah dari `MASTER_ALDA`. Membuat `SUBMISSION_ID` baru dari timestamp saat ini. Menyisipkan data history `REASSIGN` mencakup atribut PIC keluar dan masuk. Memperbarui `ALDA_PENUGASAN` dengan seluruh kolom PIC baru dan snapshot nasabah yang direfresh, mereset `STATUS = 'ASSIGNED'`, dan menginkrementasi `ASSIGN_VERSION`.

---

### `SP_ALDA_GET_PENUGASAN`

| Parameter        | Tipe          | Arah | Keterangan                                                                                                       |
| ---------------- | ------------- | ---- | ---------------------------------------------------------------------------------------------------------------- |
| `@BRANCH_ID`     | `VARCHAR(10)` | IN   | Filter cabang wajib; membatasi hasil hanya pada cabang yang ditentukan.                                                |
| `@NOMOR_KONTRAK` | `VARCHAR(50)` | IN   | Filter nomor kontrak opsional; kirim string kosong untuk mengembalikan semua.                                    |
| `@STATUS`        | `VARCHAR(20)` | IN   | Filter status opsional; kirim string kosong untuk mengembalikan semua status.                                    |
| `@PIC_NIK`       | `VARCHAR(50)` | IN   | Filter NIK PIC opsional dengan dot-normalisation; kirim string kosong untuk mengembalikan semua.                 |
| `@DATE_FROM`     | `DATETIME`    | IN   | Batas bawah opsional pada `CREATED_AT`; kirim `NULL` untuk tanpa batas bawah.                                    |
| `@DATE_TO`       | `DATETIME`    | IN   | Batas atas opsional pada `CREATED_AT` (inklusif, via `DATEADD(DAY, 1, …)`); kirim `NULL` untuk tanpa batas atas. |

**Mengembalikan:** Result set data penugasan mencakup seluruh kolom `ALDA_PENUGASAN`, field nasabah yang di-resolve menggunakan `ISNULL` dari `MASTER_ALDA`, serta data dari `ALDA_STATUS_REF`.

---

### `SP_ALDA_TASKLIST_PENUGASAN`

| Parameter          | Tipe          | Arah | Keterangan                                                                                              |
| ------------------ | ------------- | ---- | ------------------------------------------------------------------------------------------------------- |
| `@BRANCH_ID`       | `VARCHAR(10)` | IN   | Filter cabang wajib; membatasi hasil pada cabang yang ditentukan.                                       |
| `@NOMOR_KONTRAK`   | `VARCHAR(50)` | IN   | Filter nomor kontrak opsional; kirim string kosong untuk mengembalikan semua.                           |
| `@CONTRACT_STATUS` | `VARCHAR(20)` | IN   | Filter status kontrak opsional terhadap `MASTER_ALDA.CONTRACT_STATUS`; kirim string kosong untuk semua. |
| `@PORTFOLIO`       | `VARCHAR(20)` | IN   | Filter portofolio opsional; kirim string kosong untuk mengembalikan semua.                              |

**Mengembalikan:** Kolom dari `MASTER_ALDA` untuk kontrak yang memenuhi syarat menerima penugasan baru, diurutkan berdasarkan `NOMOR_KONTRAK`.

Mengkueri `MASTER_ALDA` dengan `LEFT JOIN` ke `ALDA_PENUGASAN` dan `ALDA_STATUS_REF`. Kondisi kelayakan mengembalikan kontrak di mana tidak ada row penugasan, penugasan berstatus `CANCELLED`, atau penugasan membawa `IS_FINAL = 1`. Kontrak dengan penugasan aktif non-final dikecualikan, menegakkan aturan satu-penugasan-aktif-per-kontrak pada lapisan kueri.

---

### `SP_ALDA_RECORD_PENUGASAN`

| Parameter        | Tipe          | Arah | Keterangan                                                                             |
| ---------------- | ------------- | ---- | -------------------------------------------------------------------------------------- |
| `@BRANCH_ID`     | `VARCHAR(10)` | IN   | Filter cabang wajib.                                                                   |
| `@NOMOR_KONTRAK` | `VARCHAR(50)` | IN   | Filter nomor kontrak opsional; kirim string kosong untuk mengembalikan semua.          |
| `@PIC_NIK`       | `VARCHAR(50)` | IN   | Filter NIK PIC opsional dengan dot-normalisation; kirim string kosong untuk semua.     |
| `@DATE_FROM`     | `DATETIME`    | IN   | Batas bawah opsional pada `CREATED_AT`; kirim `NULL` untuk tanpa batas bawah.          |
| `@DATE_TO`       | `DATETIME`    | IN   | Batas atas opsional pada `CREATED_AT` (inklusif); kirim `NULL` untuk tanpa batas atas. |

**Mengembalikan:** Kolom laporan operasional terkonsolidasi mencakup `NOMOR_KONTRAK`, `PIC_NIK`, `PIC` (nama), `JABATAN_PIC`, `LOKASI_PIC`, `CABANG`, `PORTFOLIO`, `UNIT` (jenis kendaraan), `AMOUNT_TO_BE_PAID`, `CUSTOMER_NAME`, `ASSIGN_STATUS`, `CREATED_DATE` (diformat sebagai `DD-MM-YYYY` via `CONVERT(VARCHAR(10), …, 105)`), dan `CREATED_BY`. Penugasan berstatus `CANCELLED` dikecualikan dari hasil.

---

### `SP_ALDA_DROPDOWN_PIC`

| Parameter    | Tipe          | Arah | Keterangan                                                                        |
| ------------ | ------------- | ---- | --------------------------------------------------------------------------------- |
| `@BRANCH_ID` | `VARCHAR(10)` | IN   | Identifikasi cabang; membatasi result set pada PIC yang termasuk cabang tersebut. |

**Mengembalikan:** `VALUE` (`NIK`), `DATA_PIC` (`NAMA`), `JABATAN`, `LOKASI` (`LOKASI_FISIK`) untuk seluruh PIC yang cocok dengan cabang. Tidak ada filter status aktif yang diterapkan dalam prosedur ini.

---

## 8. Model Siklus Hidup Task & Status

- **Gambaran State Machine.** Task bergerak melalui state machine unidireksional yang terdefinisi. Logika transisi ditegakkan di lapisan basis data; lapisan aplikasi mengirimkan nilai status target dan bertindak berdasarkan respons stored procedure. Flag `IS_FINAL` dalam `ALDA_STATUS_REF` adalah guard otoritatif untuk seluruh operasi pembaruan data — `SP_ALDA_CANCEL_ASSIGN`, `SP_ALDA_UPDATE_PIC`, dan `SP_ALDA_SUBMIT_ASSIGN` mengonsultasi flag ini sebelum mengeksekusi operasi tulis apapun.

```
  MASTER_ALDA (tanpa penugasan aktif)
            │
            │  SP_ALDA_SUBMIT_ASSIGN
            ▼
       ┌──────────┐
       │ ASSIGNED │ ◄─── SP_ALDA_SUBMIT_ASSIGN  (penugasan ulang setelah CANCELLED)
       │          │ ◄─── SP_ALDA_UPDATE_PIC     (transfer PIC; direset ke ASSIGNED)
       └────┬─────┘
            │  SP_ALDA_PIC_UPDATE_STATUS('IN_PROGRESS')
            │  [aksi PIC via tugas-baru.php]
            ▼
     ┌─────────────┐
     │ IN_PROGRESS │
     └──────┬──────┘
            │  SP_ALDA_PIC_UPDATE_STATUS('COMPLETED')
            │  [aksi back-office atau tugas-proses.php di rilis mendatang]
            ▼
      ┌───────────┐
      │ COMPLETED │  IS_FINAL = 1 · Tidak ada transisi lebih lanjut yang diizinkan.
      └───────────┘

  Dari ASSIGNED atau IN_PROGRESS:
            │  SP_ALDA_CANCEL_ASSIGN
            ▼
      ┌───────────┐
      │ CANCELLED │  IS_FINAL = 0 · Kontrak memenuhi syarat untuk penugasan ulang
      └───────────┘                via SP_ALDA_SUBMIT_ASSIGN.
```

- **Desain Status CANCELLED.** `CANCELLED` membawa `IS_FINAL = 0` secara by design, mengizinkan kontrak untuk memasuki kembali alur penugasan tanpa memerlukan koreksi data manual. UI PIC saat ini hanya mengekspos transisi `ASSIGNED → IN_PROGRESS`; jalur `IN_PROGRESS → COMPLETED` sepenuhnya didukung di lapisan basis data tetapi belum memiliki UI yang bersesuaian pada rilis ini.

### Tabel Referensi Status

| `STATUS_CODE` | `STATUS_LABEL`  | `IS_FINAL` | Keterangan                                                       |
| ------------- | --------------- | ---------- | ---------------------------------------------------------------- |
| `ASSIGNED`    | Tugas Baru      | `0`        | Status awal setelah penugasan atau penugasan ulang.              |
| `IN_PROGRESS` | Sedang Diproses | `0`        | PIC telah menerima penugasan dan sedang aktif memproses task.    |
| `COMPLETED`   | Selesai         | `1`        | Status terminal; tidak ada transisi lebih lanjut yang diizinkan. |
| `CANCELLED`   | Dibatalkan      | `0`        | Contract ID tetap memenuhi syarat untuk penugasan ulang.         |

---

## 9. Normalisasi & Pengayaan Data

- **Normalisasi NIK PIC.** Nilai NIK dapat tersimpan dengan atau tanpa pemisah titik (contoh: `123.456.789` versus `123456789`). Seluruh stored procedure yang memfilter atau membandingkan nilai NIK menerapkan `REPLACE(NIK, '.', '')` pada nilai tersimpan maupun parameter yang disuplai sebelum perbandingan. Normalisasi ini diterapkan secara konsisten di seluruh prosedur untuk mengeliminasi ketidakcocokan false yang disebabkan oleh inkonsistensi format pada data sumber atau input pengguna.

- **Komposit Kendaraan.** Deskripsi kendaraan yang dirender pada kartu task dihitung saat query oleh `SP_ALDA_PIC_GET_TASKS` dengan menggabungkan `MERK_KENDARAAN`, `TYPE_KENDARAAN`, dan `TAHUN_KENDARAAN` dari `ALDA_PENUGASAN`. Hasilnya adalah label kendaraan yang dapat dibaca manusia dengan spasi yang tepat (contoh: `Suzuki Ertiga 2021`). Layer rendering PHP menerapkan guard sekunder — `trim((string) $task['KENDARAAN']) !== ''` — sebelum merender row kendaraan, menekan output kosong jika ketiga kolom sumber bernilai `NULL`.

- **Fallback NULL.** Di mana kolom snapshot `ALDA_PENUGASAN` bernilai `NULL` — baik karena penugasan mendahului strategi denormalisasi maupun karena kolom tidak terisi saat penyisipan — `SP_ALDA_PIC_GET_TASKS` me-resolve nilai live dari `MASTER_ALDA` secara transparan. Hal ini memastikan kartu task selalu merender informasi lengkap tanpa memerlukan backfill data historis.

- **Format Mata Uang.** Helper PHP `formatRupiah()` mengkonversi nilai `decimal(18,2)` basis data mentah menjadi string tampilan Rupiah Indonesia. Transformasi ini diterapkan di lapisan presentasi saja; nilai numerik mentah dipreservasi secara terpisah dalam payload JSON yang disematkan pada atribut `onclick` setiap kartu task, memastikan tampilan modal konsisten dengan tampilan kartu tanpa menerbitkan database call kedua.

- **Penanganan DATETIME.** Ekstensi `sqlsrv` mengembalikan kolom SQL `DATETIME` sebagai objek PHP `DateTime`. Seluruh kode template menerapkan guard `instanceof DateTime` sebelum memanggil `->format()` dan melakukan fallback ke `'-'` ketika nilai kolom `NULL`, mencegah fatal error pada data dengan timestamp yang tidak tersedia.

---

## 10. Postur Keamanan

- **Validasi Input.** Seluruh input yang dikirimkan melalui POST divalidasi di lapisan aplikasi sebelum disuplai ke stored procedure. Untuk nilai numerik (seperti `penugasan_id`), validasi menggunakan `ctype_digit()` dikombinasikan dengan pemeriksaan positif. Input yang gagal validasi ditolak secara langsung tanpa mencapai lapisan basis data.

- **Eksposur Error.** Stored procedure mengembalikan pesan error yang deskriptif secara kontekstual melalui field `message` pada result set. Layer aplikasi meneruskan pesan ini ke pengguna; tidak ada stack trace, detail skema basis data, atau informasi sistem internal yang diekspos pada output yang menghadap pengguna.

- **Audit Trail.** Setiap pembaruan data pada `ALDA_PENUGASAN` — termasuk penugasan baru, reassignment, perubahan status, dan pembatalan — menghasilkan data pre-mutation yang tidak dapat diubah dalam `ALDA_PENUGASAN_HISTORY`. Record ini mencakup identitas operator, timestamp, dan kondisi sebelum serta sesudah perubahan.

- **Pencatatan Error.** Exception yang tidak tertangani dalam stored procedure dicatat ke `dbo.ERROR_LOG` sebelum mengembalikan respons kegagalan yang terkontrol. Mekanisme ini memastikan kegagalan operasional dapat didiagnosis tanpa menginterupsi alur kerja pengguna.

- **Keterbatasan Kredensial.** `SP_LOGIN_RESURVEY_ALDA` memvalidasi kredensial terhadap string plaintext hardcoded, tanpa hashing atau salting per pengguna. Implementasi ini merupakan risiko keamanan yang diketahui dan harus ditangani dalam iterasi berikutnya melalui implementasi mekanisme hash kredensial yang aman.

---

## 11. Dependensi Teknis

| Komponen             | Versi / Spesifikasi  | Keterangan                                                                                                       |
| -------------------- | -------------------- | ---------------------------------------------------------------------------------------------------------------- |
| PHP                  | 8.3.29               | Runtime lapisan aplikasi.                                                                                        |
| Ekstensi `sqlsrv`    | Kompatibel PHP 8.x   | Driver Microsoft resmi untuk koneksi SQL Server dari PHP; mengembalikan `DATETIME` sebagai objek PHP `DateTime`. |
| SQL Server           | 2008 atau lebih baru | Basis data operasional; seluruh prosedur kompatibel dengan T-SQL 2008.                                           |
| Vanilla JavaScript   | ES5+                 | Digunakan secara minimal di sisi klien untuk interaksi modal dan guard pengiriman formulir.                      |
| Bootstrap (opsional) | Sesuai implementasi  | Framework CSS untuk styling antarmuka front-end.                                                                 |

---

## 12. Kendala Sistem & Modul Stub

- **Transisi COMPLETED.** Jalur `IN_PROGRESS → COMPLETED` didukung penuh di lapisan basis data via `SP_ALDA_PIC_UPDATE_STATUS`, namun tidak ada UI yang bersesuaian pada rilis ini. `tugas-proses.php` saat ini dirender sebagai read-only. Implementasi transisi ini dijadwalkan untuk rilis berikutnya.

- **Modul Stub.** Tiga modul — `tugas-sedang-berjalan.php`, `selesai.php`, dan `upload.php` — terdaftar dalam navigasi aplikasi tetapi belum diimplementasikan pada rilis ini. Modul-modul ini berfungsi sebagai placeholder dan tidak mengeksekusi logika bisnis maupun interaksi basis data apapun.

- **Kompatibilitas SQL Server 2008.** Seluruh stored procedure dirancang untuk kompatibilitas dengan SQL Server 2008 T-SQL. `TRY_CAST` dan `TRY_CONVERT` tidak tersedia; validasi numerik menggunakan `ISNUMERIC` atau guard setara. CTE dan window function harus diverifikasi ketersediaannya sebelum digunakan dalam ekstensi query di masa mendatang.

- **Filter Status pada `SP_ALDA_DROPDOWN_PIC`.** Prosedur ini saat ini tidak menerapkan filter `IS_ACTIVE` terhadap data PIC. Apabila ada kebutuhan untuk membatasi dropdown hanya pada PIC aktif, filter ini perlu ditambahkan secara eksplisit.

---

## 13. Panduan Deployment

- **Prasyarat Server.** Web server dengan PHP 8.3.29 dan ekstensi `sqlsrv` yang terkonfigurasi wajib tersedia sebelum deployment. Konektivitas ke instance SQL Server 2008 (atau lebih baru) harus diverifikasi dari host server sebelum aplikasi dijalankan.

- **Konfigurasi Koneksi Basis Data.** String koneksi, kredensial basis data, dan nama database `MOBILE_COLLECTION` harus dikonfigurasi melalui mekanisme konfigurasi eksternal (variabel lingkungan atau file konfigurasi yang dikecualikan dari version control). Nilai-nilai ini tidak boleh dihardcode dalam source code aplikasi.

- **Deployment Stored Procedure.** Seluruh stored procedure dalam katalog §7 harus dieksekusi terhadap database `MOBILE_COLLECTION` sebelum aplikasi diaktifkan. Urutan deployment harus memperhatikan dependensi referensial; tabel `ALDA_STATUS_REF` harus terisi dengan keempat kode status yang valid sebelum operasi penugasan pertama dilakukan.

- **Inisialisasi Data Referensi.** Tabel `ALDA_STATUS_REF` harus memuat keempat row kode status (`ASSIGNED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`) dengan nilai `IS_FINAL` dan `IS_ACTIVE` yang benar sebelum sistem dioperasikan. Ketidakhadiran row apapun dalam tabel ini akan menyebabkan stored procedure memblokir operasi yang seharusnya valid.

- **Pengujian Koneksi.** Setelah deployment, verifikasi koneksi basis data, eksekusi stored procedure autentikasi, dan alur penugasan minimal (submit → cancel) harus dijalankan dalam lingkungan staging sebelum sistem dipromosikan ke produksi.

- **Pemantauan Error.** Tabel `dbo.ERROR_LOG` di database `MOBILE_COLLECTION` harus dipantau secara berkala oleh tim operasional untuk mengidentifikasi kegagalan stored procedure yang tidak tampak pada antarmuka pengguna tetapi dapat mengindikasikan inkonsistensi data atau kondisi edge case yang belum tertangani.
