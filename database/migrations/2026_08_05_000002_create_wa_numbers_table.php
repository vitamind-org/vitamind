<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `workspace_id` is nullable and unconstrained per
     * `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`'s own requirement:
     * the trait must keep working on installs where the workspaces feature
     * (and its table) isn't present at all.
     */
    public function up(): void
    {
        Schema::create('wa_numbers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->foreignId('wa_instance_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('phone_number')->nullable();
            $table->string('jid')->nullable();
            $table->string('status')->default('disconnected');
            $table->timestamps();

            $table->unique(['wa_instance_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_numbers');
    }
};
