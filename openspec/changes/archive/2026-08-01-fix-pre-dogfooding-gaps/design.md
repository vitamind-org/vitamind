## Context

`phase1-standalone-boilerplate` (archived di `openspec/changes/archive/2026-07-30-phase1-standalone-boilerplate/`) sudah mengantisipasi sebagian risiko ini di tabel risikonya sendiri — misalnya "Namespace conflicts (App\Models\User vs Core\Models\User)" dengan mitigasi "Boilerplate User extends Core BaseUser" — tapi mitigasi itu didokumentasikan sebagai instruksi manual (`MIGRATION_GUIDE.md`), bukan diverifikasi lewat instalasi fresh sungguhan. `stabilize-vitamind-packages` membuka gate dogfooding berdasarkan asumsi bahwa ekstraksi Phase 1 "selesai secara struktural" (§0.1), tanpa ada langkah yang benar-benar menjalankan `laravel new` + `composer require vitamind/core` dari nol.

Sesi eksplorasi kode langsung (bukan menjalankan instalasi) menemukan 5 gap konkret. Semuanya lolos tak terdeteksi karena satu alasan yang sama: repo `vitamin-d` ini sendiri BUKAN fresh install — ia sudah punya `app/Models/User.php`, `config/vitamin-d.php`, dan riwayat migrasi dari sebelum ekstraksi Phase 1, sehingga gap yang hanya muncul di instalasi benar-benar baru tidak pernah teruji terhadap dirinya sendiri.

## Goals / Non-Goals

**Goals:**
- Menutup 5 gap konkret yang mencegah `vitamind/core` (+ `vitamind/workspace-plugin`) benar-benar berfungsi di fresh Laravel installation, sebagaimana diklaim di Goal #1 `phase1-standalone-boilerplate`
- Memverifikasi klaim itu secara empiris (instalasi fresh sungguhan), bukan hanya lewat pembacaan kode
- Memperbarui dokumentasi (`MIGRATION_GUIDE.md`, `README.md`, `CONTRIBUTING.md`) supaya instruksinya sesuai kenyataan kode setelah perbaikan
- Menuntaskan ini sebelum `stabilize-vitamind-packages` mulai dogfooding ke proyek konsumen nyata

**Non-Goals:**
- Tidak menambah kapabilitas baru (mis. registry plugin GitHub, fitur workspace baru) — murni perbaikan terhadap klaim yang sudah ada
- Tidak mengubah threshold atau mekanisme gate `stabilize-vitamind-packages` itu sendiri — change ini hanya jadi prasyaratnya
- Tidak mengklarifikasi/memperbaiki temuan sampingan `server-provider`/`dns-provider`/`plugins.views` config (kemungkinan fitur yang memang belum dibangun) — dicatat di Open Questions, bukan di-scope ke tasks.md

## Decisions

### D1: Hapus migration `users`/`cache`/`jobs` dari `vitamind-core`, jangan dokumentasikan sebagai langkah manual

**Keputusan**: Hapus `0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php` dari `dev-packages/vitamind-core/database/migrations/`. Biarkan app konsumen memakai migration bawaan Laravel-nya sendiri (selalu ada di fresh install). `vitamind-core` hanya menyediakan migration ALTER incremental (`add_auth_fields_to_users_table`, `add_two_factor_columns_to_users_table`) plus tabel miliknya sendiri (`plugins`, `plugin_errors`, `passkeys`, `personal_access_tokens`).

**Rasional**: Isi ketiga file itu identik byte-per-byte dengan skeleton Laravel, nol modifikasi VitaminD. Pola yang benar (ALTER terpisah) sudah terbukti berfungsi dua kali di package yang sama.

**Alternatif dipertimbangkan:**
- A) Biarkan seperti sekarang, tambahkan instruksi "hapus dulu migration users/cache/jobs bawaan Laravel-mu" di `MIGRATION_GUIDE.md` → Ditolak: rapuh (bergantung developer ingat langkah manual), dan bahkan kalau diikuti dengan benar tetap tidak memberi nilai apa pun karena isinya 100% identik dengan yang sudah mereka punya
- B) Tambahkan guard `Schema::hasTable()` di `up()` sebelum `Schema::create()` → Ditolak: menutupi akar masalah, migration jadi tidak reversibel dengan bersih (`down()` bisa salah drop tabel yang bukan dibuatnya)
- C) Hapus 3 file, andalkan migration bawaan Laravel yang memang selalu ada → **Dipilih**

