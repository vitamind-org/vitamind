<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `role` column created here is later dropped (see
     * 2026_09_05_000000_replace_role_column_on_user_workspace_table) in
     * favor of `vitamind-core`'s `user_roles` table — kept as a plain
     * string default rather than the retired `UserRole` enum so this
     * migration keeps running standalone after that enum is deleted.
     */
    public function up(): void
    {
        Schema::create('user_workspace', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('workspace_id');
            $table->string('role')->default('user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_workspace');
    }
};
