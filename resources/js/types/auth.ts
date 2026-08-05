import type { SharedPageProps } from './index';

export type LoginPageProps = SharedPageProps;

export interface LoginFormData {
    email: string;
    password: string;
}

export type ProfilePageProps = SharedPageProps;

export interface ChangePasswordFormData {
    current_password: string;
    password: string;
    password_confirmation: string;
}
