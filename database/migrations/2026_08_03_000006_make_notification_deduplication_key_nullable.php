<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $deduplicationColumn = collect(Schema::getColumns('notifications'))
            ->firstWhere('name', 'deduplication_key');

        if ($deduplicationColumn === null) {
            throw new LogicException('The notifications deduplication key column is missing.');
        }

        if (! $deduplicationColumn['nullable']) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('deduplication_key', 191)->nullable()->change();
            });
        }

        if (Schema::hasIndex('notifications', 'notifications_deduplication_key_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_deduplication_key_index');
            });
        }

        if (! Schema::hasIndex('notifications', 'notifications_notifiable_dedup_unique', 'unique')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unique(
                    ['notifiable_type', 'notifiable_id', 'deduplication_key'],
                    'notifications_notifiable_dedup_unique',
                );
            });
        }
    }

    public function down(): void
    {
        // Existing native notifications may contain null keys, so this correction is forward-only.
    }
};
