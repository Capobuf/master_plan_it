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
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProjectAttachmentController extends Controller
{
    use ManagesAttachmentEndpoints;

    public function index(Request $request, int $project, AttachmentQuery $query, AttachmentAuthorization $authorization): AnonymousResourceCollection
    {
        return $this->attachmentIndex($request, $this->project($this->tenantContext($request), $project), $query, $authorization);
    }

    public function store(Request $request, int $project, UploadAttachment $action, AttachmentQuery $query, AttachmentAuthorization $authorization): JsonResponse
    {
        return $this->attachmentStore($request, $this->project($this->tenantContext($request), $project), $action, $query, $authorization);
    }

    public function download(Request $request, int $project, int $attachment, AttachmentQuery $query, AttachmentAuthorization $authorization): StreamedResponse
    {
        return $this->attachmentDownload($request, $this->project($this->tenantContext($request), $project), $attachment, $query, $authorization);
    }

    public function destroy(Request $request, int $project, int $attachment, DeleteAttachment $action): Response
    {
        return $this->attachmentDestroy($request, $this->project($this->tenantContext($request), $project), $attachment, $action);
    }

    private function project(TenantContext $context, int $id): Project
    {
        /** @var Project $project */
        $project = TenantOwnedRecordQuery::findOrFail($context, Project::class, $id);

        return $project;
    }
}
