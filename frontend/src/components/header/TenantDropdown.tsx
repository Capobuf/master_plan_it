import { useState } from "react";
import {
  enterTenant,
  leaveTenant,
  listTenants,
} from "../../api/context";
import { ApiError, type Tenant } from "../../api/client";
import { useApplicationContext } from "../../context/ApplicationContext";
import { CheckCircleIcon, ChevronDownIcon } from "../../icons";
import { domainLabel } from "../../presentation/labels";
import Alert from "../ui/alert/Alert";
import { Dropdown } from "../ui/dropdown/Dropdown";
import { DropdownItem } from "../ui/dropdown/DropdownItem";

function errorMessage(error: unknown): string {
  const apiError = ApiError.from(error);
  const isUnexpected = apiError.handledStatus === null || apiError.status === 500;

  if (isUnexpected && apiError.correlationId) {
    return `${apiError.message} Riferimento tecnico: ${apiError.correlationId}`;
  }

  return apiError.message;
}

async function getAllTenants(): Promise<Tenant[]> {
  const tenants: Tenant[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await listTenants({ page, per_page: 100 });
    tenants.push(...response.data);
    lastPage = response.meta.last_page;
    page = response.meta.current_page + 1;
  } while (page <= lastPage);

  return tenants;
}

export default function TenantDropdown() {
  const { data, loading: contextLoading, refreshContext } =
    useApplicationContext();
  const [isOpen, setIsOpen] = useState(false);
  const [tenants, setTenants] = useState<Tenant[] | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [pendingTenantId, setPendingTenantId] = useState<number | null>(null);
  const [isLeaving, setIsLeaving] = useState(false);
  const [tenantError, setTenantError] = useState<string | null>(null);

  const currentTenant = data?.tenant ?? null;
  const platformAdministrator = data?.platformAdministrator ?? false;

  if (!platformAdministrator) {
    return (
      <div className="flex h-11 items-center gap-2 rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-400">
        <CheckCircleIcon className="h-5 w-5 fill-gray-500 dark:fill-gray-400" />
        <span>
          {contextLoading
            ? "Caricamento Tenant…"
            : currentTenant
              ? `${currentTenant.name} (${domainLabel(currentTenant.state)})`
              : "Nessun Tenant"}
        </span>
      </div>
    );
  }

  const loadTenants = async () => {
    if (isLoading) {
      return;
    }

    setIsLoading(true);
    setTenantError(null);

    try {
      setTenants(await getAllTenants());
    } catch (error) {
      setTenantError(errorMessage(error));
    } finally {
      setIsLoading(false);
    }
  };

  const toggleDropdown = () => {
    const shouldOpen = !isOpen;
    setIsOpen(shouldOpen);

    if (shouldOpen && tenants === null) {
      void loadTenants();
    }
  };

  const handleEnterTenant = async (tenantId: number) => {
    if (pendingTenantId !== null || isLeaving) {
      return;
    }

    setPendingTenantId(tenantId);
    setTenantError(null);

    try {
      await enterTenant(tenantId);
      await refreshContext();
      setIsOpen(false);
    } catch (error) {
      setTenantError(errorMessage(error));
    } finally {
      setPendingTenantId(null);
    }
  };

  const handleLeaveTenant = async () => {
    if (pendingTenantId !== null || isLeaving) {
      return;
    }

    setIsLeaving(true);
    setTenantError(null);

    try {
      await leaveTenant();
      await refreshContext();
      setIsOpen(false);
    } catch (error) {
      setTenantError(errorMessage(error));
    } finally {
      setIsLeaving(false);
    }
  };

  const availableTenants =
    tenants?.filter((tenant) => tenant.id !== currentTenant?.id) ?? [];

  return (
    <div className="relative">
      <button
        onClick={toggleDropdown}
        className="dropdown-toggle flex h-11 items-center gap-2 rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-400"
        aria-label="Apri il menu Tenant"
        aria-expanded={isOpen}
        aria-controls="tenant-dropdown"
      >
        <CheckCircleIcon className="h-5 w-5 fill-gray-500 dark:fill-gray-400" />
        <span className="max-w-[120px] truncate sm:max-w-[180px]">
          {currentTenant?.name ?? "Amministrazione di Piattaforma"}
        </span>
        {currentTenant ? (
          <span className="hidden text-theme-xs text-gray-500 dark:text-gray-400 xl:inline">
            {domainLabel(currentTenant.state)}
          </span>
        ) : null}
        <ChevronDownIcon
          className={`stroke-gray-500 transition-transform duration-200 dark:stroke-gray-400 ${
            isOpen ? "rotate-180" : ""
          }`}
        />
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        triggerId="tenant-dropdown"
        className="absolute right-0 mt-[17px] flex max-h-[420px] w-[300px] flex-col overflow-y-auto rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
      >
        <div className="border-b border-gray-200 pb-3 dark:border-gray-800">
          <span className="block text-theme-xs text-gray-500 dark:text-gray-400">
            Tenant corrente
          </span>
          <span className="mt-0.5 block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
            {currentTenant?.name ?? "Amministrazione di Piattaforma"}
          </span>
        </div>

        {tenantError ? (
          <div className="mt-3">
            <Alert variant="error" title="Cambio Tenant non riuscito" message={tenantError} />
          </div>
        ) : null}

        <ul className="flex flex-col gap-1 pt-3">
          {isLoading ? (
            <li className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
              Caricamento dei Tenant…
            </li>
          ) : null}

          {!isLoading && tenants !== null && availableTenants.length === 0 ? (
            <li className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
              Nessun altro Tenant disponibile.
            </li>
          ) : null}

          {availableTenants.map((tenant) => (
            <li key={tenant.id}>
              <DropdownItem
                onClick={() => void handleEnterTenant(tenant.id)}
                baseClassName="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left font-medium text-gray-700 text-theme-sm hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
              >
                <span>{tenant.name}</span>
                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                  {pendingTenantId === tenant.id ? "Accesso…" : tenant.code}
                </span>
              </DropdownItem>
            </li>
          ))}
        </ul>

        {currentTenant ? (
          <DropdownItem
            onClick={() => void handleLeaveTenant()}
            baseClassName="mt-3 flex w-full items-center gap-3 border-t border-gray-200 px-3 pt-3 pb-2 font-medium text-gray-700 text-theme-sm hover:text-gray-900 dark:border-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
          >
            {isLeaving ? "Uscita dal Tenant…" : "Esci dal Tenant"}
          </DropdownItem>
        ) : null}
      </Dropdown>
    </div>
  );
}
