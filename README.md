# MAZ Ujian Online

Sistem ujian online berbasis web untuk sekolah. Dibangun dengan PHP native + JSON database.

## Fitur

### Panel Guru/Admin
- **Dashboard** — Statistik siswa, soal, ujian, nilai, rata-rata
- **Soal** — CRUD soal dengan folder per ujian, import/export format Aiken
- **Nilai** — Tabel nilai siswa, export CSV
- **Monitor** — Log kecurangan (pindah tab, split screen, dll)
- **Media** — Upload gambar (jpg/png/gif/webp), audio (mp3/wav/ogg), video (mp4/webm/mkv)
- **Siswa** — CRUD siswa, import CSV
- **Import** — Import soal (Aiken), siswa (CSV), guru (CSV)
- **Pengaturan** — Timer, anti-cheat, acak soal
- **Guru** — Kelola akun guru (admin only)

### Panel Siswa
- Login dengan NIS + password
- Pilih ujian dari daftar aktif
- Jawab soal dengan timer per soal
- Media di pertanyaan dan opsi jawaban (gambar, audio, video)
- Hasil ujian langsung muncul

### Anti-Cheat
- Deteksi pindah tab
- Deteksi split screen
- Blokir copy/paste
- Blokir DevTools
- Blokir translate/klik kanan
- Batas maksimal pindah tab & split screen

## Teknologi

- **Backend**: PHP 8.x (native, tanpa framework)
- **Database**: JSON files (`data/*.json`)
- **Frontend**: HTML + CSS + Vanilla JS
- **Icons**: Lucide Icons
- **Font**: Inter (Google Fonts)

## Instalasi

```bash
# Clone repo
git clone https://github.com/treximaru/ujian-2026.git
cd ujian-2026

# Jalankan server
php -S 0.0.0.0:8080 -t .

# Buka browser
http://localhost:8080/
```

## Akun Default

| Role | Username | Password |
|------|----------|----------|
| Admin | maz | admin123 |
| Guru | siti | guru123 |
| Guru | budi | *(bcrypt, lupa)* |
| Siswa | 25317567 | 12345 |

## Struktur Folder

```
ujian-2026/
├── index.html          # Halaman siswa (ujian)
├── ddg-gate.php        # DuckDuckGo browser gate
├── router.php          # PHP router (cache headers)
├── guru/
│   └── index.php       # Panel guru + API (1 file!)
├── data/
│   ├── guru.json       # Data guru
│   ├── siswa.json      # Data siswa
│   ├── ujian.json      # Data ujian
│   ├── soal.json       # Data soal
│   ├── nilai.json      # Data nilai
│   ├── settings.json   # Pengaturan
│   └── log_curang.json # Log kecurangan
├── js/
│   └── lucide.min.js   # Icon library
└── guru/uploads/       # File media
    ├── images/
    ├── audio/
    └── video/
```

## Format Soal (Aiken)

```
1. What is the capital of France?
a. London
b. Berlin
c. Paris
d. Madrid
e. Rome
ANSWER: C
```

## Media di Soal

Gunakan tag berikut di pertanyaan atau opsi jawaban:

- `[img:uploads/images/namafile.png]` — Gambar
- `[audio:uploads/audio/namafile.mp3]` — Audio
- `[video:uploads/video/namafile.mp4]` — Video

## Client Android

Untuk pengalaman ujian yang lebih aman di HP, gunakan aplikasi Android **ExamApp**:

- **Repo**: https://github.com/treximaru/ExamApp
- **Fitur**: VPN blocking, app lock, anti-screenshot, anti-navigation
- **Alur**: App blokir semua internet → load website ujian → jika siswa keluar → terkunci otomatis

## Lisensi

Personal use. dibuat untuk kebutuhan sekolah MAZ.
