import { useEffect, useState } from "react";
import { ApiError } from "../../api/client";
import { assignUserRoles, createUser, deactivateUser, listUsers, resetUserPassword, updateUser, type TenantUser } from "../../api/users";
import { listRoles, type Role } from "../../api/roles";
import ComponentCard from "../common/ComponentCard";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Select from "../form/Select";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

export default function UsersView({ canManage }: { canManage: boolean }) {
  const [users, setUsers] = useState<TenantUser[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [selected, setSelected] = useState<TenantUser | null>(null);
  const [form, setForm] = useState({ name: "", email: "", password: "", role: "" });
  const [search, setSearch] = useState("");
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    if (!canManage) return;
    try {
      const [userResult, roleResult] = await Promise.all([listUsers({ q: search, per_page: 100 }), listRoles()]);
      setUsers(userResult.data);
      setRoles(roleResult.data);
    } catch (e) { setError(ApiError.from(e).message); }
  };
  // The loader intentionally follows the access/search inputs rather than its function identity.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { void load(); }, [canManage, search]);
  const begin = (user: TenantUser | null) => {
    setSelected(user);
    setForm({ name: user?.name ?? "", email: user?.email ?? "", password: "", role: "" });
  };
  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!canManage || (!selected && !form.role)) return;
    try {
      const result = selected
        ? await updateUser(selected.id, { name: form.name, email: form.email })
        : await createUser({ name: form.name, email: form.email, password: form.password, roles: [Number(form.role)] });
      if (selected && form.role) await assignUserRoles(result.id, [Number(form.role)]);
      setUsers((rows) => selected ? rows.map((row) => row.id === result.id ? result : row) : [result, ...rows]);
      begin(null);
    } catch (e) { setError(ApiError.from(e).message); }
  };
  const deactivate = async (user: TenantUser) => { try { const result = await deactivateUser(user.id); setUsers((rows) => rows.map((row) => row.id === result.id ? result : row)); } catch (e) { setError(ApiError.from(e).message); } };
  const reset = async (user: TenantUser) => { const password = window.prompt("New password"); if (!password) return; try { await resetUserPassword(user.id, password, password); } catch (e) { setError(ApiError.from(e).message); } };

  return <div className="space-y-6">
    {canManage && <ComponentCard title={selected ? "Edit user" : "New user"}>
      <form onSubmit={submit} className="grid gap-4 md:grid-cols-4">
        <div><Label htmlFor="user-name">Name</Label><InputField id="user-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
        <div><Label htmlFor="user-email">Email</Label><InputField id="user-email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
        {!selected && <div><Label htmlFor="user-password">Password</Label><InputField id="user-password" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>}
        <div><Label>Role {selected ? "(optional reassignment)" : ""}</Label><Select key={`user-role-${selected?.id ?? "new"}`} options={roles.map((role) => ({ value: String(role.id), label: role.name }))} placeholder={selected ? "Keep current role assignment" : "Select a role"} defaultValue={form.role} onChange={(value) => setForm({ ...form, role: value })} /></div>
        <div className="flex gap-2 md:col-span-4"><Button type="submit" disabled={!selected && !form.role}>{selected ? "Save user" : "Create user"}</Button>{selected && <Button type="button" variant="outline" onClick={() => begin(null)}>Cancel</Button>}</div>
      </form>
    </ComponentCard>}
    {error && <p className="text-sm text-error-500">{error}</p>}
    <ComponentCard title="Users" desc="Users and optional tenant role reassignment.">
      <div className="mb-4 max-w-sm"><Label htmlFor="user-search">Search</Label><InputField id="user-search" type="search" value={search} onChange={(e) => setSearch(e.target.value)} /></div>
      <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["User", "Status", "Actions"].map((head) => <TableCell key={head} isHeader className="py-3 text-start text-xs font-medium text-gray-500">{head}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{users.map((user) => <TableRow key={user.id}><TableCell className="py-3"><p className="font-medium">{user.name}</p><p className="text-xs text-gray-500">{user.email}</p></TableCell><TableCell className="py-3"><Badge color={user.active ? "success" : "light"}>{user.active ? "Active" : "Inactive"}</Badge></TableCell><TableCell className="py-3"><div className="flex flex-wrap gap-2"><Button size="sm" variant="outline" onClick={() => begin(user)}>Edit / roles</Button>{user.active && <Button size="sm" variant="outline" onClick={() => void deactivate(user)}>Deactivate</Button>}<Button size="sm" variant="outline" onClick={() => void reset(user)}>Reset password</Button></div></TableCell></TableRow>)}</TableBody></Table></div>
    </ComponentCard>
  </div>;
}
