## 0. Gate Check (blocking — do not proceed until satisfied)

- [ ] 0.1 Confirm `phase1-standalone-boilerplate` §1–13 (extraction, plugin system, docs) selesai secara struktural
- [x] 0.2 `fix-pre-dogfooding-gaps` sudah selesai (33/33 tasks) — 5 gap konkret (migration duplikat, hardcode `App\Models\User`/`PersonalAccessToken`, config default yang hilang, gate manual usang, provider bangkai) sudah ditutup dan diverifikasi lewat instalasi `laravel new` + `composer require vitamind/core` (+ workspace-plugin) sungguhan di direktori terpisah — bukan cuma pembacaan kode. Dua bug tambahan ditemukan & diperbaiki selama verifikasi: `App\Models\PersonalAccessToken` juga wajib dibuat developer (didokumentasikan di `MIGRATION_GUIDE.md`), dan `PersonalAccessTokenPolicy` yang tidak pernah terdaftar di `Gate` (bug pra-existing, membuat `/settings/api-keys` selalu 403). Syarat mulai dogfooding ke BukuWarga/LembarUji/UangKas (§1 di bawah) terpenuhi.

---

## 1. Dogfooding di Proyek Konsumen Nyata

*(dipindah apa adanya dari `phase1-standalone-boilerplate` §16)*

Gate untuk membuka `publish-vitamind-packages`: minimal 2 dari 4 proyek berikut berhasil meng-extend boilerplate ini via `dev-packages/` path repository tanpa memerlukan breaking change pada `vitamind/core` atau `vitamind/workspace-plugin`.

- [ ] 1.1 BukuWarga: extend boilerplate ini, verifikasi `composer require vitamind/core` (+ workspace plugin bila dipakai) berjalan end-to-end di proyek nyata
- [ ] 1.2 LembarUji: extend boilerplate ini, verifikasi `composer require vitamind/core` (+ workspace plugin bila dipakai) berjalan end-to-end di proyek nyata
- [ ] 1.3 UangKas: extend boilerplate ini, verifikasi `composer require vitamind/core` (+ workspace plugin bila dipakai) berjalan end-to-end di proyek nyata
- [ ] 1.4 wakuwaku: extend boilerplate ini (fork + resync berkelanjutan dengan `dev-packages/`, bukan `composer require` sekali), verifikasi `vitamind/core` + `vitamind/workspace-plugin` (+ `vitamind/realtime-plugin` untuk use case realtime-data mereka) berjalan end-to-end di proyek nyata — ditambahkan sebagai proyek dogfooding ke-4, terblokir sampai change `fix-plugin-frontend-distribution` merge ke `dev`

---

## 2. Gate Check & Closure

- [ ] 2.1 Konfirmasi ≥2 dari 4 proyek di atas berhasil tanpa breaking change → unblock change `publish-vitamind-packages`
- [ ] 2.2 Jika ada proyek yang menemukan kebutuhan breaking change: catat sebagai temuan, evaluasi terpisah sebagai change terhadap `vitamind/core`/`vitamind/workspace-plugin`, lalu retry integrasi proyek tersebut
- [ ] 2.3 Mark `stabilize-vitamind-packages` as complete in OpenSpec once gate is satisfied
