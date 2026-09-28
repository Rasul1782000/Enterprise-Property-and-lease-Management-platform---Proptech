import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { tap } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface User { id:number; name:string; email:string; role:string; tenant_id?:number; }

type LoginResponse = { user: User; token?: string; access_token?: string };

@Injectable({ providedIn: 'root' })
export class AuthService {
  private _token = signal<string | null>(this.readToken());
  private _user = signal<User | null>(this.readUser());

  token = this._token.asReadonly();
  user = this._user.asReadonly();
  isAuthenticated = computed(() => !!this._token());

  constructor(private http: HttpClient) {}

  login(email:string, password:string, remember = false) {
    return this.http.post<LoginResponse>(`${environment.apiUrl}/auth/login`, {email,password}).pipe(
      tap(res => {
        const token = res.access_token || res.token;
        if (!token) throw new Error('Authentication response did not include a token.');
        this.clearStorage();
        const storage = remember ? localStorage : sessionStorage;
        storage.setItem('token', token);
        storage.setItem('user', JSON.stringify(res.user));
        this._token.set(token);
        this._user.set(res.user);
      })
    );
  }

  register(payload:any) {
    return this.http.post(`${environment.apiUrl}/auth/register`, payload);
  }

  me() {
    return this.http.get<User>(`${environment.apiUrl}/auth/me`).pipe(tap(u => {
      localStorage.setItem('user', JSON.stringify(u));
      this._user.set(u);
    }));
  }

  logout() {
    return this.http.post(`${environment.apiUrl}/auth/logout`, {}).pipe(tap(()=> this.clear()));
  }

  clear() {
    this.clearStorage();
    this._token.set(null); this._user.set(null);
  }

  private readToken(): string | null {
    return localStorage.getItem('token') || sessionStorage.getItem('token');
  }

  private clearStorage(): void {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    sessionStorage.removeItem('token');
    sessionStorage.removeItem('user');
  }

  private readUser(): User | null {
    const raw = localStorage.getItem('user') || sessionStorage.getItem('user');
    if (!raw) return null;
    try {
      return JSON.parse(raw) as User;
    } catch {
      this.clearStorage();
      return null;
    }
  }
}
