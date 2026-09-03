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
        Schema::create('folders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Nullable and deliberately unconstrained — no `->constrained('workspaces')`,
            // per plugin-workspace-scoping: the plugin must install cleanly on a
            // single-tenant project with no `workspaces` table present.
            $table->foreignId('workspace_id')->nullable();

            // `app`/`public` are deferred — see design.md Non-Goals.
            $table->enum('visibility', ['user', 'workspace'])->default('user');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
