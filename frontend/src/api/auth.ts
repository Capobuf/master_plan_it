import { apiClient, type DataEnvelope, type User } from "./client";

export interface LoginCredentials {
  email: string;
  password: string;
}

export async function establishCsrfCookie(): Promise<void> {
  await apiClient.get("/sanctum/csrf-cookie");
}

export async function login(credentials: LoginCredentials): Promise<User> {
  await establishCsrfCookie();

  const response = await apiClient.post<DataEnvelope<User>>(
    "/api/v1/auth/login",
    credentials,
  );

  return response.data.data;
}

export async function getCurrentUser(): Promise<User> {
  const response = await apiClient.get<DataEnvelope<User>>("/api/v1/auth/me");

  return response.data.data;
}

export async function logout(): Promise<void> {
  await apiClient.post("/api/v1/auth/logout");
}

