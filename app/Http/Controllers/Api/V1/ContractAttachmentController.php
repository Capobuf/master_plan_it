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
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ContractAttachmentController extends Controller
{
    use ManagesAttachmentEndpoints;

    public function index(Request $request, int $contract, AttachmentQuery $query, AttachmentAuthorization $authorization): AnonymousResourceCollection
    {
        return $this->attachmentIndex($request, $this->contract($this->tenantContext($request), $contract), $query, $authorization);
    }

    public function store(Request $request, int $contract, UploadAttachment $action, AttachmentQuery $query, AttachmentAuthorization $authorization): JsonResponse
    {
        return $this->attachmentStore($request, $this->contract($this->tenantContext($request), $contract), $action, $query, $authorization);
    }

    public function download(Request $request, int $contract, int $attachment, AttachmentQuery $query, AttachmentAuthorization $authorization): StreamedResponse
    {
        return $this->attachmentDownload($request, $this->contract($this->tenantContext($request), $contract), $attachment, $query, $authorization);
    }

    public function destroy(Request $request, int $contract, int $attachment, DeleteAttachment $action): Response
    {
        return $this->attachmentDestroy($request, $this->contract($this->tenantContext($request), $contract), $attachment, $action);
    }

    private function contract(TenantContext $context, int $id): Contract
    {
        /** @var Contract $contract */
        $contract = TenantOwnedRecordQuery::findOrFail($context, Contract::class, $id);

        return $contract;
    }
}
