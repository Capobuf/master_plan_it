import { useEffect, useState } from "react";
import { ApiError } from "../../api/client";
import { createVendor, deactivateVendor, listVendorHistory, listVendors, reactivateVendor, restoreVendor, updateVendor, type Revision, type Vendor } from "../../api/vendors";
import ComponentCard from "../common/ComponentCard";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

function VendorForm({ value, editing, onCancel, onSaved, canCreate, canUpdate }: { value: Vendor | null; editing: boolean; onCancel: () => void; onSaved: (vendor: Vendor) => void; canCreate: boolean; canUpdate: boolean }) {
  const [form, setForm] = useState({ name: value?.name ?? "", vat_number: value?.vat_number ?? "", email: value?.email ?? "", phone: value?.phone ?? "", address: value?.address ?? "" });
  const [error, setError] = useState<string | null>(null);
  const allowed = editing ? canUpdate : canCreate;
  const field = (key: keyof typeof form) => (event: React.ChangeEvent<HTMLInputElement>) => setForm((current) => ({ ...current, [key]: event.target.value }));
  const submit = async (event: React.FormEvent) => { event.preventDefault(); if (!allowed) return; try { const vendor = editing && value ? await updateVendor(value.id, { ...form, lock_version: value.lock_version }) : await createVendor(form); onSaved(vendor); } catch (requestError) { setError(ApiError.from(requestError).message); } };
  return <ComponentCard title={editing ? "Edit vendor" : "New vendor"}>
    {error && <p className="text-sm text-error-500">{error}</p>}
    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
      <div><Label htmlFor="vendor-name">Name</Label><InputField id="vendor-name" value={form.name} onChange={field("name")} /></div>
      <div><Label htmlFor="vendor-vat">VAT number</Label><InputField id="vendor-vat" value={form.vat_number} onChange={field("vat_number")} /></div>
      <div><Label htmlFor="vendor-email">Email</Label><InputField id="vendor-email" type="email" value={form.email} onChange={field("email")} /></div>
      <div><Label htmlFor="vendor-phone">Phone</Label><InputField id="vendor-phone" value={form.phone} onChange={field("phone")} /></div>
      <div className="md:col-span-2"><Label htmlFor="vendor-address">Address</Label><InputField id="vendor-address" value={form.address} onChange={field("address")} /></div>
      <div className="flex gap-3 md:col-span-2"><Button disabled={!allowed}>{editing ? "Save vendor" : "Create vendor"}</Button>{editing && <Button variant="outline" onClick={onCancel}>Cancel</Button>}</div>
    </form>
  </ComponentCard>;
}

export default function VendorsView({ canView, canCreate, canUpdate, canDeactivate, canReactivate, canHistory, canRestore }: { canView: boolean; canCreate: boolean; canUpdate: boolean; canDeactivate: boolean; canReactivate: boolean; canHistory: boolean; canRestore: boolean }) {
  const [vendors, setVendors] = useState<Vendor[]>([]); const [selected, setSelected] = useState<Vendor | null>(null); const [history, setHistory] = useState<{ vendor: number; rows: Revision[] } | null>(null); const [error, setError] = useState<string | null>(null); const [loading, setLoading] = useState(false);
  const load = async () => { if (!canView) return; setLoading(true); try { setVendors((await listVendors({ per_page: 100 })).data); setError(null); } catch (e) { setError(ApiError.from(e).message); } finally { setLoading(false); } };
  useEffect(() => { void load(); }, [canView]);
  const save = (vendor: Vendor) => { setVendors((rows) => { const exists = rows.some((row) => row.id === vendor.id); return exists ? rows.map((row) => row.id === vendor.id ? vendor : row) : [vendor, ...rows]; }); setSelected(null); };
  const stateChange = async (vendor: Vendor, active: boolean) => { if (!(active ? canReactivate : canDeactivate)) return; try { const updated = active ? await reactivateVendor(vendor.id, vendor.lock_version) : await deactivateVendor(vendor.id, vendor.lock_version); save(updated); } catch (e) { setError(ApiError.from(e).message); } };
  const showHistory = async (vendor: Vendor) => { if (!canHistory) return; try { const result = await listVendorHistory(vendor.id); setHistory({ vendor: vendor.id, rows: result.data }); } catch (e) { setError(ApiError.from(e).message); } };
  const restore = async (vendor: Vendor, revision: Revision) => { if (!canRestore || revision.source_revision_id === null) return; try { save(await restoreVendor(vendor.id, revision.source_revision_id, vendor.lock_version)); setHistory(null); } catch (e) { setError(ApiError.from(e).message); } };
  return <div className="space-y-6">{canCreate && <VendorForm value={null} editing={false} onCancel={() => undefined} onSaved={save} canCreate={canCreate} canUpdate={canUpdate} />}{selected && <VendorForm value={selected} editing onCancel={() => setSelected(null)} onSaved={save} canCreate={canCreate} canUpdate={canUpdate} />}<ComponentCard title="Vendors" desc="Tenant-scoped vendor register.">{error && <p className="mb-3 text-sm text-error-500">{error}</p>}{loading ? <p className="text-sm text-gray-500">Loading vendors…</p> : vendors.length === 0 ? <p className="text-sm text-gray-500">No vendors found.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Name", "Contact", "Status", "Actions"].map((head) => <TableCell key={head} isHeader className="py-3 text-start text-xs font-medium text-gray-500">{head}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{vendors.map((vendor) => <TableRow key={vendor.id}><TableCell className="py-3 font-medium">{vendor.name}</TableCell><TableCell className="py-3 text-sm text-gray-500">{vendor.email ?? vendor.phone ?? "—"}</TableCell><TableCell className="py-3"><Badge color={vendor.active ? "success" : "light"}>{vendor.active ? "Active" : "Inactive"}</Badge></TableCell><TableCell className="py-3"><div className="flex flex-wrap gap-2">{canUpdate && <Button size="sm" variant="outline" onClick={() => setSelected(vendor)}>Edit</Button>}{vendor.active && canDeactivate && <Button size="sm" variant="outline" onClick={() => void stateChange(vendor, false)}>Deactivate</Button>}{!vendor.active && canReactivate && <Button size="sm" variant="outline" onClick={() => void stateChange(vendor, true)}>Reactivate</Button>}{canHistory && <Button size="sm" variant="outline" onClick={() => void showHistory(vendor)}>History</Button>}</div></TableCell></TableRow>)}</TableBody></Table></div>}{history && <div className="mt-5 border-t pt-5"><h4 className="font-medium">Revision history</h4>{history.rows.length === 0 ? <p className="mt-2 text-sm text-gray-500">No revisions found.</p> : history.rows.map((revision, index) => <div key={`${revision.source_revision_id ?? "revision"}-${index}`} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><span>{revision.operation} · {revision.timestamp ?? "—"} · {revision.actor ?? "—"}</span>{canRestore && <Button size="sm" variant="outline" onClick={() => { const vendor = vendors.find((item) => item.id === history.vendor); if (vendor) void restore(vendor, revision); }}>Restore</Button>}</div>)}</div>}</ComponentCard></div>;
}