### D2: Kolisi `personal_access_tokens` — perbaikan dokumentasi, bukan kode

**Keputusan**: Migration `create_personal_access_tokens_table` TETAP di `vitamind-core` (Sanctum sendiri tidak auto-load migration-nya). `MIGRATION_GUIDE.md` ditambah peringatan eksplisit: jangan jalankan `php artisan install:api` berdampingan dengan `vitamind/core`.

**Rasional**: Beda karakter dari D1 — kolisi ini bersyarat (hanya terjadi kalau developer eksplisit publish migration Sanctum), bukan otomatis. Root cause-nya "dua sumber sama-sama klaim ownership atas migration yang sama", bukan file yang tidak ada gunanya.

**Alternatif dipertimbangkan:**
- A) Ganti nama file migration core supaya identik dengan nama file migration Sanctum (`2019_12_14_000001_...`), supaya migrator Laravel menganggapnya migration yang sama → Ditolak: rapuh (bergantung kebetulan nama), membingungkan (file bertanggal 2019 di dalam package 2026), dan tetap tidak menyelesaikan kasus `install:api` dijalankan LEBIH DULU sebelum core di-require
- B) Tambah guard `Schema::hasTable()` → Ditolak, alasan sama seperti D1-B
- C) Dokumentasi larangan eksplisit → **Dipilih**

### D3: Perbaikan hardcode `App\Models\User` — dipisah per kategori risiko

**Keputusan**: ~20 titik pemakaian type-hint/docblock/`Gate::policy()`/`authorize()`/`Rule::unique()` diganti ke `use VitaminD\Core\Models\User;` langsung. 4 titik instantiation/query builder (`RegisteredUserController.php`, `CreateNewUser.php`, `CreateUser.php`, `Admin/UserController.php`) diganti resolve dinamis lewat `config('auth.providers.users.model')`.

**Rasional**: PHP type covariance dan `Gate::getPolicyFor()`/`class_parents()` sudah menangani subclass secara transparan untuk kategori type-hint — aman diganti tanpa efek samping. Tapi `Model::create()`/`Model::query()` selalu instantiate PERSIS kelas yang di-import, bukan subclass-nya — kalau dipaksa ke `CoreUser`, user baru yang dibuat lewat Core kehilangan mixin app-layer (mis. `HasWorkspaces`) di instance itu. `config('auth.providers.users.model')` adalah mekanisme Laravel yang sudah tersedia gratis untuk kasus ini — pola yang sama seperti `Sanctum::usePersonalAccessTokenModel()` yang sudah dipakai dengan benar untuk `PersonalAccessToken`.

**Alternatif dipertimbangkan:**
- A) Biarkan semua tetap `App\Models\User`, cukup dokumentasikan bahwa `App\Models\User extends CoreUser` wajib untuk SEMUA skenario instalasi (bukan cuma workspace) → Ditolak: tidak memperbaiki akar masalah "package bergantung ke namespace app"; kontrak implisit yang tidak ditegakkan kode apa pun
- B) Ganti SEMUA titik (termasuk instantiation) ke `VitaminD\Core\Models\User` → Ditolak: memperbaiki fresh-install-tanpa-User-model, tapi merusak kasus workspace (user baru kehilangan `HasWorkspaces` di instance yang baru dibuat)
- C) Bangun mekanisme swappable-model baru khusus User (seperti `Sanctum::usePersonalAccessTokenModel()`) → Ditolak sebagai berlebihan: `config('auth.providers.users.model')` sudah menjadi versi Laravel bawaan dari pola yang sama, tidak perlu duplikasi infrastruktur
- D) Kombinasi: type-hint → CoreUser langsung, instantiation → resolve dinamis via config → **Dipilih**

### D4: `vitamind-core` men-`mergeConfigFrom()` `config/vitamin-d.php` miliknya sendiri

**Keputusan**: Tambahkan `config/vitamin-d.php` di dalam `dev-packages/vitamind-core/` berisi default untuk semua key `vitamin-d.features.*` yang sudah ada di app-layer saat ini, lalu panggil `$this->mergeConfigFrom(__DIR__.'/../../config/vitamin-d.php', 'vitamin-d')` di `CoreServiceProvider::register()`. File app-layer (`vitamin-d/config/vitamin-d.php`) tetap ada sebagai override penuh (karena `mergeConfigFrom` Laravel melakukan merge dangkal di level top-key — kalau app sudah mendefinisikan key `features` secara utuh, punya app yang menang).

