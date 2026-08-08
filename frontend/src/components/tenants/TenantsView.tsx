import { useEffect, useState } from "react";
import { ApiError, type Tenant } from "../../api/client";
import { createTenant, deactivateTenant, listTenants, reactivateTenant, updateTenant, type TenantInput } from "../../api/tenants";
import ComponentCard from "../common/ComponentCard";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

const empty: TenantInput = { name: "", code: "", currency_code: "EUR", language_code: "en", timezone: "UTC", default_vat_rate: "22" };

export default function TenantsView({ canView, canCreate, canUpdate, canDeactivate, canReactivate }: { canView: boolean; canCreate: boolean; canUpdate: boolean; canDeactivate: boolean; canReactivate: boolean }) {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [selected, setSelected] = useState<Tenant | null>(null);
  const [form, setForm] = useState<TenantInput>(empty);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    if (!canView) return;
    try { setTenants((await listTenants({ per_page: 100 })).data); } catch (e) { setError(ApiError.from(e).message); }
  };
  useEffect(() => { void load(); }, [canView]);
  const begin = (tenant: Tenant | null) => {
    setSelected(tenant);
    setForm(tenant ? { name: tenant.name, code: tenant.code, currency_code: tenant.currency_code, language_code: tenant.language_code, timezone: tenant.timezone, default_vat_rate: tenant.default_vat_rate } : empty);
  };
  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    try {
      const item = selected ? await updateTenant(selected.id, { ...form, lock_version: selected.lock_version }) : await createTenant(form);
      setTenants((items) => selected ? items.map((row) => row.id === item.id ? item : row) : [item, ...items]);
      begin(null);
    } catch (e) { setError(ApiError.from(e).message); }
  };
  const change = async (tenant: Tenant, active: boolean) => {
    try {
      const item = active ? await reactivateTenant(tenant.id, tenant.lock_version) : await deactivateTenant(tenant.id, tenant.lock_version, window.prompt("Enter the tenant code to confirm deactivation", tenant.code) ?? "");
      setTenants((items) => items.map((row) => row.id === item.id ? item : row));
    } catch (e) { setError(ApiError.from(e).message); }
  };
  const field = (key: keyof TenantInput) => (event: React.ChangeEvent<HTMLInputElement>) => setForm((current) => ({ ...current, [key]: event.target.value }));
  return <div className="space-y-6">
    {(selected ? canUpdate : canCreate) && <ComponentCard title={selected ? "Edit tenant" : "New tenant"}>
      <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
        {(["name", "code", "currency_code", "language_code", "timezone", "default_vat_rate"] as const).map((key) => <div key={key}><Label htmlFor={`tenant-${key}`}>{key.replace(/_/g, " ")}</Label><InputField id={`tenant-${key}`} value={form[key]} onChange={field(key)} /></div>)}
        <div className="flex gap-2 md:col-span-3"><Button>{selected ? "Save tenant" : "Create tenant"}</Button>{selected && <Button variant="outline" onClick={() => begin(null)}>Cancel</Button>}</div>
      </form>
    </ComponentCard>}
    {error && <p className="text-sm text-error-500">{error}</p>}
    <ComponentCard title="Tenants" desc="Platform tenant registry.">
      <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Tenant", "Locale", "Status", "Actions"].map((head) => <TableCell key={head} isHeader className="py-3 text-start text-xs font-medium text-gray-500">{head}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{tenants.map((tenant) => <TableRow key={tenant.id}><TableCell className="py-3"><p className="font-medium">{tenant.name}</p><p className="text-xs text-gray-500">{tenant.code}</p></TableCell><TableCell className="py-3 text-sm text-gray-500">{tenant.currency_code} · {tenant.timezone}</TableCell><TableCell className="py-3"><Badge color={tenant.state === "active" ? "success" : "light"}>{tenant.state}</Badge></TableCell><TableCell className="py-3"><div className="flex flex-wrap gap-2">{canUpdate && <Button size="sm" variant="outline" onClick={() => begin(tenant)}>Edit</Button>}{tenant.state === "active" && canDeactivate && <Button size="sm" variant="outline" onClick={() => void change(tenant, false)}>Deactivate</Button>}{tenant.state !== "active" && canReactivate && <Button size="sm" variant="outline" onClick={() => void change(tenant, true)}>Reactivate</Button>}</div></TableCell></TableRow>)}</TableBody></Table></div>
    </ComponentCard>
  </div>;
}
