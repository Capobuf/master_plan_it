<?php

namespace App\Filament\Components;

use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Http\Request;

/**
 * Request-derived display data for the global shell's tenant indicator.
 *
 * T001-011 owns rendering and placement in the sidebar and breadcrumbs.
 */
final readonly class TenantContextIndicator
{
    public function __construct(private ?TenantContext $context) {}

    public static function fromRequest(Request $request): self
    {
        $context = $request->attributes->get(TenantContext::class);

        return new self($context instanceof TenantContext ? $context : null);
    }

    public function isSelected(): bool
    {
        return $this->context instanceof TenantContext;
    }

    public function tenantId(): ?int
    {
        return $this->context?->tenantId;
    }

    public function tenantName(): ?string
    {
        return $this->context?->tenant->name;
    }

    public function tenantCode(): ?string
    {
        return $this->context?->tenant->code;
    }

    public function tenantState(): ?string
    {
        $state = $this->context?->tenant->getRawOriginal('state');

        return is_string($state) ? $state : null;
    }

    public function label(): string
    {
        if (! $this->isSelected()) {
            return 'No tenant selected';
        }

        return sprintf('%s (%s)', $this->tenantName(), $this->tenantCode());
    }

    /**
     * @return array{selected: bool, tenant_id: ?int, name: ?string, code: ?string, state: ?string, label: string}
     */
    public function toArray(): array
    {
        return [
            'selected' => $this->isSelected(),
            'tenant_id' => $this->tenantId(),
            'name' => $this->tenantName(),
            'code' => $this->tenantCode(),
            'state' => $this->tenantState(),
            'label' => $this->label(),
        ];
    }
}
