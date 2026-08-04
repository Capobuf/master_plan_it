<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'parent_id', 'active']);
            $table->foreign(['tenant_id', 'parent_id'])
                ->references(['tenant_id', 'id'])
                ->on('cost_centers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
