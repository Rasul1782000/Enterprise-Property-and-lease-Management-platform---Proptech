import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { Observable } from 'rxjs';

export interface DashboardStats {
  properties: { total: number; active: number; inactive: number };
  buildings: { total: number; active: number; under_maintenance: number };
  units: { total: number; vacant: number; occupied: number; reserved: number };
  tenants: { total: number; active: number; prospects: number };
  leases: { total: number; active: number; expiring_30: number; expiring_90: number; expired: number };
  invoices: { total: number; paid: number; pending: number; overdue: number; total_outstanding: number };
  revenue: { this_month: number; last_month: number; ytd: number; outstanding: number };
}

export interface ChartData {
  labels: string[];
  datasets: { label: string; data: number[]; backgroundColor?: string; borderColor?: string }[];
}

export interface RecentActivity {
  id: number;
  type: 'lease_created' | 'lease_signed' | 'lease_expiring' | 'payment_received' | 'invoice_overdue' | 'tenant_added' | 'maintenance_request';
  title: string;
  description: string;
  timestamp: string;
  related_id: number;
  related_type: string;
}

@Injectable({ providedIn: 'root' })
export class DashboardApiService {
  private readonly endpoint = 'dashboard';

  constructor(private api: ApiService) {}

  getStats(): Observable<DashboardStats> {
    return this.api.get<DashboardStats>(`${this.endpoint}/stats`);
  }

  getOccupancyChart(months = 12): Observable<ChartData> {
    return this.api.get<ChartData>(`${this.endpoint}/occupancy-chart`, { months });
  }

  getRevenueChart(months = 12): Observable<ChartData> {
    return this.api.get<ChartData>(`${this.endpoint}/revenue-chart`, { months });
  }

  getExpiringLeasesChart(months = 6): Observable<ChartData> {
    return this.api.get<ChartData>(`${this.endpoint}/expiring-leases-chart`, { months });
  }

  getRecentActivity(limit = 10): Observable<RecentActivity[]> {
    return this.api.get<RecentActivity[]>(`${this.endpoint}/recent-activity`, { limit });
  }

  getPropertyPerformance(propertyId: number): Observable<any> {
    return this.api.get<any>(`${this.endpoint}/property-performance/${propertyId}`);
  }
}