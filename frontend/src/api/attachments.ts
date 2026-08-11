import axios from "axios";
import { ApiError, apiClient, type DataEnvelope } from "./client";

export type AttachmentParent =
  | { kind: "expense"; expenseId: number }
  | { kind: "expense-row"; expenseId: number; rowId: number }
  | { kind: "contract"; contractId: number }
  | { kind: "project"; projectId: number };

export interface Attachment {
  id: number;
  name: string;
  mime_type: string;
  extension: string;
  size: number;
  uploaded_at: string | null;
  uploaded_by: { name: string };
}

export interface AttachmentMeta {
  used_bytes: string;
  quota_bytes: string;
}

export interface AttachmentAbilities {
  upload: boolean;
  download: boolean;
  delete: boolean;
}

export interface AttachmentListResponse extends DataEnvelope<Attachment[]> {
  meta: AttachmentMeta;
  abilities: AttachmentAbilities;
}

export interface AttachmentUploadResponse extends DataEnvelope<Attachment> {
  meta: AttachmentMeta;
  abilities: AttachmentAbilities;
}

function attachmentPath(parent: AttachmentParent): string {
  if (parent.kind === "expense") return `/api/v1/expenses/${parent.expenseId}/attachments`;
  if (parent.kind === "expense-row") return `/api/v1/expenses/${parent.expenseId}/rows/${parent.rowId}/attachments`;
  if (parent.kind === "contract") return `/api/v1/contracts/${parent.contractId}/attachments`;
  return `/api/v1/projects/${parent.projectId}/attachments`;
}

export async function listAttachments(parent: AttachmentParent): Promise<AttachmentListResponse> {
  return (await apiClient.get<AttachmentListResponse>(attachmentPath(parent))).data;
}

export async function uploadAttachment(parent: AttachmentParent, file: File, onProgress?: (percentage: number) => void): Promise<AttachmentUploadResponse> {
  const form = new FormData();
  form.append("file", file);
  return (await apiClient.post<AttachmentUploadResponse>(attachmentPath(parent), form, {
    onUploadProgress: (event) => {
      if (event.total && onProgress) onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)));
    },
  })).data;
}

export async function downloadAttachment(parent: AttachmentParent, attachmentId: number): Promise<Blob> {
  try {
    return (await apiClient.get<Blob>(`${attachmentPath(parent)}/${attachmentId}/download`, { responseType: "blob" })).data;
  } catch (error: unknown) {
    if (axios.isAxiosError(error) && error.response?.data instanceof Blob && error.response.data.type.includes("json")) {
      try {
        error.response.data = JSON.parse(await error.response.data.text());
      } catch {
        // ApiError.from will retain the transport diagnostic when the response is not valid JSON.
      }
    }
    throw ApiError.from(error);
  }
}

export async function deleteAttachment(parent: AttachmentParent, attachmentId: number): Promise<void> {
  await apiClient.delete(`${attachmentPath(parent)}/${attachmentId}`);
}
