# Setup & Instalasi Laravel
composer install


# Git Branching System:
- Main (Production ready files untuk keperluan deploy mauapun ci/cd )
- Development (Staging utama aplikasi sebelum di push ke main)
- Feature/${namaFitur} (Development Fitur)


# Teknis Commit Message
Format Commit Message adalah <type>(scope): (deskripsi)
Commit Type:
- feat (fitur baru)
- fix (bug fix)
- refactor (ngerapiin kode)
- docs (buat dokumentasi)
- style (formatting kode)
- chore (dependency, config, tools)
- perf (optimisasi)
- revert (in case push baru error, revert buat balik ke masa lalu)

Contoh:
feat(login): Penambahan Client Side Validation pada Presentation Layer Autentikasi