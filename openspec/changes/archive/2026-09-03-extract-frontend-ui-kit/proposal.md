## Why

`plugin-frontend-distribution` (change `fix-plugin-frontend-distribution`, sudah di-archive) menyelesaikan arah dependency FE untuk **halaman** dan **hook** plugin lewat `@plugin/{name}` dan `usePlugin()`. Tapi satu jalur tetap terbalik: komponen React plugin (mis. `dev-packages/vitamind-workspace-plugin/resources/js/components/workspace-switch.tsx`) meng-import primitives UI langsung dari host — `import { Button } from '@/components/ui/button'`. `@/` adalah alias `resources/js` milik HOST (`tsconfig.json`, `vite.config.ts`), jadi source plugin secara statis mengasumsikan struktur file internal host tertentu, tanpa kontrak yang dideklarasikan atau ter-versi — berkebalikan dengan sisi backend, di mana `vitamind/core` dan setiap plugin sama-sama `require` leaf package `vitamind/plugin-sdk` lewat `composer.json`.

Arah yang benar: primitives UI (`components/ui/*`, `lib/utils.ts`'s `cn()`) semestinya jadi lapisan bersama yang di-import HOST maupun PLUGIN dari sumber netral yang sama — bukan plugin menjangkau ke dalam host.

## What Changes

- Perkenalkan alias `@vitamind/ui/*` yang di-resolve baik oleh host maupun oleh setiap plugin distributed, menunjuk ke satu lokasi fisik bersama untuk primitives UI (`components/ui/*` saat ini) dan `cn()` (`lib/utils.ts`).
- Update seluruh import primitives UI di source plugin (`dev-packages/vitamind-workspace-plugin/resources/js/**`, dan plugin distributed lain yang punya frontend) dari `@/components/ui/*` → `@vitamind/ui/*`.
- Update dokumentasi (`docs/local-plugins.md`'s bagian "Distributed plugin frontend") untuk menyatakan `@vitamind/ui/*` sebagai jalur yang benar untuk primitives UI dari plugin, bukan `@/`.
- **Eksplisit di luar scope**: ekstraksi `@vitamind/ui` jadi npm package sungguhan dengan `package.json`/semver sendiri, dan kemampuan tiap plugin men-declare dependency versi tersendiri terhadapnya. Itu ditunda ke proposal terpisah (`add-per-plugin-npm-dependencies` atau nama serupa) yang bersyarat pada kebutuhan distribusi arms-length yang nyata — belum ada sekarang. Change ini murni penataan alias/lokasi source di dalam model kompilasi single-Vite yang sudah berjalan, sama seperti `@plugin/{name}` tidak memerlukan build terpisah.
- **Tidak menyentuh** token/CSS (`resources/css/base.css`, `formalize-design-system-tokens`) — token tetap 100% domain host, plugin tetap tidak pernah membawa stylesheet sendiri. Yang berubah hanya jalur import untuk KOMPONEN React primitives, bukan token warnanya.

Ini adalah catatan keputusan arsitektur yang sengaja **ditunda** — didokumentasikan sekarang selagi alasannya masih segar, bukan untuk dieksekusi segera. Tidak ada konsumen yang terblokir olehnya saat ini.

## Capabilities

### New Capabilities
(tidak ada)

### Modified Capabilities
- `plugin-frontend-distribution`: menambah requirement baru — primitives UI React (bukan cuma Tailwind utility classes/token, yang sudah diatur requirement "Plugin Tailwind classes are generated from host design tokens") diakses plugin lewat alias netral `@vitamind/ui/*`, bukan alias host `@/*`. Requirement token/Tailwind yang sudah ada TIDAK berubah — plugin tetap tidak pernah ship stylesheet/token sendiri.

## Impact

- **Affected code**: `vite.config.ts` (tambah alias `@vitamind/ui`), `tsconfig.json` (tambah `paths` entry), seluruh file di `dev-packages/vitamind-*/resources/js/**` yang saat ini `import ... from '@/components/ui/...'` atau `'@/lib/utils'`.
- **Affected docs**: `docs/local-plugins.md`.
- **Dependencies**: tidak memblokir dan tidak diblokir oleh `formalize-design-system-tokens` (domain berbeda: token CSS vs. komponen React) maupun `stabilize-vitamind-packages`. Tidak ada konsumen dogfooding yang menunggu change ini.
- **Breaking changes**: tidak ada breaking change pada API publik plugin manapun — murni penataan alias import internal repo ini, aman terhadap model fork-copy-no-live-sync maupun fork-resync yang sudah didokumentasikan.
