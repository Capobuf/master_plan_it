export interface RevisionHistoryItem {
    operation: 'create' | 'update' | 'deactivate' | 'reactivate' | 'restore' | 'delete';
    actor: string | null;
    timestamp: string | null;
    reason: string | null;
    sourceRevisionId: number | null;
    restoredFromRevisionId: number | null;
}
