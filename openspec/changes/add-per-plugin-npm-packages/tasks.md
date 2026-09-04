## 0. Gate — jangan lanjut ke bawah sebelum ini terpenuhi

- [ ] 0.1 Konfirmasi salah satu trigger `design.md`'s D1 benar-benar terjadi (drift versi nyata / konsumen arms-length nyata / keputusan eksplisit pemilik proyek) — dicatat di sini sebelum task 1 dimulai
- [ ] 0.2 Konfirmasi `extract-frontend-ui-kit` sudah diarsipkan (selesai dieksekusi) — proposal ini bergantung padanya

## 1. Tooling workspace FE

- [ ] 1.1 Pilih tooling workspace (npm workspaces / pnpm / lainnya) berdasarkan skala aktual saat itu (lihat design.md D3 — sengaja tidak diputuskan di muka)
- [ ] 1.2 Pasang & verifikasi tooling tidak merusak alur `npm run dev`/`npm run build` untuk konsumen fork+resync yang masih ada

## 2. Ekstraksi `@vitamind/ui` jadi package sungguhan

- [ ] 2.1 Tambah `package.json` untuk `@vitamind/ui`, versi awal (mis. `0.1.0`)
- [ ] 2.2 Setup build/publish step sesuai tooling yang dipilih di 1.1

## 3. `package.json` per-plugin

- [ ] 3.1 Untuk tiap `dev-packages/vitamind-*` yang punya `resources/js`: tambah `package.json`, declare dependency ke `@vitamind/ui` dengan constraint versi
- [ ] 3.2 Declare peer dependency (`react`, `@inertiajs/react`, dll.) sesuai kebutuhan masing-masing plugin

## 4. Verifikasi & dokumentasi

- [ ] 4.1 Verifikasi resolusi dependency lintas-workspace tidak merusak build host untuk semua konsumen dogfooding yang tercatat di `stabilize-vitamind-packages`
- [ ] 4.2 Update `docs/local-plugins.md`'s panduan ekstraksi plugin (langkah baru: inisialisasi `package.json` sejak awal)
- [ ] 4.3 Update spec `plugin-npm-package-distribution` kalau ada detail implementasi yang berbeda dari yang ditulis di proposal ini
