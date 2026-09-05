<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Workspace ownership is tracked as plain data here, not as a role —
     * `CreateWorkspace` sets this to the creating user directly, and
     * `WorkspacePolicy` checks it directly. No production data exists, so
     * this is a straightforward additive column rather than a backfill.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_id')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('owner_id');
        });
    }
};
