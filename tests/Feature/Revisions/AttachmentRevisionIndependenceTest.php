<?php

namespace Tests\Feature\Revisions;

use App\Domain\Attachments\Actions\DeleteAttachment;
use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\RestoreContractRevision;
use App\Domain\Contracts\Actions\UpdateContract;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\RestoreExpenseRevision;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\RestoreProjectRevision;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Models\CostCenter;
use App\Models\Media;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\Vendor;
use App\Models\Version;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentRevisionIndependenceTest extends TestCase
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

    public function test_expense_contract_and_project_restore_leave_four_root_attachments_unchanged(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2028]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        $expenseSourceCorrelation = (string) str()->uuid();
        $expense = app(CreateExpense::class)->execute(
            $actor,
            $context,
            new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, 'Spesa iniziale', null, null, null, null),
            [$this->expenseRowData($vendor->getKey())],
            $expenseSourceCorrelation,
        );
        $this->uploadThree($actor, $context, $expense, 'spesa');
        $expenseRow = $expense->rows()->firstOrFail();
        $expense = app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, 'Spesa corrente', null, null, null, 1),
            [$this->expenseRowData($vendor->getKey(), (int) $expenseRow->getKey(), (int) $expenseRow->lock_version)],
            (string) str()->uuid(),
        );
        $this->uploadFourth($actor, $context, $expense, 'spesa');
        $expenseSet = $this->mediaSet($expense);
        $expenseBatches = RevisionBatch::query()->count();
        $expense = app(RestoreExpenseRevision::class)->execute(
            $actor,
            $context,
            $expense,
            RevisionBatch::query()->where('correlation_id', $expenseSourceCorrelation)->firstOrFail(),
            (int) $expense->lock_version,
            (string) str()->uuid(),
        );
        $this->assertSame('Spesa iniziale', $expense->title);
        $this->assertSame($expenseSet, $this->mediaSet($expense));
        $this->assertSame($expenseBatches + 1, RevisionBatch::query()->count());

        $contractSourceCorrelation = (string) str()->uuid();
        $contract = app(CreateContract::class)->execute($actor, $context, $this->contractData($vendor->getKey(), $center->getKey(), 'Contratto iniziale'), $contractSourceCorrelation);
        $this->uploadThree($actor, $context, $contract, 'contratto');
        $term = $contract->terms()->firstOrFail();
        $contract = app(UpdateContract::class)->execute(
            $actor,
            $context,
            $contract,
            $this->contractData($vendor->getKey(), $center->getKey(), 'Contratto corrente', (int) $contract->lock_version, (int) $term->getKey(), (int) $term->lock_version),
            (string) str()->uuid(),
        );
        $this->uploadFourth($actor, $context, $contract, 'contratto');
        $contractSet = $this->mediaSet($contract);
        $contractBatches = RevisionBatch::query()->count();
        $contract = app(RestoreContractRevision::class)->execute(
            $actor,
            $context,
            $contract,
            RevisionBatch::query()->where('correlation_id', $contractSourceCorrelation)->firstOrFail(),
            (int) $contract->lock_version,
            (string) str()->uuid(),
        );
        $this->assertSame('Contratto iniziale', $contract->title);
        $this->assertSame($contractSet, $this->mediaSet($contract));
        $this->assertSame($contractBatches + 1, RevisionBatch::query()->count());

        $projectSourceCorrelation = (string) str()->uuid();
        $project = app(CreateProject::class)->execute($actor, $context, new SaveProjectData('Progetto iniziale', (int) $center->getKey(), ProjectStage::Idea, null, null), $projectSourceCorrelation);
        $this->uploadThree($actor, $context, $project, 'progetto');
        $project = app(UpdateProject::class)->execute($actor, $context, $project, new SaveProjectData('Progetto corrente', (int) $center->getKey(), ProjectStage::Approved, null, 1), (string) str()->uuid());
        $this->uploadFourth($actor, $context, $project, 'progetto');
        $projectSet = $this->mediaSet($project);
        $projectBatches = RevisionBatch::query()->count();
        $project = app(RestoreProjectRevision::class)->execute(
            $actor,
            $context,
            $project,
            RevisionBatch::query()->where('correlation_id', $projectSourceCorrelation)->firstOrFail(),
            (int) $project->lock_version,
            (string) str()->uuid(),
        );
        $this->assertSame('Progetto iniziale', $project->title);
        $this->assertSame($projectSet, $this->mediaSet($project));
        $this->assertSame($projectBatches + 1, RevisionBatch::query()->count());
    }

    public function test_upload_and_delete_do_not_create_versions_or_revision_batches(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $versionCount = Version::withTrashed()->count();
        $batchCount = RevisionBatch::query()->count();

        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
        app(DeleteAttachment::class)->execute($actor, $context, $expense, (int) $media->getKey(), (string) str()->uuid());

        $this->assertSame($versionCount, Version::withTrashed()->count());
        $this->assertSame($batchCount, RevisionBatch::query()->count());
    }

    private function uploadThree($actor, $context, $parent, string $prefix): void
    {
        foreach ([1, 2, 3] as $number) {
            app(UploadAttachment::class)->execute($actor, $context, $parent, $this->attachmentFile('pdf', $prefix.'-'.$number.'.pdf'), (string) str()->uuid());
        }
    }

    private function uploadFourth($actor, $context, $parent, string $prefix): void
    {
        app(UploadAttachment::class)->execute($actor, $context, $parent, $this->attachmentFile('pdf', $prefix.'-4.pdf'), (string) str()->uuid());
    }

    /** @return list<string> */
    private function mediaSet($parent): array
    {
        $items = Media::query()
            ->where('model_type', $parent->getMorphClass())
            ->where('model_id', $parent->getKey())
            ->orderBy('id')
            ->get();
        $this->assertCount(4, $items);
        foreach ($items as $media) {
            Storage::disk('attachments')->assertExists($media->getPathRelativeToRoot());
        }

        return $items->map(fn (Media $media): string => $media->getKey().':'.$media->file_name.':'.$media->size)->all();
    }

    private function expenseRowData(int $vendorId, ?int $id = null, ?int $lockVersion = null): SaveExpenseRowData
    {
        return new SaveExpenseRowData($id, 1, $vendorId, ExpenseType::Estimate, 'Riga invariata', null, null, '100.00', false, '22.00', false, null, null, null, null, null, null, $lockVersion, true);
    }

    private function contractData(int $vendorId, int $centerId, string $title, ?int $lockVersion = null, ?int $termId = null, ?int $termLockVersion = null): SaveContractData
    {
        return new SaveContractData(
            $vendorId,
            $centerId,
            $title,
            null,
            true,
            null,
            null,
            null,
            $lockVersion,
            [new SaveContractTermData($termId, 'allegati-term', '2028-01-01', '2028-12-31', BillingCycle::Monthly, null, null, '100.00', false, '22.00', false, $termLockVersion)],
        );
    }
}