**Rasional**: `WorkspaceServiceProvider::register()`/`::boot()` memanggil `config('vitamin-d.features.workspaces')` TANPA default parameter — di fresh install tanpa file config apa pun, ini resolve ke `null`, membuat workspace plugin selalu mati apa pun isi `.env`-nya.

**Alternatif dipertimbangkan:**
- A) Tambahkan default parameter eksplisit di tiap pemanggilan `config('vitamin-d.features.X', false)` yang belum punya → Ditolak: tidak lengkap by construction — persis kesalahan yang sudah terjadi (2 dari banyak pemanggilan lupa diberi default), gampang terulang lagi di titik baru
- B) Pindahkan flag workspace ke config milik `workspace-plugin` sendiri, lepas dari `vitamin-d.php` → Ditolak: flag ini sudah didesain (D6, phase1) sebagai toggle keseluruhan aplikasi yang dibaca core maupun plugin; memecahnya ke 2 namespace menambah indirection tanpa manfaat
- C) `mergeConfigFrom()` config default milik package → **Dipilih**, pola standar Laravel package (mis. cara Sanctum publish `config/sanctum.php`)

### D5: Bersihkan `bootstrap/providers.php` dan hapus file bangkai `AppServiceProvider`

**Keputusan**: Hapus gate manual `if (config('vitamin-d.features.workspaces')) { $providers[] = WorkspaceServiceProvider::class; }` dari `bootstrap/providers.php` — andalkan auto-discovery Composer (`extra.laravel.providers` di `vitamind-workspace-plugin/composer.json`, sudah ada). Hapus total `dev-packages/vitamind-core/src/Providers/AppServiceProvider.php` (tidak terdaftar di manapun, isinya salah kalau sampai aktif).

**Rasional**: Gate manual itu bukan defense-in-depth — kalau `vitamind/workspace-plugin` tidak ter-require sama sekali, class `WorkspaceServiceProvider` tidak ada, dan baris ini akan fatal-error "Class not found" begitu flag di-set true tanpa package terpasang. Menghapusnya lebih aman, bukan sekadar lebih bersih. Klaim `MIGRATION_GUIDE.md` sendiri ("no bootstrap/providers.php edit needed") sudah mengasumsikan baris ini tidak ada.

**Alternatif dipertimbangkan:**
- A) Biarkan gate manual sebagai "jaga-jaga" → Ditolak, lihat rasional di atas — ini bukan jaga-jaga, ini jebakan tersembunyi
- B) Perbaiki (bukan hapus) `VitaminD\Core\Providers\AppServiceProvider` dan daftarkan dengan benar → Ditolak: seluruh tanggung jawabnya sudah dipegang penuh oleh `CoreServiceProvider`; tidak ada alasan package ship dua provider yang tumpang tindih
- C) Hapus keduanya → **Dipilih**

### D6: User/PersonalAccessToken lifecycle events untuk ekstensibilitas plugin (resolusi OQ4)

**Keputusan**: Perkenalkan 10 custom domain event di `VitaminD\Core\Events` untuk operasi admin CRUD pada `User`/`PersonalAccessToken`, di-dispatch eksplisit oleh Core, bukan mengandalkan Eloquent model event bawaan:

| Event | Payload | Dipicu di |
|---|---|---|
| `UserStoring` | `array $input` | Sebelum `CreateUser::create()` insert |
| `UserStored` | `User $user` | Setelah `CreateUser::create()` insert |
| `UserChanging` | `User $user, array $input` | Sebelum `UpdateUser::update()` mutasi field |
| `UserChanged` | `User $user` | Setelah `UpdateUser::update()` `save()` |
| `UserRemoving` | `User $user` | Sebelum `DeleteUser::delete()` (Action baru) |
| `UserRemoved` | `User $user` | Setelah `DeleteUser::delete()` |
| `ApiKeyStoring` | `User $user, array $input` | Sebelum `CreateApiKey::create()` |
| `ApiKeyStored` | `PersonalAccessToken $token` | Setelah `CreateApiKey::create()` |
| `ApiKeyRemoving` | `PersonalAccessToken $apiKey` | Sebelum `DeleteApiKey::delete()` (Action baru) |
| `ApiKeyRemoved` | `PersonalAccessToken $apiKey` | Setelah `DeleteApiKey::delete()` |

