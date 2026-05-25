# Resurvey Alda - PHP Application

## 🏗️ Struktur Aplikasi

```
public/
├── assets/
│   └── css/
│       └── styles.css        # File CSS global dengan design tokens lengkap
├── index.php                 # Halaman Login
├── dashboard.php             # Halaman Dashboard
├── tugas-baru.php            # Halaman Penambahan Penugasan
├── tugas-proses.php          # Halaman Proses Penugasan
├── tugas-sedang-berjalan.php # Halaman Penugasan Aktif
├── upload.php                # Halaman Upload
├── selesai.php               # Halaman Selesai
└── logout.php                # Handler Logout
```

### Colour Palette

- Primary Dark: #03045E (Navbar)
- Primary Base: #003566 (Background halaman)
- Primary Mid: #0077B6 (Elemen interaktif)
- Primary Light: #00B4D8 (Focus ring, accent)
- Accent Gold: #FFD60A (CTA utama)
- Surface White: #FFFFFF (Card, input)
- Text Primary: #1A1A2E (Teks utama)
- Text Muted: #6B7280 (Placeholder, label sekunder)
- Text on Dark: #FFFFFF (Teks di atas dark)

### Typography

- Poppins SemiBold 600: Heading, nama aplikasi
- Inter: Body text, label, button
- Ukuran: 26px (brand), 20px (heading), 18px (navbar), 14-15px (body/button)

### Spacing

- Kelipatan 8px untuk margin, padding, gap
- Card padding: 32px
- Border radius card: 20px
- Border radius input/button: 12px
- Input/button height: 52px

### Shadow & Elevation

- Card: `0px 20px 60px rgba(0,0,0,0.25)`
- Button: `0px 4px 16px rgba(0,0,0,0.12)`
- FAB: `0px 8px 24px rgba(0,0,0,0.20)`
- Focus ring: `0px 0px 0px 3px rgba(0,180,216,0.30)`

### php -S localhost:8000