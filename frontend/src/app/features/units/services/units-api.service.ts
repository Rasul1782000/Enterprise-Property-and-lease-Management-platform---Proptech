import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { PaginatedResponse, ApiParams } from '../../../core/types';
import { Observable } from 'rxjs';

export interface Unit {
  id: number;
  building_id: number;
  property_id: number;
  code: string;
  name: string;
  type: 'residential' | 'commercial' | 'office' | 'retail' | 'warehouse';
  floor: number;
  area_sqft: number;
  bedrooms: number;
  bathrooms: number;
  base_rent: number;
  status: 'vacant' | 'occupied' | 'reserved' | 'under_maintenance';
  current_lease_id: number | null;
  current_tenant_id: number | null;
  building?: { id: number; name: string; code: string };
  property?: { id: number; name: string; code: string };
  created_at: string;
  updated_at: string;
}

export interface CreateUnitDto {
  building_id: number;
  code: string;
  name: string;
  type: Unit['type'];
  floor: number;
  area_sqft: number;
  bedrooms: number;
  bathrooms: number;
  base_rent: number;
}

export type UpdateUnitDto = Partial<CreateUnitDto>;

@Injectable({ providedIn: 'root' })
export class UnitsApiService {
  private readonly endpoint = 'units';

  constructor(private api: ApiService) {}

  list(params?: ApiParams): Observable<PaginatedResponse<Unit>> {
    return this.api.getPaginated<Unit>(this.endpoint, { ...params, include: 'building,property,tenant,lease' });
  }

  get(id: number): Observable<Unit> {
    return this.api.get<Unit>(`${this.endpoint}/${id}`, { include: 'building,property,tenant,lease' });
  }

  getByBuilding(buildingId: number, params?: ApiParams): Observable<PaginatedResponse<Unit>> {
    return this.api.getPaginated<Unit>(this.endpoint, { ...params, 'filter[building_id]': buildingId });
  }

  getByProperty(propertyId: number, params?: ApiParams): Observable<PaginatedResponse<Unit>> {
    return this.api.getPaginated<Unit>(this.endpoint, { ...params, 'filter[property_id]': propertyId });
  }

  getVacant(params?: ApiParams): Observable<PaginatedResponse<Unit>> {
    return this.api.getPaginated<Unit>(this.endpoint, { ...params, 'filter[status]': 'vacant' });
  }

  create(dto: CreateUnitDto): Observable<Unit> {
    return this.api.post<Unit>(this.endpoint, dto);
  }

  update(id: number, dto: UpdateUnitDto): Observable<Unit> {
    return this.api.put<Unit>(`${this.endpoint}/${id}`, dto);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${id}`);
  }

  updateStatus(id: number, status: Unit['status']): Observable<Unit> {
    return this.api.patch<Unit>(`${this.endpoint}/${id}/status`, { status });
  }
}