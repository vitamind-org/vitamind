## Context

`phase1-standalone-boilerplate` mengekstrak `vitamind/core` dan `vitamind/workspace-plugin` dari boilerplate `vitamin-d` menjadi package standalone yang di-consume via `dev-packages/` path repository. Pekerjaan ekstraksi (§1–13) sudah selesai dan sudah didogfooding sekali secara internal (TodoPlugin, lihat `phase1-standalone-boilerplate` §12.9–12.14). Sebelum package ini dipublish ke Packagist (`publish-vitamind-packages`), tim ingin bukti tambahan dari proyek konsumen nyata di luar repo `vitamin-d` — karena API/struktur package baru stabil kalau sudah dipakai lebih dari satu konteks proyek.

Sebelumnya, gate ini (§16) hidup di dalam `phase1-standalone-boilerplate`, dan closure phase1 (§15.6) menunggu gate ini lulus. Karena timeline 3 proyek konsumen berada di luar kendali langsung repo ini, itu membuat phase1 tidak bisa ditutup meski pekerjaan ekstraksinya sendiri sudah selesai. Change ini memindahkan gate tersebut keluar dari phase1.

## Goals / Non-Goals

**Goals:**
- Memvalidasi `vitamind/core` dan `vitamind/workspace-plugin` di ≥2 dari 3 proyek konsumen nyata (BukuWarga, LembarUji, UangKas) tanpa breaking change.
- Menyediakan gate masuk yang jelas dan dapat dirujuk untuk `publish-vitamind-packages`, terlepas dari status `phase1-standalone-boilerplate`.

**Non-Goals:**
- Tidak melakukan perubahan kode pada `vitamind/core` atau `vitamind/workspace-plugin` sebagai bagian dari change ini — jika sebuah proyek konsumen menemukan kebutuhan breaking change, itu ditangani sebagai change terpisah terhadap package yang bersangkutan, bukan di sini.
- Tidak mempublish ke Packagist — itu tetap tanggung jawab `publish-vitamind-packages`.

## Decisions

- **Pisahkan gate dari phase1, jangan skip gate-nya.** Alternatif yang dipertimbangkan: menurunkan gate jadi rekomendasi non-blocking di `publish-vitamind-packages` supaya phase1 bisa langsung ditutup tanpa change baru. Ditolak — dogfooding oleh proyek nyata adalah sinyal stabilitas yang sengaja diminta sebelum publish; melemahkannya jadi rekomendasi menghilangkan nilai gate itu sendiri. Memisahkannya jadi change sendiri mempertahankan gate sekaligus melepas phase1 dari timeline eksternal.
- **Gate capability (`package-stability-gate`) didefinisikan di change ini, bukan di `publish-vitamind-packages`.** Change yang mendefinisikan syarat masuk sebuah gate seharusnya memiliki spec-nya sendiri; `publish-vitamind-packages` cukup merujuk ke sini sebagai prasyarat, sama seperti sebelumnya merujuk ke `phase1-standalone-boilerplate §16`.
- **Threshold tetap ≥2 dari 3 proyek**, tidak diubah jadi 3/3. Ini keputusan yang sudah diambil sebelumnya di `phase1-standalone-boilerplate`; change ini hanya memindahkan, tidak meninjau ulang threshold.

## Risks / Trade-offs

- **[Risk]** Timeline 3 proyek eksternal tetap tidak terkendali langsung oleh repo ini, sehingga change ini sendiri bisa "menggantung" lama → **Mitigasi**: karena sudah dipisah dari phase1, ini tidak lagi memblokir closure phase1 maupun pekerjaan lain di `vitamin-d`. Change ini boleh tetap open/in-progress dalam waktu lama tanpa efek samping ke change lain selain `publish-vitamind-packages`.
- **[Risk]** Jika sebuah proyek konsumen menemukan breaking change yang perlu, keputusan "apakah diadopsi" bisa memperlambat gate → **Mitigasi**: proyek yang menemukan breaking change tidak dihitung ke gate sampai perubahan tersebut (jika diadopsi) selesai diimplementasikan sebagai change terpisah dan integrasi ulang berhasil (lihat scenario di `specs/package-stability-gate/spec.md`).
