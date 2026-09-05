<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the old scalar `role` tier column (enforced against the
     * retiring `UserRole` enum) with the two fields an invitation needs to
     * carry its Admin-or-role choice until acceptance (see
     * `InviteToWorkspace`/`AcceptWorkspaceInvite`): `invited_role` (a
     * `RegisterRole` key, or null when Admin was chosen) and
     * `is_admin_grant` (true when Admin was chosen instead). Actual
     * membership-tier role assignment now lives in `user_roles`
     * (`vitamind-core`), scoped to the workspace — no production data
     * exists, so this is a direct replacement rather than a backfill.
     */
    public function up(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->dropColumn('role');
            $table->string('invited_role')->nullable()->after('email');
            $table->boolean('is_admin_grant')->default(false)->after('invited_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->dropColumn(['invited_role', 'is_admin_grant']);
            $table->string('role')->default('user');
        });
    }
};
