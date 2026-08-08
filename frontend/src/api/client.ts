import axios, { AxiosError } from "axios";

export interface DataEnvelope<T> {
  data: T;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginatedData<T> extends DataEnvelope<T[]> {
  meta: PaginationMeta;
  links: PaginationLinks;
}

export interface PaginationParams {
  page?: number;
  per_page?: number;
}

export interface User {
  id: number;
  name: string;
  email: string;
  active: boolean;
  tenant_id: number | null;
}

export interface Tenant {
  id: number;
  name: string;
  code: string;
  currency_code: string;
  language_code: string;
  timezone: string;
  default_vat_rate: string;
  state: string;
  lock_version: number;
}

export type HandledApiStatus = 401 | 403 | 404 | 409 | 422 | 429 | 500;

interface ErrorPayload {
  error?: {
    code?: unknown;
    message?: unknown;
    fields?: unknown;
    correlation_id?: unknown;
  };
}

const handledStatuses = new Set<number>([401, 403, 404, 409, 422, 429, 500]);

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function stringValue(value: unknown): string | null {
  return typeof value === "string" && value.length > 0 ? value : null;
}

export class ApiError extends Error {
  readonly cause: unknown;
  readonly status: number | null;
  readonly handledStatus: HandledApiStatus | null;
  readonly code: string;
  readonly fields: Record<string, unknown>;
  readonly correlationId: string | null;

  constructor(options: {
    message: string;
    status?: number | null;
    code?: string;
    fields?: Record<string, unknown>;
    correlationId?: string | null;
    cause?: unknown;
  }) {
    super(options.message);
    this.name = "ApiError";
    this.cause = options.cause;
    this.status = options.status ?? null;
    this.handledStatus =
      this.status !== null && handledStatuses.has(this.status)
        ? (this.status as HandledApiStatus)
        : null;
    this.code = options.code ?? "REQUEST_FAILED";
    this.fields = options.fields ?? {};
    this.correlationId = options.correlationId ?? null;
  }

  static from(error: unknown): ApiError {
    if (error instanceof ApiError) {
      return error;
    }

    if (!axios.isAxiosError<ErrorPayload>(error)) {
      return new ApiError({
        message: error instanceof Error ? error.message : "Unexpected client error.",
        cause: error,
      });
    }

    const axiosError: AxiosError<ErrorPayload> = error;
    const payload = axiosError.response?.data?.error;
    const headerCorrelationId = stringValue(
      axiosError.response?.headers["x-correlation-id"],
    );

    return new ApiError({
      message:
        stringValue(payload?.message) ??
        (axiosError.response
          ? `Request failed with status ${axiosError.response.status}.`
          : "Unable to reach the application service."),
      status: axiosError.response?.status ?? null,
      code: stringValue(payload?.code) ?? axiosError.code ?? "REQUEST_FAILED",
      fields: isRecord(payload?.fields) ? payload.fields : {},
      correlationId: stringValue(payload?.correlation_id) ?? headerCorrelationId,
      cause: error,
    });
  }
}

export const apiClient = axios.create({
  baseURL: "/",
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

apiClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => Promise.reject(ApiError.from(error)),
);
