# Robotiku — Landing CMS API

Dokumentasi untuk tim frontend landing page **robotiku.id**. Konten halaman bersifat **dinamis** (CMS): di-fetch dari API, bukan hardcode. Panel internal (Super Admin/Admin) mengubah konten lewat endpoint terproteksi.

- **Base URL (produksi):** `https://api.robotiku.id/api/v1`
- **Base URL (lokal):** `http://localhost:8000/api/v1`
- **Spec OpenAPI:** `openapi.yaml` (import ke Swagger UI / Postman / Insomnia)

---

## 1. Format response

Semua endpoint membungkus response dengan envelope yang sama:

```json
{ "status": true, "data": <payload>, "message": "..." }
```

- `status`: `true` sukses, `false` gagal.
- `data`: isi sebenarnya (bisa object / array / null).
- `message`: pesan singkat (boleh ditampilkan ke user).

Error validasi (422) memakai format Laravel:

```json
{
  "message": "The content.title field is required.",
  "errors": { "content.title": ["The content.title field is required."] }
}
```

---

## 2. Autentikasi

Endpoint **GET bersifat publik** (tanpa token) — itu yang dipakai frontend untuk render.

Endpoint **PUT / upload butuh token** Super Admin / Admin:

1. `POST /auth/login` dengan `{ "email", "password" }` → dapat `data.token`.
2. Kirim di setiap request admin: `Authorization: Bearer <token>`.

Token kedaluwarsa 120 menit. Role selain `admin`/`super_admin` → `403`.

---

## 3. Daftar section

| Section        | Wajib di-render | Keterangan singkat                          |
|----------------|-----------------|---------------------------------------------|
| `navbar`       | ya              | Menu navigasi + label tombol CTA            |
| `hero`         | ya              | Judul utama, subjudul, CTA, gambar, statistik |
| `mitra`        | opsional        | Caption baris logo mitra                    |
| `about`        | ya              | Profil + paragraf + highlight               |
| `programs`     | ya              | Kartu program berjenjang                    |
| `achievements` | opsional        | Prestasi/kompetisi                          |
| `testimonials` | opsional        | Testimoni orang tua                         |
| `gallery`      | opsional        | Galeri bento (foto + tile teks)             |
| `cta`          | ya              | Ajakan mitra / free trial + kontak admin    |
| `contact`      | ya              | Alamat, kontak, sosial media (footer)       |

> Section di luar daftar ini → `404 Section tidak dikenal`.

---

## 4. Endpoint

### 4.1 GET `/landing` — semua section

Dipakai sekali saat load halaman. Mengembalikan map `{ section: content }`.

```bash
curl https://api.robotiku.id/api/v1/landing
```

```json
{
  "status": true,
  "message": "Konten landing page.",
  "data": {
    "hero": { "title": "Belajar Robotik Jadi Seru!", "...": "..." },
    "programs": { "title": "...", "items": [ ... ] }
  }
}
```

### 4.2 GET `/landing/{section}` — satu section

```bash
curl https://api.robotiku.id/api/v1/landing/hero
```

```json
{ "status": true, "message": "Konten section.", "data": { "section": "hero", "content": { "title": "..." } } }
```

### 4.3 PUT `/landing/{section}` — update (admin)

Body: `{ "content": { ... } }` sesuai skema section. Server **memvalidasi** sebelum menyimpan.

```bash
curl -X PUT https://api.robotiku.id/api/v1/landing/hero \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{ "content": { "title": "Judul Baru", "subtitle": "..." } }'
```

### 4.4 POST `/landing-upload` — upload gambar (admin)

`multipart/form-data`, field `image` (JPG/PNG, maks 5MB). Dikonversi ke WebP. Pakai `data.url` untuk mengisi field `*_url` (mis. `hero.image_url`).

```bash
curl -X POST https://api.robotiku.id/api/v1/landing-upload \
  -H "Authorization: Bearer <token>" \
  -F "image=@hero.jpg"
```

