import type { PaginatedData, SharedPageProps } from './index';
import type { RevisionHistoryItem } from './revisions';

export interface VendorRecord {
    id: number;
    name: string;
    vatNumber: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    active: boolean;
    lockVersion: number;
}

export interface VendorAbilities {
    create: boolean;
    update: boolean;
    delete: boolean;
    deactivate: boolean;
    reactivate: boolean;
    viewRevisions: boolean;
    restoreRevision: boolean;
}

export interface VendorIndexPageProps extends SharedPageProps {
    vendors: PaginatedData<VendorRecord>;
    filters: {
        q: string;
        status: 'all' | 'active' | 'inactive';
    };
    abilities: VendorAbilities;
}

export interface VendorCreatePageProps extends SharedPageProps {
    abilities: VendorAbilities;
}

export interface VendorEditPageProps extends VendorCreatePageProps {
    vendor: VendorRecord;
}

export interface VendorHistoryPageProps extends SharedPageProps {
    vendor: VendorRecord;
    history: RevisionHistoryItem[];
    canRestore: boolean;
}

export interface VendorFormData {
    name: string;
    vat_number: string;
    email: string;
    phone: string;
    address: string;
    lock_version?: number;
}