Penamaan sengaja bukan `-ing`/`-ed` pada kata create/update/delete (`Creating/Created`, `Updating/Updated`, `Deleting/Deleted`) supaya tidak rancu dengan nama native Eloquent model event — dipakai sinonim **Storing/Stored**, **Changing/Changed**, **Removing/Removed**. Tidak ada `ApiKeyChanging`/`ApiKeyChanged` karena API key tidak punya operasi update di codebase ini.

Scope sengaja dibatasi ke **admin-initiated CRUD saja** (`CreateUser`, `UpdateUser`, `Admin/UserController::destroy()`, `CreateApiKey`, `ApiKeyController::destroy()`) — bukan self-service flow (registrasi, profile update, password reset), karena risiko route-model-binding yang jadi motivasi OQ4 cuma ada di titik yang menerima parameter langsung dari route (`User $user`/`PersonalAccessToken $apiKey` yang di-bind implisit), bukan di titik yang beroperasi lewat `Auth::user()` (sudah resolve benar via `config('auth.providers.users.model')`).

`Admin/UserController::destroy()` dan `ApiKeyController::destroy()` sebelumnya memanggil `$user->delete()`/`$apiKey->delete()` inline (beda dari `store()`/`update()` yang sudah lewat Action). Diselaraskan sekalian dengan menambah `Actions/User/DeleteUser.php` dan `Actions/ApiKey/DeleteApiKey.php` — satu-satunya titik dispatch `*Removing`/`*Removed`, konsisten dengan pola Action yang sudah ada.

Sebagai bukti mekanisme benar-benar bekerja (bukan cuma infrastruktur kosong), `WorkspaceServiceProvider::boot()` mendaftarkan listener nyata pada `UserRemoving` yang menghapus baris `UserWorkspace` milik user yang dihapus (workspace itu sendiri, dan membership user lain, tidak ikut terhapus — keputusan eksplisit, bukan bug).

**Rasional**: Wildcard Eloquent listener (opsi yang tercatat semula di OQ4) adalah workaround untuk kelemahan Eloquent event — nama event Eloquent di-scope ke *runtime class* persis lewat late static binding, sehingga listener yang didaftarkan terhadap parent class (`CoreUser::deleting()`) tidak ikut terpanggil untuk instance subclass (`App\Models\User`). Custom event yang di-dispatch eksplisit oleh Core tidak punya masalah itu sama sekali — event terpicu terlepas dari `$user`/`$apiKey` instance `App\Models\*` atau Core polos, karena Core-lah yang membuat objek event-nya, bukan Eloquent yang menyimpulkan dari runtime class. Pola ini juga bukan baru — sudah ada presedennya di `VitaminD\Core\Events\PluginStateChanged` (dispatch dari 4 Action plugin, format `Dispatchable, SerializesModels` + readonly constructor-promoted properties), jadi konsisten dengan konvensi yang sudah berjalan, bukan pola baru yang harus dipelajari ulang.

**Alternatif dipertimbangkan:**
- A) Wildcard Eloquent listener (`Event::listen('eloquent.deleting: *', ...)`, filter `instanceof` di closure) → Ditolak: string event name tidak grep-able/IDE-navigable, closure harus filter SEMUA model (Plugin, Workspace, dll) yang fire event di seluruh app, bukan cuma User — indirection tanpa manfaat dibanding custom event
- B) Static hook per-class (`CoreUser::deleting(fn...)`) → Ditolak: tidak terpanggil untuk instance subclass (`App\Models\User`), akar masalah OQ4 justru soal ini — subclass yang paling sering dipakai di runtime malah tidak ter-cover
- C) Container-binding/interface resolver untuk route model binding (diusulkan user, dianalisis kritis saat sesi) → Ditolak: hanya menutup 1 dari 3 kategori risiko (route binding), tidak menyentuh `Model::create()`/`Model::query()`/relasi Eloquent yang tetap butuh `config('auth.providers.users.model')` terpisah; binding concrete class secara global di container juga punya blast radius lebih luas dari yang dibutuhkan (memengaruhi semua `app(CoreUser::class)` di manapun, bukan cuma 2 route param yang jadi target)
- D) Custom domain event, dispatch eksplisit oleh Core, konsumsi lewat listener biasa → **Dipilih**

