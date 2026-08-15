// Distributed plugins (Composer/GitHub) resolved via vite.config.ts's
// dynamically-generated `@plugin/{kebab-name}` aliases (vendor/vitamind/*).
// The plugin set isn't known statically, so this stays a wildcard ambient
// module rather than one tsconfig `paths` entry per plugin.
//
// This has to live in its own file with no top-level import/export: a
// `declare module` wildcard only registers as ambient when the containing
// file has none, since a top-level import/export turns the whole file into
// a module and the block stops matching real specifiers (verified: moving
// this into global.d.ts, which imports from '@inertiajs/core' etc., breaks
// it).
declare module '@plugin/*';
