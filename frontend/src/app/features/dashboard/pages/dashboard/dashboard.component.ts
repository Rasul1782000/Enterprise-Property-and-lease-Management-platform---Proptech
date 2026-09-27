import { Component, OnInit, inject, signal } from '@angular/core';
import { DashboardApiService, DashboardStats, RecentActivity } from '../../services/dashboard-api.service';

@Component({
  selector: 'app-dashboard',
  standalone: false,
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  private dashboardApi = inject(DashboardApiService);

  stats = signal<DashboardStats>({
    properties: { total: 0, active: 0, inactive: 0 },
    buildings: { total: 0, active: 0, under_maintenance: 0 },
    units: { total: 0, vacant: 0, occupied: 0, reserved: 0 },
    tenants: { total: 0, active: 0, prospects: 0 },
    leases: { total: 0, active: 0, expiring_30: 0, expiring_90: 0, expired: 0 },
    invoices: { total: 0, paid: 0, pending: 0, overdue: 0, total_outstanding: 0 },
    revenue: { this_month: 0, last_month: 0, ytd: 0, outstanding: 0 }
  });
  recentActivity = signal<RecentActivity[]>([]);
  loading = signal(true);

  activityCols = ['timestamp', 'type', 'title', 'description'];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.dashboardApi.getStats().subscribe({
      next: (data) => { this.stats.set(data); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
    this.dashboardApi.getRecentActivity(10).subscribe({
      next: (data) => this.recentActivity.set(data)
    });
  }

  formatTime(iso: string): string {
    const date = new Date(iso);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const mins = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
  }

  formatActivityType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  getActivityChipClass(type: string): string {
    const classes: Record<string, string> = {
      'lease_created': 'bg-primary-50 text-primary-700',
      'lease_signed': 'bg-primary-100 text-primary-800',
      'lease_expiring': 'bg-primary-50 text-primary-700',
      'payment_received': 'bg-primary-100 text-primary-800',
      'invoice_overdue': 'bg-primary-600 text-white',
      'tenant_added': 'bg-zinc-200 text-zinc-800',
      'maintenance_request': 'bg-zinc-100 text-zinc-600'
    };
    return classes[type] || 'bg-zinc-100 text-zinc-600';
  }
}