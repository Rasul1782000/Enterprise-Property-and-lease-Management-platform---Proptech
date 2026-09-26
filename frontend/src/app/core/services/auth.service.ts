import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { tap } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface User { id:number; name:string; email:string; role:string; tenant_id?:number; }

@Injectable({ providedIn: 'root' })
export class AuthService {
  private _token = signal<string | null>(localStorage.getItem('token'));
  private _user = signal<User | null>(JSON.parse(localStorage.getItem('user') || 'null'));

  token = this._token.asReadonly();
  user = this._user.asReadonly();
  isAuthenticated = computed(() => !!this._token());

  constructor(private http: HttpClient) {}

  login(email:string, password:string) {
    return this.http.post<{user:User, token:string}>(`${environment.apiUrl}/auth/login`, {email,password}).pipe(
      tap(res => {
        localStorage.setItem('token', res.token);
        localStorage.setItem('user', JSON.stringify(res.user));
        this._token.set(res.token);
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
    localStorage.removeItem('token'); localStorage.removeItem('user');
    this._token.set(null); this._user.set(null);
  }
}
