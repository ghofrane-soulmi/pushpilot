<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('deploy');
            $table->string('trigger', 20)->default('manual');
            $table->string('status', 20)->default('queued');
            $table->string('commit_hash', 40)->nullable();
            $table->text('commit_message')->nullable();
            $table->string('commit_author')->nullable();
            $table->string('release_name', 20)->nullable();
            $table->string('current_step', 40)->nullable();
            $table->json('warnings')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'id']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40);
            $table->text('line');
            $table->string('stream', 6)->default('stdout');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['deployment_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_logs');
        Schema::dropIfExists('deployments');
    }
};
