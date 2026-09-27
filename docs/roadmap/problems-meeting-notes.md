# Daftar Masalah & Kebutuhan Pengembangan (Rapat Logbook)

Dokumen ini merangkum inventarisasi masalah, temuan celah (*gap expected vs reality*), dan kebutuhan fitur pada sistem **Siimrobi (Robotiku ERP)** yang dihimpun dari catatan rapat tanggal 23 dan 24 September 2026 beserta status resolusinya.

---

## Rapat: 23 September 2026

### 1. Manajemen Sesi & Kelas (Handle Pending Kelas)
* **Kondisi Saat Ini:**
  - Sesi kelas hanya dibuat otomatis mingguan oleh sistem (*by system*).
* **Gap & Kebutuhan:**
  - **Pembuatan Sesi Manual:** Harus bisa mengatur/membuat sesi kelas secara manual untuk mengantisipasi kelas pending atau penyesuaian jadwal lapangan.
  - **Adaptasi Kontrak:** Jumlah sesi tidak selalu terpaku 4 sesi (bisa fleksibel sesuai kontrak kerjasama).
  - **Koneksi Sesi ke Penagihan:** Sistem harus menghubungkan progres sesi dengan penagihan. Ketika target sesi tercapai (misal: 4 sesi sesuai kesepakatan kontrak), sistem otomatis men-generate tagihan untuk bulan berikutnya.

### 2. Verifikasi Pembayaran & Finansial
* **Bukti Pembayaran Tidak Tampil:** Gambar bukti transfer/pembayaran tidak muncul di halaman verifikasi (diduga kendala konfigurasi storage/bucket atau serving URL).
  - *Status:* **Resolved (Phase 1)** — Diimplementasikan serving biner inline preview, guard multi-tenant, dan opsi `?download=1`. Lihat [Dokumentasi Teknis Phase 1](../features/media-serving.md).
* **Aliran Tagihan ke Finance:** Tagihan yang dikirim oleh pihak sekolah tidak masuk/terekap di dashboard tim finance.
  - *Status:* **Resolved (Phase 2)** — Diimplementasikan rekonsiliasi transaksional berjenjang saat verifikasi setoran sekolah (`SchoolSettlement`), aktivasi invoice & verifikasi siswa, serta formula anti double-counting pada dashboard keuangan. Lihat [Dokumentasi Teknis Phase 2](../features/finance-reconciliation.md).

### 3. Kinerja Sistem (Performance)
* **Latency Tidak Konsisten:** Respons sistem kadang terasa lambat secara sporadis. Diperlukan profiling query database, optimalisasi endpoint, dan caching.
  - *Status:* **Optimized** — Indeks gabungan telah diterapkan pada tabel tagihan, sekolah, dan registrasi. Lihat [Walkthrough Performa](../performance/walkthrough.md).

---

## Rapat: 24 September 2026

### 1. Data Siswa & Siklus Hidup (Student Lifecycle)
* **Filter Murid Baru:** Murid tidak muncul dalam daftar murid jika statusnya belum bayar dan belum diverifikasi.
  - *Status:* **Resolved (Phase 3)** — Query `where('is_verified', true)` diubah menjadi filter dinamis melalui query param `verification_status` (`verified`, `unverified`, `all`) pada Admin dan Portal Sekolah.
* **Fitur Edit Biodata Murid:** Belum tersedia fungsi edit profil/biodata siswa untuk role `sekolah`, `admin`, dan `superadmin`.
  - *Status:* **Resolved (Phase 3)** — Dibuat service terpusat `StudentBiodataService`, form request `UpdateStudentBiodataRequest`, serta endpoint `PUT /api/v1/siswa/{student}` (Admin) dan `PUT /api/v1/sekolah/murid/{student}` (Sekolah dengan isolasi multi-tenant).
* **Transisi Status Siswa:** Terjadi masalah/kendala teknis saat transisi status dari **Cuti $\rightarrow$ Berhenti**.
  - *Status:* **Resolved (Phase 3)** — Harmonisasi validasi `StudentStatusRequest` dan pemetaan otomatis input `'berhenti'` menjadi `'nonaktif'` pada database secara transaksional.
* **Penyederhanaan Label UI:** Label status *"Berhenti"* perlu diganti menjadi *"Nonaktif"* agar lebih representatif.
  - *Status:* **Resolved (Phase 3)** — Standarisasi label dan nilai database menjadi `nonaktif`, dengan backward compatibility untuk input `berhenti`.
* **Audit Trail / Riwayat Status:** Riwayat perubahan status siswa yang bersifat *immutable* belum terimplementasi.
  - *Status:* **Resolved (Phase 3)** — Setiap perubahan status kini wajib dan otomatis tercatat ke tabel `student_status_logs` (menggunakan trait `Immutable`) di dalam transaksi atomik database.
* **Pencarian Nomor HP Orang Tua:** Temuan nomor HP (08969696) yang sebelumnya dilaporkan tidak ditemukan dikonfirmasi **working as intended** (faktor akun *self-managed*).
  - *Detail teknis Phase 3 selengkapnya:* Lihat [Dokumentasi Teknis Phase 3](../features/student-lifecycle.md).

