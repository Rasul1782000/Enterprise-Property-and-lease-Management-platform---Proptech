import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { PaginatedResponse, ApiParams } from '../../../core/types';
import { Observable } from 'rxjs';

export interface Lease {
  id: number;
  property_id: number;
  building_id: number;
  unit_id: number;
  tenant_id: number;
  code: string;
  type: 'fixed' | 'periodic' | 'commercial' | 'residential';
  status: 'draft' | 'active' | 'expired' | 'terminated' | 'renewed';
  start_date: string;
  end_date: string;
  rent_amount: number;
  deposit_amount: number;
  payment_frequency: 'monthly' | 'quarterly' | 'annually';
  escalation_clause: string;
  renewal_options: number;
  terms: string;
  signed_at: string | null;
  terminated_at: string | null;
  created_at: string;
  updated_at: string;
  property?: any;
  building?: any;
  unit?: any;
  tenant?: any;
}

export interface CreateLeaseDto {
  property_id: number;
  building_id: number;
  unit_id: number;
  tenant_id: number;
  code: string;
  type: Lease['type'];
  start_date: string;
  end_date: string;
  rent_amount: number;
  deposit_amount: number;
  payment_frequency: Lease['payment_frequency'];
  escalation_clause?: string;
  renewal_options?: number;
  terms?: string;
}

export type UpdateLeaseDto = Partial<CreateLeaseDto>;

export interface LeaseWizardData {
  step: number;
  property_id?: number;
  building_id?: number;
  unit_id?: number;
  tenant_id?: number;
  lease_data?: Partial<CreateLeaseDto>;
}

@Injectable({ providedIn: 'root' })
export class LeasesApiService {
  private readonly endpoint = 'leases';

  constructor(private api: ApiService) {}

  list(params?: ApiParams): Observable<PaginatedResponse<Lease>> {
    return this.api.getPaginated<Lease>(this.endpoint, { ...params, include: 'property,building,unit,tenant' });
  }

  get(id: number): Observable<Lease> {
    return this.api.get<Lease>(`${this.endpoint}/${id}`, { include: 'property,building,unit,tenant,invoices,payments' });
  }

  getByProperty(propertyId: number, params?: ApiParams): Observable<PaginatedResponse<Lease>> {
    return this.api.getPaginated<Lease>(this.endpoint, { ...params, 'filter[property_id]': propertyId });
  }

  getByUnit(unitId: number, params?: ApiParams): Observable<PaginatedResponse<Lease>> {
    return this.api.getPaginated<Lease>(this.endpoint, { ...params, 'filter[unit_id]': unitId });
  }

  getByTenant(tenantId: number, params?: ApiParams): Observable<PaginatedResponse<Lease>> {
    return this.api.getPaginated<Lease>(this.endpoint, { ...params, 'filter[tenant_id]': tenantId });
  }

  getActive(params?: ApiParams): Observable<PaginatedResponse<Lease>> {
    return this.api.getPaginated<Lease>(this.endpoint, { ...params, 'filter[status]': 'active' });
  }

  getExpiring(days = 90, params?: ApiParams): Observable<PaginatedResponse<Lease>> {
    return this.api.getPaginated<Lease>(this.endpoint, { ...params, 'filter[expiring_within]': days });
  }

  create(dto: CreateLeaseDto): Observable<Lease> {
    return this.api.post<Lease>(this.endpoint, dto);
  }

  update(id: number, dto: UpdateLeaseDto): Observable<Lease> {
    return this.api.put<Lease>(`${this.endpoint}/${id}`, dto);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${id}`);
  }

  sign(id: number): Observable<Lease> {
    return this.api.post<Lease>(`${this.endpoint}/${id}/sign`, {});
  }

  terminate(id: number, terminatedAt: string, reason: string): Observable<Lease> {
    return this.api.post<Lease>(`${this.endpoint}/${id}/terminate`, { terminated_at: terminatedAt, reason });
  }

  renew(id: number, newEndDate: string, newRentAmount: number): Observable<Lease> {
    return this.api.post<Lease>(`${this.endpoint}/${id}/renew`, { end_date: newEndDate, rent_amount: newRentAmount });
  }

  generateDocument(id: number, template: string): Observable<Blob> {
    return this.api.getBlob(`${this.endpoint}/${id}/document`, { template });
  }
}
