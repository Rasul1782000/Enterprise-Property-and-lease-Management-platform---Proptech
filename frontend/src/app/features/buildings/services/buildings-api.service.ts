import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { PaginatedResponse, ApiParams } from '../../../core/types';
import { Observable } from 'rxjs';

export interface Building {
  id: number;
  property_id: number;
  code: string;
  name: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  floors: number;
  units_count: number;
  status: 'active' | 'inactive' | 'under_maintenance';
  property?: { id: number; name: string; code: string };
  created_at: string;
  updated_at: string;
}

export interface CreateBuildingDto {
  property_id: number;
  code: string;
  name: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  floors: number;
}

export interface UpdateBuildingDto extends Partial<CreateBuildingDto> {}

@Injectable({ providedIn: 'root' })
export class BuildingsApiService {
  private readonly endpoint = 'buildings';

  constructor(private api: ApiService) {}

  list(params?: ApiParams): Observable<PaginatedResponse<Building>> {
    return this.api.getPaginated<Building>(this.endpoint, { ...params, include: 'property' });
  }

  get(id: number): Observable<Building> {
    return this.api.get<Building>(`${this.endpoint}/${id}`, { include: 'property,units' });
  }

  getByProperty(propertyId: number, params?: ApiParams): Observable<PaginatedResponse<Building>> {
    return this.api.getPaginated<Building>(this.endpoint, { ...params, 'filter[property_id]': propertyId });
  }

  create(dto: CreateBuildingDto): Observable<Building> {
    return this.api.post<Building>(this.endpoint, dto);
  }

  update(id: number, dto: UpdateBuildingDto): Observable<Building> {
    return this.api.put<Building>(`${this.endpoint}/${id}`, dto);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${id}`);
  }
}