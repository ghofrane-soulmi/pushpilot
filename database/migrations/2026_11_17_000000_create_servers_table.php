<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('host', 253);
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username', 32);
            $table->text('private_key');
            $table->text('public_key');
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
