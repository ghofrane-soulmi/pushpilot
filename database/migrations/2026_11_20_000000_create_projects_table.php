<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a server cannot be deleted while projects use it (SRV-5).
            $table->foreignId('server_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('repository_url', 255);
            $table->string('branch', 255);
            $table->string('deploy_path', 255);
            $table->text('deploy_private_key');
            $table->text('deploy_public_key');
            $table->text('webhook_secret');
            $table->text('env_content')->nullable();
            $table->json('shared_paths');
            $table->string('php_fpm_service', 50)->nullable();
            $table->boolean('run_composer')->default(true);
            $table->boolean('run_npm_build')->default(false);
            $table->boolean('run_migrations')->default(false);
            $table->boolean('queue_restart')->default(false);
            $table->string('health_check_url', 255)->nullable();
            $table->unsignedSmallInteger('health_check_status')->default(200);
            $table->boolean('auto_rollback')->default(false);
            $table->unsignedTinyInteger('keep_releases')->default(5);
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('env_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'deploy_path']);
        });

        Schema::create('project_hooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->text('command');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'stage', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_hooks');
        Schema::dropIfExists('projects');
    }
};
