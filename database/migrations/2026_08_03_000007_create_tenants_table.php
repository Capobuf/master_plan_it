<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('state', ['active', 'inactive'])->default('active');
            $table->string('currency_code', 3);
            $table->string('language_code', 10);
            $table->string('timezone');
            $table->decimal('default_vat_rate', 12, 2);
            $table->enum('budget_basis', ['net', 'gross'])->default('net');
            $table->unsignedBigInteger('attachment_quota_bytes')->default(2147483648);
            $table->boolean('deletion_reason_required')->default(false);
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('report_logo_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('state_changed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('state_changed_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['state', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
