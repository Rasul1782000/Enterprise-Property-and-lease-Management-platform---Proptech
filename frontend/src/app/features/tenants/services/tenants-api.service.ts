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

export interface TenantDocument {
  id: number;
  tenant_id: number;
  name: string;
  category: string;
  disk: string;
  path: string;
  mime_type: string | null;
  size_kb: number;
  uploaded_by: number | null;
  /** Presigned object-storage link, or the API proxy route as a fallback. */
  url: string;
  /** Lower-cased extension without the dot, e.g. `pdf`. */
  extension: string;
  created_at: string;
  updated_at: string;
}

/** Categories the backend accepts on upload. Keep in step with TenantDocument::CATEGORIES. */
export const TENANT_DOCUMENT_CATEGORIES = [
  { label: 'Other', value: 'other' },
  { label: 'ID Document', value: 'id_document' },
  { label: 'Lease Agreement', value: 'lease_agreement' },
  { label: 'Payment Receipt', value: 'payment_receipt' },
  { label: 'Insurance', value: 'insurance' },
  { label: 'Tax Form', value: 'tax_form' },
  { label: 'Correspondence', value: 'correspondence' }
];

/** Extensions the backend accepts on upload. Keep in step with the controller's `mimes` rule. */
export const TENANT_DOCUMENT_EXTENSIONS = [
  'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'
];

/** 10 MB, matching the backend's `max:10240` rule. */
export const TENANT_DOCUMENT_MAX_KB = 10240;

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

  getDocuments(tenantId: number, params?: ApiParams): Observable<PaginatedResponse<TenantDocument>> {
    return this.api.getPaginated<TenantDocument>(`${this.endpoint}/${tenantId}/documents`, params);
  }

  /** Upload one document against a tenant. `file` must not be renamed. */
  uploadDocument(tenantId: number, file: File, name?: string, category?: string): Observable<TenantDocument> {
    const form = new FormData();
    form.append('file', file, file.name);
    if (name) form.append('name', name);
    if (category) form.append('category', category);
    return this.api.postForm<TenantDocument>(`${this.endpoint}/${tenantId}/documents`, form);
  }

  downloadDocument(tenantId: number, documentId: number): Observable<Blob> {
    return this.api.getBlob(`${this.endpoint}/${tenantId}/documents/${documentId}/download`);
  }

  deleteDocument(tenantId: number, documentId: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${tenantId}/documents/${documentId}`);
  }
}