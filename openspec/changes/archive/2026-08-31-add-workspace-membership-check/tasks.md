## 1. Shared primitive in plugin-sdk

- [x] 1.1 Create `dev-packages/vitamind-plugin-sdk/src/Support/WorkspaceMembership.php` with `public static function check(?Authenticatable $user, int|string $workspaceId): bool`, porting the canonical-id regex, feature-flag gate, and no-user gate from `WorkspaceChannelAuthorization::check()` verbatim.
- [x] 1.2 Implement the membership lookup: `DB::table('user_workspace')->where('user_id', $user->getAuthIdentifier())->where('workspace_id', (int) $workspaceId)->exists()`.
- [x] 1.3 Add `dev-packages/vitamind-plugin-sdk/tests/Support/WorkspaceMembershipTest.php` covering: member authorized, non-member denied, workspace-id-equals-current-workspace-but-no-membership-row denied, feature disabled denies, no user denies, each non-canonical id form denied (`"1x"`, `"01"`, `"1.0"`, `"-1"`, `"0"`, `""`).

## 2. Refactor realtime-plugin to delegate

- [x] 2.1 Add `vitamind/plugin-sdk` as an explicit dependency check point (already required by `vitamind/realtime-plugin`'s composer.json — confirm, don't add if already present).
- [x] 2.2 Replace the body of `dev-packages/vitamind-realtime-plugin/src/Support/WorkspaceChannelAuthorization::check()` with a one-line delegation to `VitaminD\PluginSdk\Support\WorkspaceMembership::check()`; remove the now-dead inline regex/query code.
- [x] 2.3 Update the class docblock to reflect that it now delegates to the shared primitive rather than implementing the check itself.
- [x] 2.4 Run `dev-packages/vitamind-realtime-plugin/tests/Unit/WorkspaceChannelAuthorizationTest.php` and `tests/Feature/ChannelAuthorizationTest.php` unmodified and confirm all cases still pass.

## 3. Verification

- [x] 3.1 Run the full plugin-sdk test suite (`dev-packages/vitamind-plugin-sdk/tests`) and confirm no regressions.
- [x] 3.2 Run the full realtime-plugin test suite and confirm no regressions.
- [x] 3.3 Confirm `vitamind/plugin-sdk`'s `composer.json` still does not require `vitamind/workspace-plugin`.
