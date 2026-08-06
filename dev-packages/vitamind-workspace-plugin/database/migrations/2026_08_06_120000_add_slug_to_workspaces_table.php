<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        $this->backfillSlugs();

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * Names that only differ by case, whitespace, or punctuation normalize
     * to the same slug — assign the "clean" slug to whichever row is
     * processed first (ascending id) and suffix the rest (-2, -3, ...) so
     * the migration succeeds regardless of pre-existing near-duplicates.
     */
    private function backfillSlugs(): void
    {
        DB::table('workspaces')->orderBy('id')->select('id', 'name')
            ->chunkById(200, function ($workspaces): void {
                foreach ($workspaces as $workspace) {
                    $base = Str::substr(Str::slug($workspace->name) ?: 'workspace', 0, 255);
                    $slug = $base;
                    $suffix = 2;

                    while (DB::table('workspaces')->where('slug', $slug)->where('id', '!=', $workspace->id)->exists()) {
                        $suffixValue = "-{$suffix}";
                        $slug = Str::substr($base, 0, 255 - strlen($suffixValue)).$suffixValue;
                        $suffix++;
                    }

                    DB::table('workspaces')->where('id', $workspace->id)->update(['slug' => $slug]);
                }
            });
    }
};
