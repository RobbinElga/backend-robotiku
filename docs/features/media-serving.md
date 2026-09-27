# Dokumentasi Teknis Phase 1: Bukti Pembayaran & Media Serving

Dokumen ini mendokumentasikan arsitektur, endpoint, otorisasi, dan mekanisme serving media/bukti pembayaran yang diimplementasikan pada **Phase 1 (Local-First Media Serving)** di Robotiku ERP Backend.

---

## 1. Latar Belakang & Masalah

Sebelum implementasi Phase 1:
1. **Paksa Unduh (Forced Download):** Method `proof()` di `PaymentVerificationController` menggunakan `response()->download(...)` yang menambahkan header `Content-Disposition: attachment`. Hal ini memaksa browser mengunduh file alih-alih menampilkan gambar/preview PDF langsung di elemen `<img>` atau modal dialog.
2. **Ketiadaan Endpoint Khusus Sekolah:** Admin sekolah tidak memiliki endpoint terproteksi untuk melihat bukti pembayaran siswa sekolahnya sendiri saat verifikasi pembayaran instansi.
3. **Ketiadaan Endpoint Bukti Setoran:** Staf keuangan dan admin sekolah belum memiliki endpoint RESTful berbasis ID untuk memeriksa bukti transfer setoran sekolah (`SchoolSettlement`).

---

## 2. Daftar Endpoint & Hak Akses

Berikut adalah daftar endpoint serving bukti bayar dan media yang aktif:

| Method | Endpoint | Handler | Role / Guard | Deskripsi |
|---|---|---|---|---|
| `GET` | `/api/v1/bayar/payments/{payment}/proof` | `PaymentVerificationController@proof` | `role:admin_keuangan,admin,super_admin` | Melihat bukti transfer pembayaran siswa mandiri/instansi. |
| `GET` | `/api/v1/sekolah/pembayaran/{payment}/proof` | `SchoolPaymentController@proof` | `auth:sanctum` (Admin Sekolah & Keuangan) | Melihat bukti bayar siswa yang terdaftar di sekolah admin terkait. |
| `GET` | `/api/v1/school/payments/{payment}/proof` | `SchoolPaymentController@proof` | `auth:sanctum` (Admin Sekolah & Keuangan) | *Alias* endpoint bahasa Inggris untuk kompatibilitas klien. |
| `GET` | `/api/v1/sekolah/setoran/{settlement}/proof` | `SchoolPaymentController@settlementProof` | `auth:sanctum` (Admin Sekolah terkait) | Melihat bukti transfer setoran milik sekolah admin yang login. |
| `GET` | `/api/v1/keuangan/setoran/{settlement}/proof` | `FinanceController@settlementProof` | `role:admin_keuangan,admin,super_admin` | Rekonsiliasi bukti transfer setoran sekolah oleh staf keuangan. |
| `GET` | `/api/v1/media/{path}` | `MediaController@show` | Public (Folder whitelist) | Streaming media folder internal (`payments/`, `settlements/`, dll.). |
| `GET` | `/api/v1/public-media/{path}` | `MediaController@publicShow` | Public (Folder whitelist) | Streaming asset publik (`schools/`, `articles/`, `landing/`, `settings/`). |

---

## 3. Mekanisme Serving & Headers

Semua endpoint media dan bukti transaksi di atas mengikuti standar respons file biner:

### 3.1. Default: Inline Preview (Browser View)
Secara default, response menghasilkan:
```http
HTTP/1.1 200 OK
Content-Type: image/jpeg (atau application/pdf, sesuai file)
Content-Disposition: inline
```
Header ini memungkinkan browser menampilkan file langsung di tag `<img>`, tag `<iframe/embed>`, maupun membuka preview tab baru.

### 3.2. Mode Unduh Paksa (`?download=1`)
Jika klien atau pengguna ingin mengunduh file secara eksplisit ke penyimpanan lokal, tambahkan query parameter `?download=1`:
```http
GET /api/v1/bayar/payments/12/proof?download=1
GET /api/v1/keuangan/setoran/5/proof?download=1
GET /api/v1/media/payments/bukti.jpg?download=1
```
Response akan menghasilkan:
```http
HTTP/1.1 200 OK
Content-Disposition: attachment; filename="bukti.jpg"
```

---

## 4. Keamanan & Multi-Tenant Guard

Untuk mencegah kebocoran data (*Insecure Direct Object Reference / IDOR*):
1. **Pemeriksaan Kepemilikan Sekolah (School Multi-Tenancy):**
   Pada `SchoolPaymentController@proof`:
   ```php
   $user = $r->user();
   if ($user instanceof SchoolAdmin) {
       $payment->loadMissing('invoice.student');
       abort_unless($payment->invoice?->student?->school_id === $user->school_id, 403, 'Akses ditolak.');
   }
   ```
   Admin Sekolah A tidak diizinkan membuka bukti pembayaran siswa Sekolah B (menghasilkan respons `403 Forbidden`).
2. **Validasi Eksistensi File Fisik:**
   Sebelum memanggil `response()->file(...)`, sistem memverifikasi ketersediaan file pada storage:
   ```php
   abort_unless($payment->proof_file && Storage::disk('local')->exists($payment->proof_file), 404);
   ```
3. **Pencegahan Path Traversal di MediaController:**
   Memblokir request yang mengandung karakter `..` atau folder di luar daftar *whitelist*.

---

## 5. Hubungan dengan Frontend

Frontend Next.js memiliki 2 pola pemanggilan:
1. **Melalui Path File (`ProofView` / `AuthImage`):**
   Memanggil `api.get('/media/${path}', { responseType: 'blob' })`. Digunakan pada halaman rekap umum dan kartu tabel.
2. **Melalui ID Transaksi:**
   Memanggil `api.get('/bayar/payments/${payment.id}/proof', { responseType: 'blob' })` pada modal detail verifikasi internal keuangan.

Kedua pola di atas sepenuhnya didukung oleh backend.

---

## 6. Validasi Pengujian Otomatis

Pengujian unit dan fitur untuk Phase 1 mencakup:
```bash
php artisan test tests/Feature/Bayar/PaymentVerifyTest.php tests/Feature/Keuangan/SettlementProofTest.php tests/Feature/MediaTest.php
```

Cakupan pengujian:
- Verifikasi inline header dan download attachment parameter.
- Proteksi status 404 jika file bukti tidak ditemukan.
- Isolasi otorisasi multi-tenant admin sekolah (akses valid vs lintas sekolah).
- Pembatasan role internal keuangan dan penolakan role tidak sah (misal: `trainer`).
- Pencegahan path traversal dan akses folder ilegal pada endpoint media.
