<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Actions\PurgeAttachments;
use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Contracts\Actions\DeleteContract;
use App\Domain\Expenses\Actions\DeleteExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Projects\Actions\DeleteProject;
use App\Models\AuditEvent;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentTerminalPurgeTest extends TestCase
{
    use CreatesAttachmentFiles;
    use DatabaseTransactions;
    use InteractsWithAttachments;

    protected function tearDown(): void
    {
        $this->cleanupAttachmentFiles();
        $this->resetAttachmentPermissionScope();
        parent::tearDown();
    }

    public function test_expense_row_and_root_terminal_delete_purge_only_their_current_payloads(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = Expense::factory()->for($tenant)->create();
        $keptRow = ExpenseRow::factory()->for($tenant)->for($expense)->create(['position' => 1]);
        $removedRow = ExpenseRow::factory()->for($tenant)->for($expense)->create(['position' => 2]);
        $otherExpense = Expense::factory()->for($tenant)->create();

        $rootMedia = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf', 'spesa.pdf'), (string) str()->uuid());
        $keptMedia = app(UploadAttachment::class)->execute($actor, $context, $keptRow, $this->attachmentFile('pdf', 'riga-1.pdf'), (string) str()->uuid());
        $removedMedia = app(UploadAttachment::class)->execute($actor, $context, $removedRow, $this->attachmentFile('pdf', 'riga-2.pdf'), (string) str()->uuid());
        $otherMedia = app(UploadAttachment::class)->execute($actor, $context, $otherExpense, $this->attachmentFile('pdf', 'altra.pdf'), (string) str()->uuid());

        app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            $this->expenseData($expense),
            [$this->rowData($keptRow)],
            (string) str()->uuid(),
            [['id' => (int) $removedRow->getKey(), 'lock_version' => (int) $removedRow->lock_version]],
        );

        $this->assertDatabaseMissing('media', ['id' => $removedMedia->getKey()]);
        Storage::disk('attachments')->assertMissing($removedMedia->getPathRelativeToRoot());
        $this->assertDatabaseHas('media', ['id' => $rootMedia->getKey()]);
        $this->assertDatabaseHas('media', ['id' => $keptMedia->getKey()]);

        $expense->refresh();
        app(DeleteExpense::class)->execute($actor, $context, $expense, (int) $expense->lock_version, false, (string) str()->uuid());
        $this->assertDatabaseMissing('media', ['id' => $rootMedia->getKey()]);
        $this->assertDatabaseMissing('media', ['id' => $keptMedia->getKey()]);
        $this->assertDatabaseHas('media', ['id' => $otherMedia->getKey()]);
        Storage::disk('attachments')->assertExists($otherMedia->getPathRelativeToRoot());
    }

    public function test_contract_and_project_terminal_delete_purge_payloads_and_empty_purge_is_idempotent(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $parents = $this->supportedAttachmentParents($tenant);
        $contractMedia = app(UploadAttachment::class)->execute($actor, $context, $parents['contract'], $this->attachmentFile('pdf', 'contratto.pdf'), (string) str()->uuid());
        $projectMedia = app(UploadAttachment::class)->execute($actor, $context, $parents['project'], $this->attachmentFile('pdf', 'progetto.pdf'), (string) str()->uuid());

        app(DeleteContract::class)->execute($actor, $context, $parents['contract'], 1, null, (string) str()->uuid());
        app(DeleteProject::class)->execute($actor, $context, $parents['project'], 1, null, (string) str()->uuid());

        $this->assertSame(0, Media::query()->whereKey([$contractMedia->getKey(), $projectMedia->getKey()])->count());
        Storage::disk('attachments')->assertMissing($contractMedia->getPathRelativeToRoot());
        Storage::disk('attachments')->assertMissing($projectMedia->getPathRelativeToRoot());
        $this->assertSame(0, app(PurgeAttachments::class)->forParent($actor, $context, $parents['expense'], (string) str()->uuid()));
        $this->assertSame(0, app(PurgeAttachments::class)->forParent($actor, $context, $parents['expense'], (string) str()->uuid()));
    }

    public function test_purge_audit_failure_preserves_metadata_and_payload(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;
        $listeners = $dispatcher->getRawListeners()[$eventName] ?? [];
        $dispatcher->listen($eventName, static function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(PurgeAttachments::class));
            $action = app(PurgeAttachments::class);
            $action->execute($actor, $context, $expense, $correlationId);
        } finally {
            $dispatcher->forget($eventName);
            foreach ($listeners as $listener) {
                $dispatcher->listen($eventName, $listener);
            }
            $this->assertDatabaseHas('media', ['id' => $media->getKey()]);
            $this->assertDatabaseCount('audit_events', $auditCount);
            Storage::disk('attachments')->assertExists($media->getPathRelativeToRoot());
        }
    }

    public function test_terminal_storage_failure_rolls_back_parent_metadata_and_audit(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $project = $this->supportedAttachmentParents($tenant)['project'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $project, $this->attachmentFile('pdf'), (string) str()->uuid());
        $path = $media->getPathRelativeToRoot();
        $auditCount = AuditEvent::query()->count();

        $disk = Mockery::mock(FilesystemContract::class);
        $disk->shouldReceive('exists')->once()->andReturnTrue();
        $disk->shouldReceive('deleteDirectory')->once()->andReturnFalse();
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('disk')->once()->with('attachments')->andReturn($disk);
        $this->app->instance(Factory::class, $factory);

        try {
            app(DeleteProject::class)->execute($actor, $context, $project, 1, null, (string) str()->uuid());
            $this->fail('Terminal delete reported success after the payload purge failed.');
        } catch (\DomainException $exception) {
            $this->assertSame('ATTACHMENT_STORAGE_FAILURE', $exception->getMessage());
        }

        $this->assertInstanceOf(Project::class, Project::query()->whereKey($project->getKey())->first());
        $this->assertDatabaseHas('media', ['id' => $media->getKey()]);
        $this->assertDatabaseCount('audit_events', $auditCount);
        Storage::disk('attachments')->assertExists($path);
    }

    private function expenseData(Expense $expense): SaveExpenseData
    {
        return new SaveExpenseData(
            planningYearId: (int) $expense->planning_year_id,
            costCenterId: (int) $expense->cost_center_id,
            kind: ExpenseKind::from((string) $expense->getRawOriginal('kind')),
            title: (string) $expense->title,
            notes: $expense->notes,
            projectId: $expense->project_id === null ? null : (int) $expense->project_id,
            contractId: $expense->contract_id === null ? null : (int) $expense->contract_id,
            expectedLockVersion: (int) $expense->lock_version,
        );
    }

    private function rowData(ExpenseRow $row): SaveExpenseRowData
    {
        return new SaveExpenseRowData(
            id: (int) $row->getKey(),
            position: (int) $row->position,
            vendorId: $row->vendor_id === null ? null : (int) $row->vendor_id,
            type: ExpenseType::from((string) $row->getRawOriginal('type')),
            description: (string) $row->description,
            quantity: $row->quantity,
            unitPrice: $row->unit_price,
            enteredAmount: (string) $row->entered_amount,
            amountIncludesVat: (bool) $row->amount_includes_vat,
            vatRate: (string) $row->vat_rate,
            isExtra: (bool) $row->is_extra,
            fundedPlafondExpenseId: $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id,
            spendDate: $row->getRawOriginal('spend_date'),
            periodStart: $row->getRawOriginal('period_start'),
            periodEnd: $row->getRawOriginal('period_end'),
            distribution: $row->distribution,
            externalReference: $row->external_reference,
            expectedLockVersion: (int) $row->lock_version,
        );
    }
}
