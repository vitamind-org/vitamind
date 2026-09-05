<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('role');
            $table->string('scope_type')->nullable();
            $table->unsignedInteger('scope_id')->nullable();
            $table->timestamps();

            // Doubles as the lookup index for the common query shape
            // (WHERE user_id = ? AND scope_type = ? AND scope_id = ?) — no
            // separate index is needed alongside it.
            $table->unique(['user_id', 'role', 'scope_type', 'scope_id'], 'user_roles_user_role_scope_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
