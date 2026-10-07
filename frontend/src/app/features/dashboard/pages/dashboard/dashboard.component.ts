import {
  AfterViewInit,
  Component,
  ElementRef,
  OnDestroy,
  OnInit,
  ViewChild,
  computed,
  inject,
  signal
} from '@angular/core';
import { DashboardApiService, DashboardStats, RecentActivity } from '../../services/dashboard-api.service';
import { Chart, ChartConfiguration, registerables } from 'chart.js';
import { forkJoin } from 'rxjs';
Chart.register(...registerables);

type Tone = 'coral' | 'teal' | 'sunny';

interface ActivityStyle {
  icon: string;
  label: string;
  tone: Tone;
}

const EMPTY_STATS: DashboardStats = {
  properties: { total: 0, active: 0, inactive: 0 },
  buildings: { total: 0, active: 0, under_maintenance: 0 },
  units: { total: 0, vacant: 0, occupied: 0, reserved: 0 },
  tenants: { total: 0, active: 0, prospects: 0 },
  leases: { total: 0, active: 0, expiring_30: 0, expiring_90: 0, expired: 0 },
  invoices: { total: 0, paid: 0, pending: 0, overdue: 0, total_outstanding: 0 },
  revenue: { this_month: 0, last_month: 0, ytd: 0, outstanding: 0 }
};

