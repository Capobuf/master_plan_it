<?php

namespace App\Domain\Attachments\Services;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Project;
use App\Models\User;
use App\Policies\ContractPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ProjectPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\PermissionRegistrar;

final class AttachmentAuthorization
{
    public function authorizeView(User $actor, TenantContext $context, Model $parent): void
    {
        $this->response($actor, $context, $parent, 'viewAttachment')->authorize();
    }

    public function authorizeUpload(User $actor, TenantContext $context, Model $parent): void
    {
        $this->response($actor, $context, $parent, 'uploadAttachment')->authorize();
    }

    public function authorizeDelete(User $actor, TenantContext $context, Model $parent): void
    {
        $this->response($actor, $context, $parent, 'deleteAttachment')->authorize();
    }

    /** @return array{upload: bool, download: bool, delete: bool} */
    public function abilities(User $actor, TenantContext $context, Model $parent): array
    {
        return [
            'upload' => $this->response($actor, $context, $parent, 'uploadAttachment')->allowed(),
            'download' => $this->response($actor, $context, $parent, 'viewAttachment')->allowed(),
            'delete' => $this->response($actor, $context, $parent, 'deleteAttachment')->allowed(),
        ];
    }

    private function response(User $actor, TenantContext $context, Model $parent, string $method): Response
    {
        $authorizationRoot = $this->authorizationRoot($context, $parent);
        $registrar = app(PermissionRegistrar::class);
        $administrator = app(PlatformAdministrator::class);

        if ($authorizationRoot instanceof Expense) {
            return (new ExpensePolicy($context, $registrar, $administrator))->{$method}($actor, $authorizationRoot);
        }
        if ($authorizationRoot instanceof Contract) {
            return (new ContractPolicy($context, $registrar, $administrator))->{$method}($actor, $authorizationRoot);
        }

        return (new ProjectPolicy($context, $registrar, $administrator))->{$method}($actor, $authorizationRoot);
    }

    private function authorizationRoot(TenantContext $context, Model $parent): Expense|Contract|Project
    {
        if ($parent instanceof ExpenseRow) {
            $currentRow = TenantOwnedRecordQuery::forTenant($context, ExpenseRow::class)
                ->where('expense_id', $parent->expense_id)
                ->whereKey($parent->getRawOriginal($parent->getKeyName()))
                ->first();
            if (! $currentRow instanceof ExpenseRow) {
                throw (new ModelNotFoundException)->setModel(ExpenseRow::class);
            }

            $parent = TenantOwnedRecordQuery::forTenant($context, Expense::class)
                ->whereKey($currentRow->expense_id)
                ->first();
        } elseif ($parent instanceof Expense || $parent instanceof Contract || $parent instanceof Project) {
            $parent = TenantOwnedRecordQuery::forTenant($context, $parent::class)
                ->whereKey($parent->getRawOriginal($parent->getKeyName()))
                ->first();
        } else {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        if (! $parent instanceof Expense && ! $parent instanceof Contract && ! $parent instanceof Project) {
            throw (new ModelNotFoundException)->setModel(Model::class);
        }

        return $parent;
    }
}
