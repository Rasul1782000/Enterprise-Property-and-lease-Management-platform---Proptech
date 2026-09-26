import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { Observable, tap } from 'rxjs';

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'staff' | 'viewer';
  permissions: string[];
  avatar_url?: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

export interface LoginResponse {
  user: User;
  access_token: string;
  refresh_token: string;
  expires_in: number;
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ForgotPasswordData {
  email: string;
}

export interface ResetPasswordData {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

@Injectable({ providedIn: 'root' })
export class AuthApiService {
  private readonly endpoint = 'auth';

  constructor(private api: ApiService) {}

  login(credentials: LoginCredentials): Observable<LoginResponse> {
    return this.api.post<LoginResponse>(`${this.endpoint}/login`, credentials).pipe(
      tap(response => this.setTokens(response.access_token, response.refresh_token))
    );
  }

  register(data: RegisterData): Observable<LoginResponse> {
    return this.api.post<LoginResponse>(`${this.endpoint}/register`, data).pipe(
      tap(response => this.setTokens(response.access_token, response.refresh_token))
    );
  }

  logout(): Observable<void> {
    return this.api.post<void>(`${this.endpoint}/logout`, {});
  }

  me(): Observable<User> {
    return this.api.get<User>(`${this.endpoint}/me`);
  }

  refreshToken(): Observable<{ access_token: string; expires_in: number }> {
    const refreshToken = this.getRefreshToken();
    return this.api.post<{ access_token: string; expires_in: number }>(`${this.endpoint}/refresh`, { refresh_token: refreshToken }).pipe(
      tap(response => this.setAccessToken(response.access_token))
    );
  }

  forgotPassword(data: ForgotPasswordData): Observable<void> {
    return this.api.post<void>(`${this.endpoint}/forgot-password`, data);
  }

  resetPassword(data: ResetPasswordData): Observable<void> {
    return this.api.post<void>(`${this.endpoint}/reset-password`, data);
  }

  changePassword(current: string, newPassword: string): Observable<void> {
    return this.api.post<void>(`${this.endpoint}/change-password`, { current_password: current, password: newPassword });
  }

  updateProfile(data: Partial<User>): Observable<User> {
    return this.api.put<User>(`${this.endpoint}/profile`, data);
  }

  private setTokens(access: string, refresh: string): void {
    localStorage.setItem('access_token', access);
    localStorage.setItem('refresh_token', refresh);
  }

  private setAccessToken(access: string): void {
    localStorage.setItem('access_token', access);
  }

  getAccessToken(): string | null {
    return localStorage.getItem('access_token');
  }

  getRefreshToken(): string | null {
    return localStorage.getItem('refresh_token');
  }

  isAuthenticated(): boolean {
    return !!this.getAccessToken();
  }

  clearTokens(): void {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  }
}