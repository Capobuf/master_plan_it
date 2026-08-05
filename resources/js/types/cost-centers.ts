import type { SelectOption, SharedPageProps } from './index';
import type { RevisionHistoryItem } from './revisions';

export interface CostCenterRecord {
    id: number;
    name: string;
    parentId: number | null;
    active: boolean;
    lockVersion: number;
}

export interface CostCenterTreeNode extends CostCenterRecord {
    depth: number;
    children: CostCenterTreeNode[];
}

export interface CostCenterParentOption extends SelectOption<number> {
    active: boolean;
}

export interface CostCenterAbilities {
    create: boolean;
    update: boolean;
    delete: boolean;
    deactivate: boolean;
    reactivate: boolean;
    viewRevisions: boolean;
    restoreRevision: boolean;
}

export interface CostCenterIndexPageProps extends SharedPageProps {
    costCenters: CostCenterTreeNode[];
    abilities: CostCenterAbilities;
}

export interface CostCenterCreatePageProps extends SharedPageProps {
    parents: CostCenterParentOption[];
    abilities: CostCenterAbilities;
}

export interface CostCenterEditPageProps extends CostCenterCreatePageProps {
    costCenter: CostCenterRecord;
}

export interface CostCenterHistoryPageProps extends SharedPageProps {
    costCenter: CostCenterRecord;
    history: RevisionHistoryItem[];
    canRestore: boolean;
}

export interface CostCenterFormData {
    name: string;
    parent_id: number | null;
    lock_version?: number;
}
