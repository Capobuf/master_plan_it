<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('vat_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'active', 'name']);
            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
