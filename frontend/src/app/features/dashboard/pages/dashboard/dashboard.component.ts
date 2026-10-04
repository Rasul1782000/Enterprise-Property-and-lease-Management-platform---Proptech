import { Component, OnInit, inject, signal } from '@angular/core';
import { DashboardApiService, DashboardStats, RecentActivity } from '../../services/dashboard-api.service';
import { Chart, ChartConfiguration, registerables } from 'chart.js';
import { forkJoin } from 'rxjs';
Chart.register(...registerables);

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
  isChartExpanded = signal(false);
  performanceSources = signal({
    occupancy: null as any,
    revenue: null as any,
    expiringLeases: null as any
  });

  activityCols = ['timestamp', 'type', 'title', 'description'];

  private chart?: Chart;

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    forkJoin({
      stats: this.dashboardApi.getStats(),
      occupancy: this.dashboardApi.getOccupancyChart(12),
      revenue: this.dashboardApi.getRevenueChart(12),
      expiringLeases: this.dashboardApi.getExpiringLeasesChart(6)
    }).subscribe({
      next: ({ stats, occupancy, revenue, expiringLeases }) => {
        this.stats.set(stats);
        this.performanceSources.set({ occupancy, revenue, expiringLeases });
        this.loading.set(false);
        this.updateChart();
      },
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

  private createChart(): void {
    if (this.chart) return;
    const canvas = document.getElementById('bubbleChartCanvas') as HTMLCanvasElement;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const config: ChartConfiguration = {
      type: 'bar',
      data: {
        labels: ['Occupancy', 'Revenue Collected', 'Revenue Outstanding', 'Expiring Leases'],
        datasets: [
          {
            label: 'Latest dashboard value',
            data: this.getComparisonValues(),
            backgroundColor: 'rgba(255, 107, 107, 0.65)',
            borderColor: 'rgba(255, 107, 107, 1)',
            borderWidth: 1,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        scales: {
          x: {
            title: {
              display: true,
              text: 'Performance Metrics'
            }
          },
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Value'
            },
            min: 0,
            ticks: {
              callback: function(value: any) {
                if (typeof value === 'number') {
                  return value.toLocaleString();
                }
                return value;
              }
            }
          }
        },
        plugins: {
          title: {
            display: true,
            text: 'Performance Metrics Comparison - All Dashboard Sources'
          },
          legend: {
            position: 'top',
            labels: {
              usePointStyle: true,
              padding: 20
            }
          },
          tooltip: {
            backgroundColor: 'rgba(45, 45, 45, 0.9)',
            titleFont: {
              size: 16,
              weight: 'bold'
            },
            bodyFont: {
              size: 14
            },
            callbacks: {
              label: function(context: any) {
                let label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                let value = context.raw;
                if (typeof value === 'number') {
                  if (context.dataIndex === 0) {
                    label += value.toFixed(1) + '%';
                  } else if (context.dataIndex === 1 || context.dataIndex === 2) {
                    label += '$' + value.toLocaleString();
                  } else {
                    label += value;
                  }
                } else {
                  label += value;
                }
                return label;
              }
            }
          }
        }
      }
    };

    this.chart = new Chart(ctx, config);
  }

  private updateChart(): void {
    this.createChart();
    const canvas = document.getElementById('bubbleChartCanvas') as HTMLCanvasElement;
    if (!canvas || !this.chart) return;
    this.chart.data.datasets[0].data = this.getComparisonValues();
    this.chart.update();
  }

  toggleBubble(): void {
    this.isChartExpanded.set(!this.isChartExpanded());
    if (this.isChartExpanded()) {
      setTimeout(() => this.updateChart());
    }
  }

  private getComparisonValues(): number[] {
    const sources = this.performanceSources();
    const latest = (source: any, datasetIndex = 0): number => {
      const data = source?.datasets?.[datasetIndex]?.data;
      return Array.isArray(data) && data.length ? Number(data[data.length - 1]) : 0;
    };
    const occupancyRate = this.stats().units.total > 0
      ? this.stats().units.occupied / this.stats().units.total * 100
      : latest(sources.occupancy) ;

    return [
      occupancyRate,
      latest(sources.revenue),
      latest(sources.revenue, 1),
      latest(sources.expiringLeases)
    ];
  }
}