export interface RevisionActor {
  kind: "human" | "system";
  label: string;
}

export interface OperationalRevision {
  id: number;
  operation: string;
  actor: RevisionActor;
  timestamp: string | null;
  summary: string;
  changed_count: number;
  changed_fields: string[];
  can_compare: boolean;
  can_restore: boolean;
}

export interface RevisionDiff {
  scope: string;
  subject: string;
  field: string;
  label: string;
  revision_value: string | number | boolean | null;
  current_value: string | number | boolean | null;
}

export interface RevisionComparison {
  revision: OperationalRevision;
  changes: RevisionDiff[];
}
