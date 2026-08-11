<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attachments\Actions\DeleteAttachment;
use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Domain\Attachments\Services\AttachmentAuthorization;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Api\V1\Concerns\ManagesAttachmentEndpoints;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExpenseAttachmentController extends Controller
{
    use ManagesAttachmentEndpoints;

    public function index(Request $request, int $expense, AttachmentQuery $query, AttachmentAuthorization $authorization): AnonymousResourceCollection
    {
        return $this->attachmentIndex($request, $this->expense($this->tenantContext($request), $expense), $query, $authorization);
    }

    public function store(Request $request, int $expense, UploadAttachment $action, AttachmentQuery $query, AttachmentAuthorization $authorization): JsonResponse
    {
        return $this->attachmentStore($request, $this->expense($this->tenantContext($request), $expense), $action, $query, $authorization);
    }

    public function download(Request $request, int $expense, int $attachment, AttachmentQuery $query, AttachmentAuthorization $authorization): StreamedResponse
    {
        return $this->attachmentDownload($request, $this->expense($this->tenantContext($request), $expense), $attachment, $query, $authorization);
    }

    public function destroy(Request $request, int $expense, int $attachment, DeleteAttachment $action): Response
    {
        return $this->attachmentDestroy($request, $this->expense($this->tenantContext($request), $expense), $attachment, $action);
    }

    public function rowIndex(Request $request, int $expense, int $row, AttachmentQuery $query, AttachmentAuthorization $authorization): AnonymousResourceCollection
    {
        return $this->attachmentIndex($request, $this->row($this->tenantContext($request), $expense, $row), $query, $authorization);
    }

    public function rowStore(Request $request, int $expense, int $row, UploadAttachment $action, AttachmentQuery $query, AttachmentAuthorization $authorization): JsonResponse
    {
        return $this->attachmentStore($request, $this->row($this->tenantContext($request), $expense, $row), $action, $query, $authorization);
    }

    public function rowDownload(Request $request, int $expense, int $row, int $attachment, AttachmentQuery $query, AttachmentAuthorization $authorization): StreamedResponse
    {
        return $this->attachmentDownload($request, $this->row($this->tenantContext($request), $expense, $row), $attachment, $query, $authorization);
    }

    public function rowDestroy(Request $request, int $expense, int $row, int $attachment, DeleteAttachment $action): Response
    {
        return $this->attachmentDestroy($request, $this->row($this->tenantContext($request), $expense, $row), $attachment, $action);
    }

    private function expense(TenantContext $context, int $id): Expense
    {
        /** @var Expense $expense */
        $expense = TenantOwnedRecordQuery::findOrFail($context, Expense::class, $id);

        return $expense;
    }

    private function row(TenantContext $context, int $expenseId, int $rowId): ExpenseRow
    {
        return TenantOwnedRecordQuery::forTenant($context, ExpenseRow::class)
            ->where('expense_id', $expenseId)
            ->whereKey($rowId)
            ->firstOrFail();
    }
}