```json
{ "status": true, "message": "Gambar terunggah.", "data": { "url": ".../storage/landing/abc.webp", "path": "landing/abc.webp" } }
```

**Alur ganti gambar:** upload → ambil `url` → PUT section dengan field gambar diisi `url` tsb.

### 4.5 GET `/artikel` — daftar artikel (publik, paginated)

Dipakai landing page untuk section **"Artikel & Tips"** dan halaman `/artikel`. Hanya artikel berstatus `publish`.

Query opsional: `category`, `per_page` (default 9).

```bash
curl "https://api.robotiku.id/api/v1/artikel?per_page=3"
```

```json
{
  "status": true,
  "message": "Artikel publik.",
  "data": {
    "current_page": 1, "per_page": 3, "total": 12,
    "data": [
      {
        "id": 1,
        "title": "Kenapa Anak Perlu Belajar Coding Sejak Dini?",
        "slug": "kenapa-anak-perlu-belajar-coding",
        "category": "Tips Belajar",
        "cover_url": "https://api.robotiku.id/storage/articles/abc.webp",
        "published_at": "2026-06-12T08:00:00.000000Z"
      }
    ]
  }
}
```

> Data list ada di `data.data` (array), info paginasi di `data.current_page`, `data.total`, dst.

### 4.6 GET `/artikel/{slug}` — detail artikel (publik)

```bash
curl https://api.robotiku.id/api/v1/artikel/kenapa-anak-perlu-belajar-coding
```

```json
{
  "status": true,
  "message": "Detail artikel.",
  "data": {
    "id": 1, "title": "...", "slug": "...", "category": "Tips Belajar",
    "cover_url": "...", "published_at": "...",
    "content": "<p>Isi artikel berupa HTML dari rich text editor...</p>"
  }
}
```

`content` berupa **HTML** — render dengan sanitasi yang aman di sisi FE. Slug tidak ditemukan / draft → `404`.

---

## 5. Skema tiap section

Batas panjang teks (`maxLength`) divalidasi server. Field bertanda **(wajib)** harus ada saat PUT.

### navbar
```json
{
  "links": [ { "label": "Tentang", "href": "#tentang" } ],   // (wajib) array link
  "cta_label": "Daftar"
}
```

### hero
```json
{
  "badge": "#1 Kelas Robotik Anak",
  "title": "Belajar Robotik Jadi Seru!",                     // (wajib)
  "subtitle": "Kelas robotika interaktif untuk anak.",
  "primary_cta":   { "label": "Daftar Sekarang", "href": "/daftar" },
  "secondary_cta": { "label": "Lihat Program",  "href": "#program" },
  "image_url": "/storage/landing/hero.webp",
  "stat": { "value": "500+", "label": "Siswa Telah Bergabung" }
}
```

### mitra
```json
{ "caption": "Dipercaya oleh Mitra Sekolah" }
```

### about
```json
{
  "badge": "Mengenal RobotiKU",
  "title": "Bermain Sambil Mengasah Logika",                 // (wajib)
  "paragraphs": [ "Paragraf 1...", "Paragraf 2..." ],         // (wajib) array string
  "highlights": [ { "icon": "puzzle", "title": "Hands-on", "desc": "Praktek langsung" } ]
}
```

### programs
```json
{
  "title": "Program Pilihan Untuk Setiap Usia",              // (wajib)
  "subtitle": "Kurikulum berjenjang.",
  "items": [                                                  // (wajib) min 1
    {
      "id": "robo-kids",                                      // (wajib)
      "icon": "bot",                                          // (wajib) nama ikon
      "age": "Usia 5-7 Tahun",
      "title": "Robo Kids",                                   // (wajib)
      "desc": "Pengenalan dasar robotika tanpa layar.",
      "points": [ "Motorik halus", "Pengenalan pola" ],
      "featured": false
    }
  ]
}
```

