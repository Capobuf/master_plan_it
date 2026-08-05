import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { Badge, ConfirmModal, EmptyState, PageHeader } from '../../../components/ui';

type Center = { id: number; name: string; depth: number; active: boolean; lockVersion: number; children: Center[] };
type Action = 'deactivate' | 'delete' | null;

export default function CostCenters({ costCenters = [], abilities }: { costCenters?: Center[]; abilities: Record<string, boolean> }) {
    const [target, setTarget] = useState<Center | null>(null);
    const [action, setAction] = useState<Action>(null);
    const execute = () => {
        if (!target || !action) return;
        if (action === 'delete') {
            router.delete(`/operational/cost-centers/${target.id}`, { data: { lock_version: target.lockVersion }, onSuccess: () => setTarget(null) });
            return;
        }
        router.post(`/operational/cost-centers/${target.id}/deactivate`, { lock_version: target.lockVersion }, { onSuccess: () => setTarget(null) });
    };
    const rows = (nodes: Center[]): React.ReactNode[] => nodes.flatMap((center) => [
        <tr key={`center-${center.id}`}><td className="font-semibold text-slate-900"><span style={{ paddingLeft: `${center.depth * 20}px` }}>{center.depth ? '└ ' : ''}{center.name}</span></td><td><Badge tone={center.active ? 'green' : 'slate'}>{center.active ? 'Active' : 'Inactive'}</Badge></td><td><div className="flex gap-3">{abilities.update && <Link href={`/operational/cost-centers/${center.id}/edit`} className="font-semibold text-brand-700">Edit</Link>}{abilities.viewRevisions && <Link href={`/operational/cost-centers/${center.id}/history`} className="font-semibold text-brand-700">History</Link>}{center.active && abilities.deactivate && <button onClick={() => { setTarget(center); setAction('deactivate'); }} className="font-semibold text-red-700">Deactivate</button>}{!center.active && abilities.reactivate && <button onClick={() => router.post(`/operational/cost-centers/${center.id}/reactivate`, { lock_version: center.lockVersion })} className="font-semibold text-emerald-700">Reactivate</button>}{abilities.delete && <button onClick={() => { setTarget(center); setAction('delete'); }} className="font-semibold text-red-700">Delete</button>}</div></td></tr>,
        ...rows(center.children ?? []),
    ]);
    return <AppLayout><Head title="Cost centers"/><PageHeader title="Cost centers" description="Organize the tenant cost center hierarchy." crumbs={[{ label: 'Cost centers' }]} action={abilities.create && <Link className="mp-button mp-button-primary" href="/operational/cost-centers/create">Add cost center</Link>}/>{costCenters.length ? <div className="mp-card operational-table-scroll overflow-x-auto"><table className="mp-table"><thead><tr><th>Name</th><th>Status</th><th/></tr></thead><tbody>{rows(costCenters)}</tbody></table></div> : <EmptyState title="No cost centers found">Create a cost center to start building the hierarchy.</EmptyState>}<ConfirmModal open={!!target} title={action === 'delete' ? 'Delete cost center' : 'Deactivate cost center'} confirmLabel={action === 'delete' ? 'Delete' : 'Deactivate'} onClose={() => { setTarget(null); setAction(null); }} onConfirm={execute}>Confirm {action} for {target?.name}.</ConfirmModal></AppLayout>;
}
