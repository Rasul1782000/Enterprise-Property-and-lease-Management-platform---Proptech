import { Directive, OnInit, inject, signal } from '@angular/core';
import { ConfirmationService, MessageService } from 'primeng/api';
import { OperationsApiService, ModuleStats } from './operations-api.service';
import { ApiParams, PaginatedResponse } from '../types';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

export type TagSeverity = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast';

const EMPTY_STATS: ModuleStats = { total: 0, attention: 0, by_status: {} };

const SEVERITY: Record<string, TagSeverity> = {
  active: 'success', paid: 'success', executed: 'success', registered: 'success',
  approved: 'success', noc_issued: 'success', recovered: 'success', cleared: 'success',
  draft: 'secondary', sent: 'info', submitted: 'info', deposited: 'info',
  notified: 'info', promise_to_pay: 'info', inspection_scheduled: 'info',
  under_review: 'info', under_negotiation: 'info', partially_signed: 'info',
  partially_recovered: 'info', reservation: 'info',
  pending: 'warn', sealed: 'warn', reserved: 'warn', issued: 'warn',
  partially_paid: 'warn', new: 'warn', not_submitted: 'warn', returned: 'warn',
  replaced: 'warn', expired: 'warn', escalated: 'warn',
  overdue: 'danger', rejected: 'danger', bounced: 'danger', noc_rejected: 'danger',
  legal_action: 'danger', frozen: 'danger', written_off: 'danger',
  terminated: 'secondary', released: 'secondary', closed: 'secondary', cancelled: 'secondary',
  declined: 'secondary'
};

@Directive()
export abstract class ModulePage<T extends { id: number }> implements OnInit {
  protected readonly api = inject(OperationsApiService);
  protected readonly messages = inject(MessageService);
  protected readonly confirmation = inject(ConfirmationService);

  abstract readonly endpoint: string;

  protected readonly Math = Math;

  protected defaultSort = 'id';

  rows = signal<T[]>([]);
  stats = signal<ModuleStats>(EMPTY_STATS);
  total = signal(0);
  loading = signal(false);

  perPage = 15;
  page = 1;
  sort = '';
  dir: 'asc' | 'desc' = 'asc';

  searchTerm = '';
  statusFilter: string | null = null;
  
  filters: Record<string, any> = {};

  ngOnInit(): void {
    this.sort = this.defaultSort;
    this.reload();
  }

  reload(): void {
    this.load();
    this.loadStats();
  }

  loadStats(): void {
    this.api.stats(this.endpoint).subscribe({
      next: s => this.stats.set(s),
      error: () => this.stats.set(EMPTY_STATS)
    });
  }

  load(): void {
    this.loading.set(true);
    this.api.list<T>(this.endpoint, this.params()).subscribe({
      next: (res: PaginatedResponse<T>) => {
        this.rows.set(res.data);
        this.total.set(res.meta.total);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  protected params(): ApiParams {
    const params: ApiParams = {
      page: this.page,
      per_page: this.perPage,
      sort: this.sort,
      direction: this.dir,
      search: this.searchTerm || undefined
    };
    if (this.statusFilter) {
      params['filter[status]'] = this.statusFilter;
    }
    Object.entries(this.filters).forEach(([field, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params[`filter[${field}]`] = value;
      }
    });
    return params;
  }

  onLazyLoad(event: any): void {
    if (event?.sortField) {
      this.sort = event.field;
      this.dir = event.order === 1 ? 'asc' : 'desc';
    } else if (event) {
      this.perPage = event.rows;
      this.page = Math.floor(event.first / event.rows) + 1;
    }
    this.load();
  }

  onSearch(value: string): void {
    this.searchTerm = value;
    this.page = 1;
    this.load();
  }

  onStatusFilter(status: string | null): void {
    this.statusFilter = status;
    this.page = 1;
    this.load();
  }

  resetFilters(): void {
    this.searchTerm = '';
    this.statusFilter = null;
    this.filters = {};
    this.page = 1;
    this.load();
  }

  act(row: T, action: string, detail: string, payload: Record<string, any> = {}): void {
    this.api.run<T>(this.endpoint, row.id, action, payload).subscribe({
      next: () => {
        this.messages.add({ severity: 'success', summary: 'Done', detail });
        this.reload();
      },
      error: () => this.messages.add({ severity: 'error', summary: 'Action failed', detail: 'That transition is not available right now.' })
    });
  }

  patch(row: T, payload: Record<string, any>, detail: string): void {
    this.api.update<T>(this.endpoint, row.id, payload as Partial<T>).subscribe({
      next: () => {
        this.messages.add({ severity: 'success', summary: 'Updated', detail });
        this.reload();
      },
      error: () => this.messages.add({ severity: 'error', summary: 'Update failed', detail: 'Please try again.' })
    });
  }

  remove(row: T, label: string): void {
    this.confirmation.confirm({
      header: 'Delete record',
      message: `Delete ${label}? This cannot be undone.`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => {
        this.api.remove(this.endpoint, row.id).subscribe({
          next: () => {
            this.messages.add({ severity: 'success', summary: 'Deleted', detail: `${label} was deleted.` });
            this.reload();
          },
          error: () => this.messages.add({ severity: 'error', summary: 'Delete failed', detail: 'Please try again.' })
        });
      }
    });
  }

  money(value: any, currency = 'AED'): string {
    const n = Number(value || 0);
    try {
      return new Intl.NumberFormat('en-AE', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
    } catch {
      return `${currency} ${n.toLocaleString()}`;
    }
  }

  number(value: any): string {
    return Number(value || 0).toLocaleString();
  }

  dateOnly(value: any): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  dateTime(value: any): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  daysUntil(value: any): number {
    return Math.round((new Date(value).getTime() - Date.now()) / 86400000);
  }

  titleCase(value: string): string {
    return String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  severity(status: string): TagSeverity {
    return SEVERITY[status] ?? 'info';
  }

  isOverdue(value: any): boolean {
    return !!value && new Date(value).getTime() < Date.now();
  }

  count(status: string): number {
    return Number(this.stats().by_status?.[status] ?? 0);
  }

  protected card(label: string, value: string | number, icon: string, tone: StatCard['tone'], hint?: string): StatCard {
    return { label, value, icon, tone, hint };
  }

  protected moneyCards(cards: { label: string; field: string; icon: string; tone: StatCard['tone'] }[]): StatCard[] {
    return cards.map(c => this.card(c.label, this.money(this.stats()[c.field]), c.icon, c.tone));
  }
}