**Implementasi**: `dev-packages/vitamind-core/src/Events/{User,ApiKey}{Storing,Stored,Changing,Changed,Removing,Removed}.php` (10 file), `Actions/User/DeleteUser.php`, `Actions/ApiKey/DeleteApiKey.php` (baru); `Actions/User/CreateUser.php`, `Actions/User/UpdateUser.php`, `Actions/ApiKey/CreateApiKey.php`, `Http/Controllers/Admin/UserController.php`, `Http/Controllers/ApiKeyController.php` (dispatch/delegasi ke Action baru); `vitamind-workspace-plugin/src/Providers/WorkspaceServiceProvider.php` (`registerEventListeners()`, dipanggil dari `boot()` di dalam guard flag workspace yang sudah ada). Test: `tests/Feature/Admin/UserManagementTest.php`, `tests/Feature/ApiKeyManagementTest.php` (assert event dispatch dengan payload benar), `tests/Feature/WorkspaceUserCleanupTest.php` (baru — cleanup nyata, workspace tidak ikut terhapus, membership user lain tidak terpengaruh). 97/97 test lolos (89 sebelumnya + 8 baru).

**Catatan**: `Admin/UserController::destroy()`/`ApiKeyController::destroy()` masih menerima route-bound `$user`/`$apiKey` bertipe Core polos (perilaku dari D3, tidak diubah oleh D6) — D6 tidak "memperbaiki" tipe instance itu, tapi menyediakan jalur bagi plugin untuk bereaksi terhadap operasi CRUD-nya tanpa perlu tahu/peduli instance konkretnya apa.

## Risks / Trade-offs

| Risk | Mitigasi |
|------|----------|
| **D3 kategori type-hint menyentuh ~20 file** — risiko regresi tersebar luas | Jalankan `php artisan test` penuh setelah setiap kategori selesai, plus verifikasi manual jalur `admin/users`, `settings/profile`, 2FA sesuai langkah verifikasi `MIGRATION_GUIDE.md` sendiri |
| **D3 kategori instantiation adalah perubahan paling berisiko** — menyentuh alur pembuatan user sungguhan (registrasi, admin create-user) | Tambah test yang menegaskan user yang baru dibuat adalah instance `App\Models\User` (bukan `VitaminD\Core\Models\User` polos) dan tetap punya method `HasWorkspaces` saat fitur workspace aktif |
| **D1 menghapus migration bisa memutus environment yang kadung sudah `migrate` memakai copy lama** — termasuk repo `vitamin-d` ini sendiri di masa lalu, atau dev environment mana pun yang sempat migrate sebelum fix ini | `database/migrations` di boilerplate ini sudah kosong sejak Phase 1 (tidak terpengaruh), dan 0 dari 3 proyek dogfooding sudah mulai (dikonfirmasi `stabilize-vitamind-packages` §1) — blast radius hari ini nol untuk konsumen eksternal. Tetap catat sebagai catatan rilis untuk dev environment internal yang mungkin sudah migrate |
| **D4 mengubah precedence config** — sekarang ada 2 lapis (package default + app override) alih-alih 1 sumber tunggal | `mergeConfigFrom` Laravel melakukan merge dangkal per top-level key; karena app ini sudah mendefinisikan key `features` secara utuh, perilakunya tidak berubah untuk app ini — tambahkan test khusus skenario "tanpa config app-layer sama sekali" untuk memastikan default package saja sudah cukup |
| **Belum ada langkah dogfooding yang benar-benar menjalankan fresh install** — semua 5 temuan berasal dari pembacaan kode, bukan eksekusi nyata | Tasks.md mewajibkan verifikasi end-to-end (`laravel new` + `composer require vitamind/core` [+ workspace-plugin] di direktori terpisah) sebagai bagian dari definition of done, bukan opsional |

## Migration Plan

1. **D1 — Hapus migration duplikat** (rendah risiko, mekanis): hapus 3 file, jalankan `php artisan migrate:fresh` di boilerplate ini untuk pastikan tidak ada yang bergantung padanya
2. **D4 — Config merge** (memungkinkan verifikasi D5 & D3 selanjutnya lebih reliable): tambah `config/vitamin-d.php` package + `mergeConfigFrom`
3. **D5 — Bersihkan bootstrap/providers.php + hapus AppServiceProvider bangkai**: mekanis, risiko rendah setelah D4 terverifikasi
4. **D3 — Perbaikan hardcode User**: kategori type-hint dulu (aman), lalu kategori instantiation (butuh test coverage tambahan)
5. **D2 — Dokumentasi larangan `install:api`**: dokumentasi saja
6. **Update semua dokumentasi** (`MIGRATION_GUIDE.md`, `README.md`, `CONTRIBUTING.md`) supaya konsisten dengan kenyataan kode pasca-fix
7. **Verifikasi end-to-end**: `laravel new` di direktori terpisah → `composer require vitamind/core` saja → verifikasi rute/migrasi/auth jalan → ulangi dengan `vitamind/workspace-plugin` ditambahkan
8. **Regresi penuh**: `php artisan test` di boilerplate ini

