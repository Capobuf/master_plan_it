<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_batch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('revision_batch_id');
            $table->foreignId('version_id')->constrained('versions')->restrictOnDelete();
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');
            $table->unsignedBigInteger('sequence');
            $table->timestamps();

            $table->unique(['revision_batch_id', 'version_id']);
            $table->unique(['revision_batch_id', 'sequence']);
            $table->foreign(['tenant_id', 'revision_batch_id'], 'revision_items_tenant_batch_fk')
                ->references(['tenant_id', 'id'])->on('revision_batches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_batch_items');
    }
};
