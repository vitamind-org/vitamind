## 1. Alias setup

- [x] 1.1 Tambah `@vitamind/ui/*` di `vite.config.ts`'s `resolve.alias`, menunjuk ke `resources/js/components/ui` (dan entri terpisah untuk `resources/js/lib/utils.ts` kalau dibutuhkan path granular, mis. `@vitamind/ui/cn`)
- [x] 1.2 Tambah entri `paths` yang sepadan di `tsconfig.json`
- [x] 1.3 Verifikasi `npm run types` tetap bersih setelah alias baru ditambahkan (sebelum migrasi import apa pun)

## 2. Migrasi import host

- [x] 2.1 Grep `@/components/ui\|@/lib/utils` di `resources/js/**` (di luar `dev-packages/`), update ke `@vitamind/ui/*`
- [x] 2.2 Verifikasi `npm run types` dan `npm run build` bersih

## 3. Migrasi import plugin

- [x] 3.1 Update 18 file (per grep saat proposal ditulis) di `dev-packages/vitamind-workspace-plugin/resources/js/**` dan `dev-packages/vitamind-archive-plugin/resources/js/**` dari `@/components/ui/*`/`@/lib/utils` ke `@vitamind/ui/*`
- [x] 3.2 Grep ulang `@/components/ui\|@/lib/utils` di `dev-packages/` — harus nol hasil
- [x] 3.3 Verifikasi `npm run build` sukses, `@plugin/{name}` tetap resolve untuk semua plugin yang disentuh

## 4. Dokumentasi

- [x] 4.1 Update `docs/local-plugins.md`'s bagian "Distributed plugin frontend": dokumentasikan `@vitamind/ui/*` sebagai jalur primitives UI, dengan catatan eksplisit ini alias Vite/tsconfig — BUKAN npm package terpisah — sampai (dan kalau) companion change ekstraksi package dieksekusi

  (Catatan: file docs ini sudah dipindah/direorganisasi jadi `docs/plugin-development/local-plugins.md` sebelum change ini dikerjakan — bagian "Distributed plugin frontend" diupdate di lokasi barunya.)

## 5. Verifikasi akhir

- [x] 5.1 Smoke test manual: buka satu halaman yang merender komponen dari plugin (mis. workspace switcher atau halaman archive-plugin) di browser sungguhan, pastikan primitives UI (Button/Dialog/dst.) tetap tampil & berfungsi seperti sebelumnya
- [x] 5.2 `npm run types` dan `npm run build` bersih di seluruh repo