## Open Questions

- **OQ1**: Default `config/vitamin-d.php` sebaiknya di-ship dari `vitamind-core` saja (karena baru `workspaces` yang jadi feature flag lintas-package saat ini), atau apakah `plugin-sdk` butuh mekanisme feature-flag config generik untuk plugin 1st-party lain di masa depan? — Belum perlu diputuskan sekarang; revisit kalau ada plugin kedua yang butuh pola serupa
- **OQ2**: Untuk D3 kategori instantiation, apakah 4 titik `config('auth.providers.users.model')` sebaiknya dibungkus helper kecil (mis. `VitaminD\Core\Support\authUserModel()`) supaya tidak duplikatif, atau inline saja karena cuma 4 titik? — Condong ke helper kecil untuk konsistensi, tapi tidak blocking
- **OQ3**: Verifikasi end-to-end (langkah 7 Migration Plan) — pakai direktori scratch sekali-pakai, atau disiapkan sebagai environment terus-menerus yang dipakai ulang tiap kali ada perubahan ke core/workspace-plugin ke depannya? — Diputuskan saat tasks.md dieksekusi, bukan blocker untuk proposal ini
- **OQ4** — **RESOLVED, lihat D6.** (ditemukan saat eksekusi tasks.md 4.2–4.3): dua titik route model binding implisit Laravel ikut terkena penggantian import type-hint, dengan risiko yang sama:
  - `Admin/UserController.php::update()`/`::destroy()` — parameter `User $user`, sekarang `VitaminD\Core\Models\User`
  - `ApiKeyController.php::destroy()` — parameter `PersonalAccessToken $apiKey`, sekarang `VitaminD\Core\Models\PersonalAccessToken`

  Untuk route model binding implisit, Laravel meng-instantiate PERSIS class yang di-type-hint (lewat `$container->make($class)` sebelum `resolveRouteBinding()`) — bukan lewat `config('auth.providers.users.model')` atau `Sanctum::usePersonalAccessTokenModel()`. Artinya `$user`/`$apiKey` yang di-bind ke route sekarang jadi instance Core polos, kehilangan mixin app-layer (`HasWorkspaces` pada User, `HasWorkspaceScopedTokens` pada PersonalAccessToken).

  Dicek konkret untuk kedua titik: hari ini nol bug. Tidak ada Observer/event hook terdaftar untuk `User` atau `PersonalAccessToken` di codebase ini, dan keempat method (`UserController::update/destroy`, `ApiKeyController::destroy`) tidak memanggil method apa pun dari `HasWorkspaces`/`HasWorkspaceScopedTokens` — hanya `save()`/`delete()` dasar. Tapi ini jadi jebakan fatal-error ("call to undefined method") kalau nanti ada yang menambah logika workspace-aware langsung pada `$user`/`$apiKey` di titik-titik ini tanpa sadar tipenya sudah instance Core polos.

  **Resolusi**: Ditutup lewat custom domain event (bukan wildcard Eloquent listener seperti draf awal tech-debt ini) — lihat **D6** untuk desain lengkap, alternatif yang dipertimbangkan, dan daftar file yang diimplementasikan. Sudah diimplementasikan dan diverifikasi (97/97 test), bukan lagi follow-up terbuka.
- **OQ5** (temuan sampingan, task 6.5 — bukan bagian dari fix ini): `GetBootstrap.php` membaca `config('server-provider.providers')`, `config('dns-provider.providers')`, dan `config('plugins.views')` (semuanya via `?? []`, jadi tidak fatal), tapi tidak ada satu pun file config (`server-provider.php`, `dns-provider.php`, `plugins.php`) yang pernah ada di package atau boilerplate manapun di repo ini. Kemungkinan ini sisa dari fitur yang direncanakan tapi belum dibangun, bukan bug. Perlu diklarifikasi terpisah: apakah ini scaffolding untuk fitur mendatang (server/DNS provider integration untuk deployment, plugin-supplied views) yang perlu didokumentasikan sebagai "belum tersedia", atau dead code yang harus dihapus. Tidak masuk scope `fix-pre-dogfooding-gaps` (lihat Non-Goals).
