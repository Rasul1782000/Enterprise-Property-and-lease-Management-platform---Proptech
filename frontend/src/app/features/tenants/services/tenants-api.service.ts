import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { PaginatedResponse, ApiParams } from '../../../core/types';
import { Observable } from 'rxjs';

export interface Tenant {
  id: number;
  code: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  company: string;
  tax_id: string;
  status: 'active' | 'inactive' | 'prospect' | 'former';
  emergency_contact_name: string;
  emergency_contact_phone: string;
  notes: string;
  leases_count: number;
  created_at: string;
  updated_at: string;
}

export interface CreateTenantDto {
  code: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  company?: string;
  tax_id?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  notes?: string;
}

export type UpdateTenantDto = Partial<CreateTenantDto>;

@Injectable({ providedIn: 'root' })
export class TenantsApiService {
  private readonly endpoint = 'tenants';

  constructor(private api: ApiService) {}

  list(params?: ApiParams): Observable<PaginatedResponse<Tenant>> {
    return this.api.getPaginated<Tenant>(this.endpoint, params);
  }

  get(id: number): Observable<Tenant> {
    return this.api.get<Tenant>(`${this.endpoint}/${id}`, { include: 'leases,units' });
  }

  search(query: string, params?: ApiParams): Observable<PaginatedResponse<Tenant>> {
    return this.api.getPaginated<Tenant>(this.endpoint, { ...params, search: query });
  }

  create(dto: CreateTenantDto): Observable<Tenant> {
    return this.api.post<Tenant>(this.endpoint, dto);
  }

  update(id: number, dto: UpdateTenantDto): Observable<Tenant> {
    return this.api.put<Tenant>(`${this.endpoint}/${id}`, dto);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${id}`);
  }

  getLeases(tenantId: number, params?: ApiParams): Observable<PaginatedResponse<any>> {
    return this.api.getPaginated<any>(`${this.endpoint}/${tenantId}/leases`, params);
  }

  getDocuments(tenantId: number, params?: ApiParams): Observable<PaginatedResponse<any>> {
    return this.api.getPaginated<any>(`${this.endpoint}/${tenantId}/documents`, params);
  }
}