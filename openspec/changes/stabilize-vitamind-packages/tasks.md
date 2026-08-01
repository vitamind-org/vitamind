## 0. Gate Check (blocking — do not proceed until satisfied)

- [ ] 0.1 Confirm `phase1-standalone-boilerplate` §1–13 (extraction, plugin system, docs) selesai secara struktural

---

## 1. Dogfooding di Proyek Konsumen Nyata

*(dipindah apa adanya dari `phase1-standalone-boilerplate` §16)*

Gate untuk membuka `publish-vitamind-packages`: minimal 2 dari 3 proyek berikut berhasil meng-extend boilerplate ini via `dev-packages/` path repository tanpa memerlukan breaking change pada `vitamind/core` atau `vitamind/workspace-plugin`.

- [ ] 1.1 BukuWarga: extend boilerplate ini, verifikasi `composer require vitamind/core` (+ workspace plugin bila dipakai) berjalan end-to-end di proyek nyata
- [ ] 1.2 LembarUji: extend boilerplate ini, verifikasi `composer require vitamind/core` (+ workspace plugin bila dipakai) berjalan end-to-end di proyek nyata
- [ ] 1.3 UangKas: extend boilerplate ini, verifikasi `composer require vitamind/core` (+ workspace plugin bila dipakai) berjalan end-to-end di proyek nyata

---

## 2. Gate Check & Closure

- [ ] 2.1 Konfirmasi ≥2 dari 3 proyek di atas berhasil tanpa breaking change → unblock change `publish-vitamind-packages`
- [ ] 2.2 Jika ada proyek yang menemukan kebutuhan breaking change: catat sebagai temuan, evaluasi terpisah sebagai change terhadap `vitamind/core`/`vitamind/workspace-plugin`, lalu retry integrasi proyek tersebut
- [ ] 2.3 Mark `stabilize-vitamind-packages` as complete in OpenSpec once gate is satisfied
