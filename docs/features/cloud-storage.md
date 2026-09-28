# Cloud Object Storage Adapter & Presigned URL Strategy (Phase 4)

Dokumentasi ini menjelaskan arsitektur penyimpanan berkas Robotiku ERP yang mendukung secara penuh **penyimpanan lokal (Local Disk)** maupun **penyimpanan awan (Bucket Storage: AWS S3, MinIO, Cloudflare R2)** dengan strategi **Presigned Temporary URL**.

---

## 1. Latar Belakang & Masalah

Sebelum Phase 4, seluruh operasi berkas (bukti pembayaran, setoran, MoU, TTD pelatih, dan media landing) terikat kuat (*tightly coupled*) pada disk lokal server:
- `app/Support/ImageStorage.php` memanggil `$disk->path($path)` secara langsung untuk konversi WebP via ekstensi GD. Pada driver S3/R2/MinIO, pemanggilan `->path()` memicu *fatal exception* karena adapter remote tidak memiliki path filesystem lokal.
- Controller mengandalkan `response()->file($disk->path(...))` dan `response()->download(...)` yang memerlukan berkas fisik lokal di server.
- Pada arsitektur kontainer (Docker/Kubernetes) atau multi-server, file yang disimpan lokal akan hilang saat kontainer direstart atau tidak dapat diakses antar node web server.

---

## 2. Arsitektur Dual Storage & Unified Service

