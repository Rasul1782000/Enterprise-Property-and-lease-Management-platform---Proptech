import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { PaginatedResponse, ApiParams } from '../../../core/types';
import { Observable } from 'rxjs';

export interface Property {
  id: number;
  code: string;
  name: string;
  type: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  country: string;
  occupancy_rate: number;
  status: 'active' | 'inactive';
  buildings_count: number;
  units_count: number;
  created_at: string;
  updated_at: string;
}

export interface CreatePropertyDto {
  code: string;
  name: string;
  type: string;
  address: string;
  city: string;
  state: string;
  zip: string;
  country: string;
}

export interface UpdatePropertyDto extends Partial<CreatePropertyDto> {}

@Injectable({ providedIn: 'root' })
export class PropertiesApiService {
  private readonly endpoint = 'properties';

  constructor(private api: ApiService) {}

  list(params?: ApiParams): Observable<PaginatedResponse<Property>> {
    return this.api.getPaginated<Property>(this.endpoint, { ...params, include: 'buildings' });
  }

  get(id: number): Observable<Property> {
    return this.api.get<Property>(`${this.endpoint}/${id}`, { include: 'buildings,units' });
  }

  create(dto: CreatePropertyDto): Observable<Property> {
    return this.api.post<Property>(this.endpoint, dto);
  }

  update(id: number, dto: UpdatePropertyDto): Observable<Property> {
    return this.api.put<Property>(`${this.endpoint}/${id}`, dto);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${id}`);
  }

  getOccupancy(id: number): Observable<{ occupancy_rate: number }> {
    return this.api.get<{ occupancy_rate: number }>(`${this.endpoint}/${id}/occupancy`);
  }

  export(params?: ApiParams): Observable<Blob> {
    return this.api.getBlob(`${this.endpoint}/export`, params);
  }
}