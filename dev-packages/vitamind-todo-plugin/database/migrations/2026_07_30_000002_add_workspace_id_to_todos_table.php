<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable and deliberately unconstrained: this plugin also installs on
     * single-tenant projects, where the workspaces feature is off and there is
     * no `workspaces` table for a foreign key to reference.
     *
     * Rows that predate this column keep a null `workspace_id`, which the
     * BelongsToWorkspace scope treats as belonging to no workspace — so they
     * stop being visible once the feature is on. A plugin carrying real data
     * across this change would need to decide where those rows belong and
     * backfill them; there is nothing to migrate for a fresh install.
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
    }
};