### achievements
```json
{
  "title": "Kompetisi dan Penghargaan",                      // (wajib)
  "desc": "Siswa kami rutin meraih prestasi...",
  "items": [ { "icon": "trophy", "title": "Juara 1 ...", "desc": "..." } ]
}
```

### testimonials
```json
{
  "title": "Apa Kata Mereka",                                // (wajib)
  "desc": "Pengalaman orang tua & siswa.",
  "items": [
    { "name": "Ibu Budi", "role": "Orang tua Robo Kids", "initials": "IB", "rating": 5, "text": "..." }
  ]
}
```
> `rating` angka 0–5 (boleh desimal, mis. 4.5).

### gallery
```json
{
  "title": "Keseruan di Kelas",                              // (wajib)
  "desc": "Intip keseruan anak merakit robot.",
  "main": { "image_url": "/storage/landing/g1.webp", "caption": "Kerja Sama Tim" },
  "tiles": [
    { "type": "text",  "title": "Ide Kreatif" },
    { "type": "image", "image_url": "/storage/landing/g2.webp" },
    { "type": "text",  "title": "Kompetisi Tahunan", "desc": "Wadah unjuk gigi." }
  ]
}
```
> Tiap tile `type` = `image` atau `text`.

### cta
```json
{
  "title": "Siap Memulai Petualangan?",                      // (wajib)
  "desc": "Pilih jalur yang sesuai.",
  "partner": { "title": "Bergabung Menjadi Mitra", "desc": "..." },
  "trial":   { "title": "Daftar Free Trial", "desc": "..." },
  "admins": [
    { "label": "Admin Jabodetabek", "phone": "6281234567890" },
    { "label": "Admin Pontianak",   "phone": "6281234567891" }
  ]
}
```
> `phone` format internasional untuk link wa.me (`https://wa.me/{phone}`).

### contact
```json
{
  "tagline": "Membangun generasi masa depan...",
  "address": "Jl. Robotika No. 123, Jakarta Selatan",
  "phone": "+62 812 3456 7890",
  "email": "halo@robotiku.id",
  "whatsapp": "6281234567890",
  "socials": [
    { "type": "facebook",  "url": "https://facebook.com/robotiku" },
    { "type": "instagram", "url": "https://instagram.com/robotiku" }
  ]
}
```

---

## 6. Catatan untuk frontend

- **Render dari API**, jangan hardcode. Cache hasil `GET /landing` (mis. ISR/SWR) lalu revalidate berkala.
- **Ikon** (`icon`, `socials[].type`) berupa string nama — petakan di sisi FE ke komponen ikon kalian (lucide/react-icons/dll).
- **Gambar** bisa berupa path relatif (`/images/...` dari aset FE) atau URL hasil upload (`/storage/landing/...webp`). Tangani keduanya.
- **Field opsional bisa kosong/absen** — selalu pakai fallback aman saat render.
- **Artikel** bukan bagian dari section CMS — datang dari endpoint terpisah `GET /artikel` & `GET /artikel/{slug}` (lihat bagian 4.5–4.6). Konten CMS `programs/hero/dll` statis-terstruktur, artikel dinamis-paginated.

---

## 7. Ringkasan endpoint

| Method | Endpoint                | Auth        | Untuk                                  |
|--------|-------------------------|-------------|----------------------------------------|
| GET    | `/landing`              | —           | Semua konten section landing           |
| GET    | `/landing/{section}`    | —           | Satu section                           |
| PUT    | `/landing/{section}`    | Admin       | Ubah konten section                    |
| POST   | `/landing-upload`       | Admin       | Upload gambar → URL                     |
| GET    | `/artikel`              | —           | Daftar artikel publish (paginated)     |
| GET    | `/artikel/{slug}`       | —           | Detail artikel                         |

---

*Versi 1.1 — disusun untuk handoff ke tim frontend landing page. Pertanyaan teknis: dev@robotiku.id.*
