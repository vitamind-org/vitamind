<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('role');
        });

        // MySQL does not support filtered/partial unique indexes, so uniqueness
        // of "at most one is_default = true row per user" is enforced via a
        // STORED GENERATED column that collapses to NULL when is_default is
        // false, unique-indexed on that column (NULLs don't conflict).
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->unsignedBigInteger('default_owner_id')->nullable()
                ->storedAs('CASE WHEN is_default THEN user_id ELSE NULL END')
                ->after('is_default');
        });

        $this->backfillDefaults();

        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->unique('default_owner_id', 'uq_user_workspace_default_owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_workspace', function (Blueprint $table): void {
            $table->dropUnique('uq_user_workspace_default_owner');
            $table->dropColumn('default_owner_id');
            $table->dropColumn('is_default');
        });
    }

    /**
     * For each user with a resolvable current_workspace_id, flag their
     * matching user_workspace row as default; otherwise fall back to their
     * earliest-created membership.
     */
    private function backfillDefaults(): void
    {
        DB::table('users')->orderBy('id')->select('id', 'current_workspace_id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $defaultRow = null;

                    if ($user->current_workspace_id) {
                        $defaultRow = DB::table('user_workspace')
                            ->where('user_id', $user->id)
                            ->where('workspace_id', $user->current_workspace_id)
                            ->first();
                    }

                    if (! $defaultRow) {
                        $defaultRow = DB::table('user_workspace')
                            ->where('user_id', $user->id)
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->first();
                    }

                    if ($defaultRow) {
                        DB::table('user_workspace')->where('id', $defaultRow->id)->update(['is_default' => true]);
                    }
                }
            });
    }
};
