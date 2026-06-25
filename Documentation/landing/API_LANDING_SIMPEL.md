# Dokumentasi API Landing Page (Versi Simpel)

Dokumen ini menjelaskan REST API untuk halaman landing **robotiku.id**: konten dinamis (CMS) dan artikel publik. Dibangun dengan Laravel.

## Base URL
`/api/v1`

Semua endpoint relatif terhadap base URL di atas.
Contoh lokal: `http://localhost:8000/api/v1` · Produksi: `https://api.robotiku.id/api/v1`

## Autentikasi & Otorisasi
- Endpoint **GET** bersifat **publik** (tanpa token) — dipakai frontend untuk merender halaman.
- Endpoint **PUT** dan **upload** butuh login role `admin` / `super_admin`:
  1. `POST /auth/login` → ambil `data.token`.
  2. Kirim header `Authorization: Bearer <token>` di tiap request admin.
- **Gagal auth**: `401 Unauthorized`. **Role tidak sesuai**: `403 Forbidden`. **Validasi gagal**: `422 Unprocessable Entity`.

## Format Response Umum
Semua response dibungkus envelope berikut:

```json
{
  "status": true,
  "data": {},
  "message": "Pesan singkat"
}
```

Format error validasi (422):

```json
{
  "message": "The content.title field is required.",
  "errors": {
    "content.title": ["The content.title field is required."]
  }
}
```

---

## 1. Konten Landing (CMS)

Section yang tersedia: `navbar`, `hero`, `mitra`, `about`, `programs`, `achievements`, `testimonials`, `gallery`, `cta`, `contact`.

### `GET /landing`
Mengambil seluruh konten landing sebagai map `{ section: content }`.

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Konten landing page.",
  "data": {
    "hero": {
      "badge": "#1 Kelas Robotik Anak",
      "title": "Belajar Robotik Jadi Seru!",
      "subtitle": "Kelas robotika interaktif untuk anak.",
      "primary_cta": { "label": "Daftar Sekarang", "href": "/daftar" },
      "image_url": "/storage/landing/hero.webp",
      "stat": { "value": "500+", "label": "Siswa Telah Bergabung" }
    },
    "programs": { "title": "Program Pilihan", "items": [] }
  }
}
```

### `GET /landing/:section`
Mengambil satu section.

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Konten section.",
  "data": {
    "section": "hero",
    "content": { "title": "Belajar Robotik Jadi Seru!" }
  }
}
```
*Section tidak dikenal → `404 Not Found`.*

### `PUT /landing/:section`
Memperbarui konten satu section. Butuh role admin/super_admin. Server memvalidasi sesuai skema section.

**Request Body:**
```json
{
  "content": {
    "badge": "#1 Kelas Robotik Anak",
    "title": "Belajar Robotik Jadi Seru!",
    "subtitle": "Kelas robotika interaktif untuk anak.",
    "primary_cta": { "label": "Daftar Sekarang", "href": "/daftar" },
    "secondary_cta": { "label": "Lihat Program", "href": "#program" },
    "image_url": "/storage/landing/hero.webp",
    "stat": { "value": "500+", "label": "Siswa Telah Bergabung" }
  }
}
```

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Section diperbarui.",
  "data": { "section": "hero", "content": { /* konten tersimpan */ } }
}
```

### `POST /landing-upload`
Mengunggah gambar (JPG/PNG, maks 5MB). Dikonversi ke WebP, mengembalikan URL publik. Butuh role admin/super_admin.

**Request Body:** `multipart/form-data`
*   `image` (file)

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Gambar terunggah.",
  "data": {
    "url": "http://localhost:8000/storage/landing/abc.webp",
    "path": "landing/abc.webp"
  }
}
```
*Alur: upload → ambil `url` → kirim `PUT /landing/:section` dengan field gambar (mis. `image_url`) diisi `url` tersebut.*

---

## 2. Artikel (Publik)

### `GET /artikel`
Daftar artikel berstatus `publish` (paginated). Dipakai section "Artikel & Tips".

**Query Parameters:**
*   `category` (opsional)
*   `per_page` (opsional, default: 9)

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Artikel publik.",
  "data": {
    "current_page": 1,
    "per_page": 9,
    "total": 12,
    "data": [
      {
        "id": 1,
        "title": "Kenapa Anak Perlu Belajar Coding Sejak Dini?",
        "slug": "kenapa-anak-perlu-belajar-coding",
        "category": "Tips Belajar",
        "cover_url": "https://api.robotiku.id/storage/articles/abc.webp",
        "published_at": "2026-06-12T08:00:00Z"
      }
    ]
  }
}
```
*Daftar artikel ada di `data.data`; info paginasi di `data.current_page`, `data.total`, dst.*

### `GET /artikel/:slug`
Detail satu artikel berdasarkan slug.

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Detail artikel.",
  "data": {
    "id": 1,
    "title": "Kenapa Anak Perlu Belajar Coding Sejak Dini?",
    "slug": "kenapa-anak-perlu-belajar-coding",
    "category": "Tips Belajar",
    "cover_url": "https://api.robotiku.id/storage/articles/abc.webp",
    "published_at": "2026-06-12T08:00:00Z",
    "content": "<p>Isi artikel berupa HTML dari rich text editor...</p>"
  }
}
```
*Slug tidak ditemukan / masih draft → `404 Not Found`. `content` berupa HTML, render dengan sanitasi aman.*

---

## Ringkasan Endpoint

| Method | Endpoint              | Auth  | Keterangan                         |
|--------|-----------------------|-------|------------------------------------|
| GET    | `/landing`            | —     | Semua konten section               |
| GET    | `/landing/:section`   | —     | Satu section                       |
| PUT    | `/landing/:section`   | Admin | Ubah konten section                |
| POST   | `/landing-upload`     | Admin | Upload gambar → URL                |
| GET    | `/artikel`            | —     | Daftar artikel publish (paginated) |
| GET    | `/artikel/:slug`      | —     | Detail artikel                     |

---

## Catatan untuk Frontend
*   Render konten dari API, jangan hardcode. Cache hasil `GET /landing` dan revalidate berkala.
*   Field `icon` (di `programs`, `about.highlights`) dan `socials[].type` berupa string nama — petakan ke komponen ikon di sisi FE.
*   `image_url` bisa path relatif (`/images/...`) atau URL hasil upload (`/storage/...`). Tangani keduanya.
*   Field opsional bisa kosong/absen — selalu sediakan fallback aman.

*Versi 1.0 — handoff ke tim frontend landing page.*