Sistem kini menggunakan abstraksi terpusat pada [`App\Support\MediaStorage`](file:///c:/Users/Fjontoldesk/Documents/Projects/robotiku-erp/backend-robotiku/app/Support/MediaStorage.php) yang secara transparan menangani penyimpanan dan penyajian berkas baik untuk disk lokal maupun bucket cloud.

```mermaid
flowchart TD
    Client[Klien Web / Mobile / Ortu] --> Route[Media & Proof Endpoints]
    Route --> Auth{Otorisasi / Folder Guard}
    Auth -->|Lolos| MS[App\\Support\\MediaStorage::response]
    MS --> Resolve{Deteksi Lokasi Berkas}
    Resolve -->|S3 / MinIO / R2| Presigned[Hasilkan Presigned URL S3]
    Presigned --> Redirect[HTTP 302 Redirect ke Bucket CDN]
    Resolve -->|Local Disk| Stream[Secure Flysystem Stream HTTP 200]
    Resolve -->|Tidak Ditemukan| 404[HTTP 404 Not Found]
```

### A. Strategi Presigned Temporary URL (Bucket Storage)
Untuk penyimpanan bucket (`FILESYSTEM_DISK=s3`):
1. Sistem melakukan verifikasi otorisasi terlebih dahulu (misal: verifikasi token Sanctum untuk folder internal staf, atau validasi kepemilikan sekolah multi-tenant).
2. Sistem menghasilkan **Presigned Temporary URL** dengan masa berlaku terbatas (default: 15 menit).
3. Jika request menyertakan `?download=1`, opsi `ResponseContentDisposition` disetel ke `attachment; filename="..."` langsung pada parameter signature S3.
4. Klien dialihkan via **HTTP 302 Found** (`redirect()->away($url)`).
5. Klien (browser, tag `<img>`, atau PDF viewer) mengunduh berkas langsung dari Object Storage / CDN, **mengeliminasi beban CPU dan bandwidth server PHP**.
6. Jika klien API menginginkan URL secara langsung tanpa redirect, parameter `?json=1` dengan header `Accept: application/json` akan mengembalikan payload JSON `{ "url": "...", "expires_at": "..." }`.

### B. Penyajian Aman untuk Local Storage
Untuk lingkungan pengembangan atau server tanpa kredensial S3 (`FILESYSTEM_DISK=local`):
- Berkas tetap tersimpan di direktori privat non-publik (`storage/app/private`).
- Sistem menyajikan berkas via Flysystem stream (`readStream()`) dengan header `Content-Type`, `Content-Disposition` (`inline` atau `attachment`), dan `Cache-Control: private`.

### C. Fallback Cerdas (Zero-Downtime Migration)
Jika sistem diubah ke `FILESYSTEM_DISK=s3`, berkas-berkas lama yang diunggah sebelum migrasi dan masih berada di disk lokal **tidak akan menghasilkan 404**. Metode `MediaStorage::resolveDiskForFile($path)` akan secara otomatis:
1. Memeriksa keberadaan file di disk default (`s3`).
2. Jika tidak ada di S3, memeriksa apakah file tersedia di disk `local`.
3. Jika ditemukan di `local`, menyajikan file via streaming lokal secara transparan.

---

## 3. Konversi Gambar WebP dalam Memori

Pada [`App\Support\ImageStorage`](file:///c:/Users/Fjontoldesk/Documents/Projects/robotiku-erp/backend-robotiku/app/Support/ImageStorage.php), pemanggilan fisik `$disk->path()` telah digantikan dengan *output buffering* memori PHP:

```php
// Render WebP langsung ke memory buffer (tanpa menyentuh filesystem lokal)
ob_start();
imagewebp($src, null, 80);
$content = (string) ob_get_clean();
imagedestroy($src);

// Tulis binary string langsung ke disk aktif (local maupun s3)
Storage::disk($diskName)->put($path, $content);
```

Keuntungan:
- Kompatibel 100% dengan AWS S3, MinIO, Cloudflare R2, dan disk lokal.
- Tidak memerlukan pembuatan direktori fisik `@mkdir` di server host.
- Menghemat operasi I/O disk lokal.

---

## 4. Panduan Konfigurasi Multi-Provider

### 1. Mode Lokal (Default)
```dotenv
FILESYSTEM_DISK=local
```
Berkas disimpan di `storage/app/private/` dan disajikan via stream PHP berotentikasi.

### 2. AWS S3 Standard
```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=robotiku-erp-production
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### 3. MinIO (Self-Hosted Object Storage)
```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadminpassword
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=robotiku
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### 4. Cloudflare R2 (S3-Compatible, Tanpa Biaya Egress)
```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=r2-access-key-id
AWS_SECRET_ACCESS_KEY=r2-secret-access-key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=robotiku-media
AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

---

## 5. Ringkasan Endpoint Terintegrasi

| Endpoint | Method | Otorisasi | Respon Storage (Cloud) | Respon Storage (Lokal) |
|---|---|---|---|---|
| `/api/v1/media/{path}` | `GET` | Folder Guard (`auth:sanctum` untuk internal staf) | `302 Redirect` ke Presigned URL | `200 OK` Streamed Response |
| `/api/v1/public-media/{path}` | `GET` | Publik (folder umum) | `302 Redirect` ke Presigned URL | `200 OK` Streamed Response |
| `/api/v1/bayar/payments/{payment}/proof` | `GET` | `admin_keuangan, admin, super_admin` | `302 Redirect` ke Presigned URL | `200 OK` Streamed Response |
| `/api/v1/sekolah/pembayaran/{payment}/proof` | `GET` | `SchoolAdmin` sekolah terkait | `302 Redirect` ke Presigned URL | `200 OK` Streamed Response |
| `/api/v1/keuangan/setoran/{settlement}/proof` | `GET` | `admin_keuangan, admin, super_admin` | `302 Redirect` ke Presigned URL | `200 OK` Streamed Response |
| `/api/v1/sekolah/setoran/{settlement}/proof` | `GET` | `SchoolAdmin` sekolah terkait | `302 Redirect` ke Presigned URL | `200 OK` Streamed Response |
| `/api/v1/canvas/mou/{mou}/file` | `GET` | `auth:sanctum` staf internal | `302 Redirect` ke Presigned URL (Attachment) | `200 OK` Streamed Download |

---

## 6. Verifikasi & Pengujian Otomatis

Seluruh logika penyimpanan cloud diuji secara otomatis pada [`tests/Feature/Storage/CloudStorageTest.php`](file:///c:/Users/Fjontoldesk/Documents/Projects/robotiku-erp/backend-robotiku/tests/Feature/Storage/CloudStorageTest.php):
1. `test_image_storage_store_webp_on_local_disk`: Memvalidasi penyimpanan WebP lokal.
2. `test_image_storage_store_webp_on_cloud_bucket_storage`: Memvalidasi penyimpanan WebP S3/cloud tanpa path lokal.
3. `test_media_storage_response_on_cloud_bucket_returns_presigned_url_redirect`: Memvalidasi presigned URL redirect 302.
4. `test_media_storage_response_json_format_on_cloud_bucket`: Memvalidasi kembalian JSON bila diminta klien API.
5. `test_media_storage_dual_fallback_serves_local_file_when_default_is_s3`: Memvalidasi fallback tanpa downtime dari cloud ke file warisan lokal.
6. `test_payment_verification_proof_redirects_to_presigned_url_on_s3`: Memvalidasi bukti bayar ortu dialihkan ke presigned S3.
7. `test_school_settlement_proof_redirects_to_presigned_url_on_s3`: Memvalidasi bukti setoran sekolah dialihkan ke presigned S3.
