import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { PaginatedResponse, ApiParams } from '../types';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  private buildParams(params?: ApiParams): HttpParams {
    let httpParams = new HttpParams();
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        if (v != null && v !== '') {
          httpParams = httpParams.set(k, String(v));
        }
      });
    }
    return httpParams;
  }

  get<T>(path: string, params?: ApiParams): Observable<T> {
    return this.http.get<T>(`${this.base}/${path}`, { params: this.buildParams(params) });
  }

  getPaginated<T>(path: string, params?: ApiParams): Observable<PaginatedResponse<T>> {
    return this.http.get<PaginatedResponse<T>>(`${this.base}/${path}`, { params: this.buildParams(params) });
  }

  post<T>(path: string, body: any): Observable<T> {
    return this.http.post<T>(`${this.base}/${path}`, body);
  }

  /**
   * Multipart POST for file uploads. `body` must be a FormData; no
   * Content-Type is set so the browser can add the multipart boundary.
   */
  postForm<T>(path: string, body: FormData): Observable<T> {
    return this.http.post<T>(`${this.base}/${path}`, body);
  }

  put<T>(path: string, body: any): Observable<T> {
    return this.http.put<T>(`${this.base}/${path}`, body);
  }

  patch<T>(path: string, body: any): Observable<T> {
    return this.http.patch<T>(`${this.base}/${path}`, body);
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(`${this.base}/${path}`);
  }

  getBlob(path: string, params?: ApiParams): Observable<Blob> {
    return this.http.get(`${this.base}/${path}`, { params: this.buildParams(params), responseType: 'blob' });
  }
}