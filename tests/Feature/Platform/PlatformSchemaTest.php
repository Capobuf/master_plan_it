<?php

namespace Tests\Feature\Platform;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Models\AuditEvent;
use App\Models\DatabaseNotification;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\PlatformSettingSeeder;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_platform_persistence_uses_the_approved_typed_schema(): void
    {
        $this->assertTrue(Schema::hasTable('platform_settings'));
        $this->assertTrue(Schema::hasColumns('platform_settings', [
            'id',
            'audit_retention_months',
            'lock_version',
            'updated_by_user_id',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('users', ['tenant_id', 'is_active', 'lock_version']));
        $this->assertTrue(Schema::hasColumns('audit_events', [
            'tenant_id',
            'actor_user_id',
            'actor_label',
            'event_type',
            'subject_type',
            'subject_id',
            'correlation_id',
            'properties',
            'occurred_at',
        ]));
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'deduplication_key',
        ]));

        $deduplicationColumn = collect(Schema::getColumns('notifications'))
            ->firstWhere('name', 'deduplication_key');

        $this->assertNotNull($deduplicationColumn);
        $this->assertTrue($deduplicationColumn['nullable']);
        $this->assertFalse(Schema::hasIndex('notifications', 'notifications_deduplication_key_index'));
        $this->assertTrue(Schema::hasIndex(
            'notifications',
            'notifications_notifiable_dedup_unique',
            'unique',
        ));

        $this->assertSame(24, (int) DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'platform_settings')
            ->where('column_name', 'audit_retention_months')
            ->value('column_default'));

        $this->assertSame(1, (int) DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'platform_settings')
            ->where('column_name', 'lock_version')
            ->value('column_default'));
    }

    public function test_platform_setting_is_one_bounded_global_row(): void
    {
        $setting = PlatformSetting::query()->create([
            'id' => 1,
            'audit_retention_months' => 24,
            'lock_version' => 1,
        ]);

        $this->assertSame(1, $setting->id);
        $this->assertSame(24, $setting->audit_retention_months);
        $this->assertSame(1, $setting->lock_version);

        $this->expectException(QueryException::class);

        DB::table('platform_settings')->insert([
            'id' => 2,
            'audit_retention_months' => 24,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[DataProvider('invalidRetentionValues')]
    public function test_platform_setting_rejects_out_of_range_retention(int $retentionMonths): void
    {
        $this->expectException(QueryException::class);

        DB::table('platform_settings')->insert([
            'id' => 1,
            'audit_retention_months' => $retentionMonths,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidRetentionValues(): array
    {
        return [
            'below lower bound' => [0],
            'above upper bound' => [121],
        ];
    }

    public function test_platform_setting_seeder_is_idempotent_and_preserves_existing_values(): void
    {
        DB::table('platform_settings')->where('id', 1)->delete();

        $seeder = new PlatformSettingSeeder;
        $seeder->run();

        $this->assertDatabaseHas('platform_settings', [
            'id' => 1,
            'audit_retention_months' => 24,
            'lock_version' => 1,
        ]);

        PlatformSetting::query()->findOrFail(1)->update([
            'audit_retention_months' => 36,
            'lock_version' => 2,
        ]);

        $seeder->run();

        $this->assertSame(1, PlatformSetting::query()->count());
        $this->assertDatabaseHas('platform_settings', [
            'id' => 1,
            'audit_retention_months' => 36,
            'lock_version' => 2,
        ]);
    }

    public function test_models_expose_only_the_required_platform_persistence_contract(): void
    {
        $user = User::factory()->create();
        $event = AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'actor_label' => $user->name,
            'event_type' => 'platform.setting.created',
            'correlation_id' => (string) str()->uuid(),
            'properties' => ['audit_retention_months' => 24],
            'occurred_at' => now(),
        ]);

        $this->assertTrue($user->is_active);
        $this->assertSame($user->id, $event->actor->id);
        $this->assertIsArray($event->properties);
        $this->assertFalse($event->timestamps);
        $this->assertInstanceOf(DatabaseNotification::class, new DatabaseNotification);
        $this->assertNotContains('password', (new AuditEvent)->getFillable());
        $this->assertNotContains('token', (new AuditEvent)->getFillable());
    }

    public function test_native_database_channel_persists_without_a_deduplication_key(): void
    {
        $user = User::factory()->create();
        $notification = new class extends Notification
        {
            /**
             * @return list<string>
             */
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            /**
             * @return array{message: string}
             */
            public function toArray(object $notifiable): array
            {
                return ['message' => 'Platform notification'];
            }
        };

        $user->notify($notification);

        $stored = $user->notifications()->firstOrFail();

        $this->assertInstanceOf(DatabaseNotification::class, $stored);
        $this->assertNull($stored->deduplication_key);
        $this->assertSame(['message' => 'Platform notification'], $stored->data);
        $this->assertDatabaseHas('notifications', [
            'id' => $stored->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'deduplication_key' => null,
        ]);
    }

    public function test_non_null_notification_occurrence_key_is_unique_per_recipient(): void
    {
        $user = User::factory()->create();
        $attributes = [
            'id' => (string) str()->uuid(),
            'type' => 'platform.notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Platform notification'],
            'deduplication_key' => 'platform:occurrence:1',
        ];

        DatabaseNotification::query()->create($attributes);

        $this->expectException(QueryException::class);

        DatabaseNotification::query()->create([
            ...$attributes,
            'id' => (string) str()->uuid(),
        ]);
    }

    public function test_notification_forward_correction_is_idempotent_on_the_desired_schema(): void
    {
        $migration = require database_path(
            'migrations/2026_08_03_000006_make_notification_deduplication_key_nullable.php',
        );

        $migration->up();
        $migration->up();

        $this->assertTrue(collect(Schema::getColumns('notifications'))
            ->firstWhere('name', 'deduplication_key')['nullable']);
        $this->assertTrue(Schema::hasIndex(
            'notifications',
            'notifications_notifiable_dedup_unique',
            'unique',
        ));
    }

    public function test_audit_event_rejects_model_builder_and_eventless_mutations(): void
    {
        $event = AuditEvent::query()->create([
            'event_type' => 'platform.setting.created',
            'correlation_id' => (string) str()->uuid(),
            'occurred_at' => now(),
        ]);

        $mutations = [
            'instance update' => fn () => $event->update(['event_type' => 'platform.setting.updated']),
            'instance eventless save' => fn () => Model::withoutEvents(
                fn () => $event->forceFill(['event_type' => 'platform.setting.updated'])->save(),
            ),
            'instance increment' => fn () => $event->increment('id'),
            'instance decrement' => fn () => $event->decrement('id'),
            'instance increment quietly' => fn () => $event->incrementQuietly('id'),
            'instance decrement quietly' => fn () => $event->decrementQuietly('id'),
            'instance increment each' => fn () => $event->incrementEach(['id' => 1]),
            'instance decrement each' => fn () => $event->decrementEach(['id' => 1]),
            'instance increment each quietly' => fn () => $event->incrementEachQuietly(['id' => 1]),
            'instance decrement each quietly' => fn () => $event->decrementEachQuietly(['id' => 1]),
            'builder update' => fn () => AuditEvent::query()->whereKey($event->id)
                ->update(['event_type' => 'platform.setting.updated']),
            'builder update or create' => fn () => AuditEvent::query()->updateOrCreate(
                ['id' => $event->id],
                ['event_type' => 'platform.setting.updated'],
            ),
            'builder update or insert' => fn () => AuditEvent::query()->updateOrInsert(
                ['id' => $event->id],
                ['event_type' => 'platform.setting.updated'],
            ),
            'builder update from' => fn () => AuditEvent::query()->whereKey($event->id)
                ->updateFrom(['event_type' => 'platform.setting.updated']),
            'builder upsert' => fn () => AuditEvent::query()->upsert(
                [[
                    'id' => $event->id,
                    'event_type' => 'platform.setting.updated',
                    'correlation_id' => $event->correlation_id,
                    'occurred_at' => $event->occurred_at,
                ]],
                ['id'],
                ['event_type'],
            ),
            'builder increment or create' => fn () => AuditEvent::query()->incrementOrCreate(
                ['id' => $event->id],
            ),
            'builder touch' => fn () => AuditEvent::query()->whereKey($event->id)->touch('occurred_at'),
            'builder increment' => fn () => AuditEvent::query()->whereKey($event->id)->increment('id'),
            'builder decrement' => fn () => AuditEvent::query()->whereKey($event->id)->decrement('id'),
            'builder increment each' => fn () => AuditEvent::query()->whereKey($event->id)
                ->incrementEach(['id' => 1]),
            'builder decrement each' => fn () => AuditEvent::query()->whereKey($event->id)
                ->decrementEach(['id' => 1]),
            'instance delete' => fn () => $event->delete(),
            'instance eventless delete' => fn () => Model::withoutEvents(fn () => $event->delete()),
            'builder delete' => fn () => AuditEvent::query()->whereKey($event->id)->delete(),
            'builder force delete' => fn () => AuditEvent::query()->whereKey($event->id)->forceDelete(),
        ];

        foreach ($mutations as $name => $mutation) {
            $this->assertAuditMutationRejected($mutation, $name);
        }

        $this->assertFalse(method_exists(AuditEvent::class, 'deleteForRetention'));
        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'event_type' => 'platform.setting.created',
        ]);
    }

    public function test_audit_builder_preserves_insert_and_read_paths(): void
    {
        $id = AuditEvent::query()->insertGetId([
            'event_type' => 'platform.audit.inserted',
            'correlation_id' => (string) str()->uuid(),
            'properties' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);

        $this->assertSame('platform.audit.inserted', AuditEvent::query()->findOrFail($id)->event_type);
        $this->assertGreaterThanOrEqual(1, AuditEvent::query()->count());
    }

    public function test_audit_recorder_persists_only_validated_properties(): void
    {
        $actor = User::factory()->create();
        $occurredAt = new DateTimeImmutable('2026-08-04T16:30:00+02:00');

        $event = (new AuditRecorder)->record(
            eventType: 'platform.setting.updated',
            correlationId: (string) str()->uuid(),
            properties: new AuditProperties([
                'audit_retention_months' => ['old' => 24, 'new' => 36],
            ]),
            actor: $actor,
            subject: $actor,
            occurredAt: $occurredAt,
        );

        $this->assertSame($actor->id, $event->actor_user_id);
        $this->assertSame($actor->name, $event->actor_label);
        $this->assertSame(User::class, $event->subject_type);
        $this->assertSame($actor->id, $event->subject_id);
        $this->assertSame(
            ['audit_retention_months' => ['old' => 24, 'new' => 36]],
            $event->properties,
        );
        $this->assertSame('2026-08-04 14:30:00', $event->occurred_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_current_application_code_has_no_raw_audit_table_mutation_escape_hatch(): void
    {
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                "/DB::table\\(\\s*['\"]audit_events['\"]\\s*\\)/",
                $file->getContents(),
                "Raw audit table access found in {$file->getRelativePathname()}.",
            );
        }
    }

    /**
     * @param  callable(): mixed  $mutation
     */
    private function assertAuditMutationRejected(callable $mutation, string $name = 'mutation'): void
    {
        try {
            $mutation();
            $this->fail("Audit event {$name} was accepted.");
        } catch (\LogicException $exception) {
            $this->assertSame('Audit events are append-only.', $exception->getMessage());
        }
    }
}