@Component({
  selector: 'app-dashboard',
  standalone: false,
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit, AfterViewInit, OnDestroy {
  private dashboardApi = inject(DashboardApiService);

  @ViewChild('occupancyCanvas') occupancyCanvas?: ElementRef<HTMLCanvasElement>;
  @ViewChild('revenueCanvas') revenueCanvas?: ElementRef<HTMLCanvasElement>;
  @ViewChild('expiringCanvas') expiringCanvas?: ElementRef<HTMLCanvasElement>;

  stats = signal<DashboardStats>(EMPTY_STATS);
  recentActivity = signal<RecentActivity[]>([]);
  loading = signal(true);
  lastUpdated = signal<Date | null>(null);

  /* Raw series, held as signals so the template can drive empty/error states. */
  occupancySeries = signal<{ labels: string[]; occupied: number[]; vacant: number[] } | null>(null);
  revenueSeries = signal<{ labels: string[]; collected: number[]; outstanding: number[] } | null>(null);
  expiringSeries = signal<{ labels: string[]; expiring: number[] } | null>(null);

  private occupancyChart?: Chart;
  private revenueChart?: Chart;
  private expiringChart?: Chart;

  /* ---- Derived headline figures --------------------------------------- */
  readonly occupancyRate = computed(() => {
    const units = this.stats().units;
    if (!units.total) return 0;
    return (units.occupied / units.total) * 100;
  });

  readonly collectionRate = computed(() => {
    const invoices = this.stats().invoices;
    if (!invoices.total) return 0;
    return (invoices.paid / invoices.total) * 100;
  });

  readonly revenueTrend = computed(() => {
    const revenue = this.stats().revenue;
    if (!revenue.last_month) return null;
    const delta = ((revenue.this_month - revenue.last_month) / revenue.last_month) * 100;
    return { delta, up: delta >= 0 };
  });

  readonly leasesAtRisk = computed(() => {
    const leases = this.stats().leases;
    return leases.expiring_30 + leases.expired;
  });

  ngOnInit(): void {
    this.loadData();
  }

  ngAfterViewInit(): void {
    // Canvases only exist once the loading skeletons are swapped out, so give
    // the view a tick to render them before Chart.js measures anything.
    setTimeout(() => this.renderCharts(), 0);
  }

  ngOnDestroy(): void {
    this.destroyCharts();
  }

  loadData(): void {
    this.loading.set(true);
    forkJoin({
      stats: this.dashboardApi.getStats(),
      occupancy: this.dashboardApi.getOccupancyChart(12),
      revenue: this.dashboardApi.getRevenueChart(12),
      expiringLeases: this.dashboardApi.getExpiringLeasesChart(6)
    }).subscribe({
      next: ({ stats, occupancy, revenue, expiringLeases }) => {
        this.stats.set({ ...EMPTY_STATS, ...stats });
        this.occupancySeries.set({
          labels: occupancy?.labels ?? [],
          occupied: this.pickSeries(occupancy, 0),
          vacant: this.pickSeries(occupancy, 1)
        });
        this.revenueSeries.set({
          labels: revenue?.labels ?? [],
          collected: this.pickSeries(revenue, 0),
          outstanding: this.pickSeries(revenue, 1)
        });
        this.expiringSeries.set({
          labels: expiringLeases?.labels ?? [],
          expiring: this.pickSeries(expiringLeases, 0)
        });
        this.loading.set(false);
        this.lastUpdated.set(new Date());
        setTimeout(() => this.renderCharts(), 0);
      },
      error: () => {
        this.loading.set(false);
        this.lastUpdated.set(new Date());
      }
    });

    this.dashboardApi.getRecentActivity(10).subscribe({
      next: (data) => this.recentActivity.set(data ?? []),
      error: () => this.recentActivity.set([])
    });
  }

  /* ---- Formatting ------------------------------------------------------ */
  formatCurrency(value: number): string {
    const amount = Number(value) || 0;
    return '$' + Math.round(amount).toLocaleString('en-US');
  }

  formatCompactCurrency(value: number): string {
    const amount = Number(value) || 0;
    const abs = Math.abs(amount);
    if (abs >= 1_000_000) return '$' + (amount / 1_000_000).toFixed(1) + 'M';
    if (abs >= 1_000) return '$' + Math.round(amount / 1_000) + 'k';
    return '$' + Math.round(amount).toLocaleString('en-US');
  }

  formatPercent(value: number, digits = 1): string {
    return `${(Number(value) || 0).toFixed(digits)}%`;
  }

  formatTime(iso: string): string {
    const date = new Date(iso);
    if (isNaN(date.getTime())) return '';
    const diff = Date.now() - date.getTime();
    const mins = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }

  /* ---- Activity presentation ------------------------------------------ */
  activityStyle(type: string): ActivityStyle {
    const styles: Record<string, ActivityStyle> = {
      lease_created: { icon: 'pi pi-file-plus', label: 'Lease created', tone: 'coral' },
      lease_signed: { icon: 'pi pi-verified', label: 'Lease signed', tone: 'teal' },
      lease_expiring: { icon: 'pi pi-clock', label: 'Lease expiring', tone: 'sunny' },
      payment_received: { icon: 'pi pi-wallet', label: 'Payment received', tone: 'teal' },
      invoice_overdue: { icon: 'pi pi-exclamation-triangle', label: 'Invoice overdue', tone: 'coral' },
      tenant_added: { icon: 'pi pi-user-plus', label: 'Tenant added', tone: 'sunny' },
      maintenance_request: { icon: 'pi pi-wrench', label: 'Maintenance', tone: 'teal' }
    };
    return styles[type] ?? { icon: 'pi pi-bell', label: this.formatActivityType(type), tone: 'teal' };
  }

  private formatActivityType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  /* ---- Charts ---------------------------------------------------------- */
  private pickSeries(source: { datasets?: { data?: unknown[] }[] } | null, index: number): number[] {
    const data = source?.datasets?.[index]?.data;
    return Array.isArray(data) ? data.map(v => Number(v) || 0) : [];
  }

  private renderCharts(): void {
    if (this.loading()) return;
    const occupancy = this.occupancySeries();
    const revenue = this.revenueSeries();
    const expiring = this.expiringSeries();
    if (!occupancy || !revenue || !expiring) return;

    this.occupancyChart = this.renderBarChart(
      this.occupancyCanvas,
      occupancy.labels,
      [
        { label: 'Occupied', data: occupancy.occupied, backgroundColor: 'rgba(78, 205, 196, 0.85)', borderColor: '#3DBEB5' },
        { label: 'Vacant', data: occupancy.vacant, backgroundColor: 'rgba(255, 230, 109, 0.85)', borderColor: '#F5D44E' }
      ],
      { stacked: true }
    );

    this.revenueChart = this.renderBarChart(
      this.revenueCanvas,
      revenue.labels,
      [
        { label: 'Collected', data: revenue.collected, backgroundColor: 'rgba(78, 205, 196, 0.85)', borderColor: '#3DBEB5' },
        { label: 'Outstanding', data: revenue.outstanding, backgroundColor: 'rgba(255, 107, 107, 0.85)', borderColor: '#E85D5D' }
      ],
      { stacked: false, currency: true }
    );

    this.expiringChart = this.renderBarChart(
      this.expiringCanvas,
      expiring.labels,
      [
        { label: 'Leases expiring', data: expiring.expiring, backgroundColor: 'rgba(255, 230, 109, 0.9)', borderColor: '#F5D44E' }
      ],
      { stacked: false, legend: false }
    );
  }

  private renderBarChart(
    host: ElementRef<HTMLCanvasElement> | undefined,
    labels: string[],
    datasets: { label: string; data: number[]; backgroundColor: string; borderColor: string }[],
    options: { stacked: boolean; currency?: boolean; legend?: boolean; tick?: (value: unknown) => string }
  ): Chart | undefined {
    const canvas = host?.nativeElement;
    if (!canvas || !labels.length) return undefined;

    const formatTick = (value: unknown): string => {
      if (options.tick) return options.tick(value);
      return options.currency ? this.formatCompactCurrency(Number(value)) : String(Math.round(Number(value)));
    };

    /* Rounded tops read as softer, more deliberate bars; the radius is capped so
       short bars (a lease count of 2 next to a value of 6) don't turn into pills. */
    const rounded = datasets.map(d => ({
      ...d,
      borderWidth: 0,
      borderRadius: 8,
      borderSkipped: false as const,
      maxBarThickness: 46,
      barPercentage: 0.72,
      categoryPercentage: 0.78
    }));

    const config: ChartConfiguration<'bar'> = {
      type: 'bar',
      data: { labels, datasets: rounded },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 450, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 8 } },
        scales: {
          x: {
            stacked: options.stacked,
            grid: { display: false },
            border: { display: false },
            ticks: { color: '#6b7280', font: { size: 12, family: 'Poppins', weight: 600 } }
          },
          y: {
            stacked: options.stacked,
            beginAtZero: true,
            border: { display: false },
            grid: { color: '#eef0f2', drawTicks: false },
            ticks: {
              color: '#9ca3af',
              font: { size: 12, family: 'Poppins' },
              maxTicksLimit: 5,
              padding: 8,
              callback: (value) => formatTick(value)
            }
          }
        },
        plugins: {
          legend: {
            /* A single-series chart gains nothing from a legend naming the only
               colour, and the space is better spent on the plot itself. */
            display: options.legend !== false,
            position: 'top',
            align: 'end',
            labels: {
              usePointStyle: true,
              pointStyle: 'circle',
              boxWidth: 8,
              boxHeight: 8,
              padding: 18,
              color: '#6b7280',
              font: { size: 12, family: 'Poppins', weight: 600 }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(31, 41, 55, 0.95)',
            padding: 12,
            cornerRadius: 14,
            displayColors: true,
            boxWidth: 8,
            boxHeight: 8,
            usePointStyle: true,
            titleFont: { size: 13, family: 'Poppins', weight: 'bold' },
            bodyFont: { size: 13, family: 'Poppins' },
            callbacks: {
              label: (context) =>
                ` ${context.dataset.label}: ${(context.parsed.y ?? 0).toLocaleString('en-US')}`
            }
          }
        }
      }
    };

    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
    return new Chart(canvas, config);
  }

  private destroyCharts(): void {
    this.occupancyChart?.destroy();
    this.revenueChart?.destroy();
    this.expiringChart?.destroy();
    this.occupancyChart = undefined;
    this.revenueChart = undefined;
    this.expiringChart = undefined;
  }
}
