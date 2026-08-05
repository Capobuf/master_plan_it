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
            $table->foreignId('revision_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->constrained('versions')->restrictOnDelete();
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');
            $table->unsignedBigInteger('sequence');
            $table->timestamps();

            $table->unique(['revision_batch_id', 'version_id']);
            $table->unique(['revision_batch_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_batch_items');
    }
};
