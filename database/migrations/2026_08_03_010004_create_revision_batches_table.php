<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('root_subject_type');
            $table->unsignedBigInteger('root_subject_id');
            $table->enum('operation', ['create', 'update', 'deactivate', 'reactivate', 'restore', 'delete']);
            $table->string('reason')->nullable();
            $table->char('correlation_id', 36);
            $table->unsignedBigInteger('restored_from_batch_id')->nullable();
            $table->unsignedBigInteger('restored_from_version_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // A correlation id is diagnostic, not an idempotency key.
            $table->unique(['tenant_id', 'id'], 'revision_batches_tenant_id_unique');
            $table->index('correlation_id');
            $table->index(['tenant_id', 'root_subject_type', 'root_subject_id', 'occurred_at'], 'revision_batches_history_index');

            $table->foreign('restored_from_batch_id')
                ->references('id')
                ->on('revision_batches')
                ->restrictOnDelete();
            $table->foreign('restored_from_version_id')
                ->references('id')
                ->on('versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_batches');
    }
};