### 2. Skema Pembayaran Sekolah (Multi-scheme Payment)
Terdapat *gap* ekspektasi alur pembayaran antara yang langsung ke Robotiku dengan yang dikelola sekolah:
* **Versi 1 (Direct Payment):** Pembayaran langsung ditujukan ke rekening Robotiku.
* **Versi 2 (Managed by School):** Dikelola oleh pihak sekolah (dapat menggunakan rekening sekolah).
* **Versi 3 (Collective Payment):** Sekolah mengelola pembiayaan secara kolektif, sehingga tagihan pembiayaan **tidak boleh dimunculkan** di portal orang tua (*expose* logika penayangan tagihan).

### 3. Portal Orang Tua (Parent Portal UX)
* **Header Identitas:** Menambahkan informasi **nama sekolah** tepat di bawah sapaan `"Hi, {nama},"` agar orang tua mengetahui konteks sekolah anak yang terdaftar.

### 4. Autentikasi & Sesi
* **Logout Mechanics:** Terdapat kejanggalan/anomali (*tomfoolery*) pada mekanisme logout yang perlu distandarisasi penanganan token/session-nya.

---

## Matriks Rangkuman & Prioritas

| No | Modul | Deskripsi Masalah | Tanggal Temuan | Status | Referensi Teknis |
|:--:|---|---|:---:|:---:|---|
| 1 | Sesi & Kelas | Pembuatan sesi manual & handle pending kelas | 23/09/2026 | Backlog | Roadmap Phase 4 |
| 2 | Sesi & Finansial | Trigger invoice setelah target sesi kontrak selesai | 23/09/2026 | Backlog | Roadmap Phase 4 |
| 3 | Finansial | Gambar bukti bayar tidak muncul (Storage/Bucket) | 23/09/2026 | **Resolved (Phase 1)** | [docs/features/media-serving.md](../features/media-serving.md) |
| 4 | Finansial | Tagihan sekolah tidak masuk ke modul finance | 23/09/2026 | **Resolved (Phase 2)** | [docs/features/finance-reconciliation.md](../features/finance-reconciliation.md) |
| 5 | Siswa | Fitur edit biodata murid (role sekolah, superadmin, admin) | 24/09/2026 | **Resolved (Phase 3)** | [docs/features/student-lifecycle.md](../features/student-lifecycle.md) |
| 6 | Siswa | Bug transisi status Cuti $\rightarrow$ Berhenti & ubah label ke Nonaktif | 24/09/2026 | **Resolved (Phase 3)** | [docs/features/student-lifecycle.md](../features/student-lifecycle.md) |
| 7 | Siswa | Tabel riwayat status siswa (*immutable audit trail*) | 24/09/2026 | **Resolved (Phase 3)** | [docs/features/student-lifecycle.md](../features/student-lifecycle.md) |
| 8 | Siswa | Filter visibilitas murid yang belum bayar/verified | 24/09/2026 | **Resolved (Phase 3)** | [docs/features/student-lifecycle.md](../features/student-lifecycle.md) |
| 9 | Finansial / Ortu | Penyesuaian tampilan tagihan ortu sesuai 3 skema sekolah (v1/v2/v3) | 24/09/2026 | High | Backlog / Phase 4 |
| 10 | Portal Ortu | Tampilkan nama sekolah di bawah sapaan orang tua | 24/09/2026 | UI Quick-fix | Frontend / Backlog |
| 11 | Auth | Perbaikan alur & mekanisme logout | 24/09/2026 | Bugfix | Backlog |
| 12 | System | Optimalisasi performa respon backend | 23/09/2026 | **Optimized** | [docs/performance/walkthrough.md](../performance/walkthrough.md) |

---

## Log Riwayat Implementasi Fase

1. **Phase 1: Local-First Media Serving & Proof View**
   - Mengubah respon download paksa menjadi stream biner inline.
   - Menyediakan otorisasi multi-tenant bagi admin sekolah untuk verifikasi bukti bayar murid.
   - Dokumentasi: [`docs/features/media-serving.md`](../features/media-serving.md).

2. **Phase 2: Rekonsiliasi Finansial & Dashboard Pendapatan**
   - Menyinkronkan persetujuan setoran sekolah dengan status pelunasan invoice dan aktivasi siswa.
   - Menghitung omzet setoran sekolah pada dashboard keuangan tanpa risiko *double-counting*.
   - Dokumentasi: [`docs/features/finance-reconciliation.md`](../features/finance-reconciliation.md).

3. **Phase 3: Siklus Hidup Siswa (Status, Biodata, & Filter Visibilitas)**
   - Harmonisasi status siswa (`berhenti` $\rightarrow$ `nonaktif`) dengan audit log immutable di `student_status_logs`.
   - Endpoint pembaruan biodata murid & kontak wali untuk Admin dan Sekolah (dengan isolasi multi-tenant `school_id`).
   - Filter visibilitas dinamis `verification_status` (`verified`, `unverified`, `all`).
   - Dokumentasi: [`docs/features/student-lifecycle.md`](../features/student-lifecycle.md).
