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
        // A racy check-then-insert in DiscoverPlugins could create more than one
        // row for the same (source, folder) pair. Keep the row that carries the
        // most admin-entered state (enabled, or named via the Enable action),
        // and drop the rest before the unique index below can be added.
        $duplicateGroups = DB::table('plugins')
            ->select('source', 'folder')
            ->groupBy('source', 'folder')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('plugins')
                ->where('source', $group->source)
                ->where('folder', $group->folder)
                ->orderByDesc('is_enabled')
                ->orderByDesc('name')
                ->orderBy('id')
                ->get();

            $rows->skip(1)->each(fn ($row) => DB::table('plugins')->where('id', $row->id)->delete());
        }

        Schema::table('plugins', function (Blueprint $table) {
            $table->unique(['source', 'folder']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropUnique(['source', 'folder']);
        });
    }
};
