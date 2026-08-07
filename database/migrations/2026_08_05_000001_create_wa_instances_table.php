<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('base_url');
            $table->string('basic_auth_username');
            $table->text('basic_auth_password');
            $table->text('webhook_secret')->nullable();
            $table->string('state')->default('provisioning');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_instances');
    }
};
