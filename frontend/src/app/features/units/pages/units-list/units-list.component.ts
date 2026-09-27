import { Component, OnInit, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService, MenuItem, MessageService } from 'primeng/api';
import { UnitsApiService, Unit } from '../../services/units-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { UnitFormDialogData } from '../../components/unit-form/unit-form.component';

interface ListSortEvent { field: string; order: number | null | undefined; }
interface ListPageEvent { first: number; rows: number; }

@Component({
  selector: 'app-units-list',
  standalone: false,
  templateUrl: './units-list.component.html',
  styleUrls: ['./units-list.component.scss']
})
export class UnitsListComponent implements OnInit {
  private api = inject(UnitsApiService);
  private router = inject(Router);
  private confirmation = inject(ConfirmationService);
  private messages = inject(MessageService);

  cols = ['code', 'name', 'type', 'floor', 'area_sqft', 'base_rent', 'status', 'actions'];
  units = signal<Unit[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';
  formVisible = signal(false);
  formData = signal<UnitFormDialogData>({ mode: 'create' });
  private menuCache = new Map<number, MenuItem[]>();

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Unit>) => { this.units.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: ListSortEvent) { this.sort = e.field || this.sort; this.dir = e.order === 1 ? 'asc' : 'desc'; this.page = 1; this.load(); }
  onPage(e: ListPageEvent) { this.perPage = e.rows; this.page = Math.floor(e.first / e.rows) + 1; this.load(); }

  getStatusClass(s: string) { const c: Record<string,string> = {'vacant':'bg-primary-100 text-primary-800','occupied':'bg-zinc-200 text-zinc-800','reserved':'bg-primary-50 text-primary-700','under_maintenance':'bg-zinc-100'}; return c[s] || 'bg-zinc-100'; }

  statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      active: 'success', occupied: 'success', paid: 'success',
      inactive: 'secondary', former: 'secondary', cancelled: 'secondary',
      under_maintenance: 'warn', pending: 'warn', partial: 'warn', draft: 'warn', prospect: 'warn', reserved: 'warn',
      overdue: 'danger', terminated: 'danger', vacant: 'danger', expired: 'danger'
    };
    return map[status] ?? 'info';
  }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
  dateOnly(v: any): string { return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  pct(v: any): string { return Number(v || 0).toFixed(1) + '%'; }

  rowMenu(u: Unit): MenuItem[] {
    let items = this.menuCache.get(u.id);
    if (!items) {
      items = [
        { label: 'View', icon: 'pi pi-eye', command: () => this.router.navigate(['/units', u.id]) },
        { label: 'Edit', icon: 'pi pi-pencil', command: () => this.editUnit(u) },
        { label: 'Change Status', icon: 'pi pi-refresh', command: () => this.updateStatus(u) },
        { separator: true },
        { label: 'Delete', icon: 'pi pi-trash', command: () => this.deleteUnit(u) }
      ];
      this.menuCache.set(u.id, items);
    }
    return items;
  }

  editUnit(u: Unit) { this.openEditDialog(u); }
  deleteUnit(u: Unit) {
    this.confirmation.confirm({
      header: 'Delete unit',
      message: `Delete unit "${u.name}"?`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => this.api.delete(u.id).subscribe(() => {
        this.messages.add({ severity: 'success', summary: 'Deleted', detail: `Unit "${u.name}" was deleted` });
        this.load();
      })
    });
  }

  updateStatus(u: Unit) {
    const statuses: Unit['status'][] = ['vacant', 'occupied', 'reserved', 'under_maintenance'];
    const currentIndex = statuses.indexOf(u.status);
    const nextStatus = statuses[(currentIndex + 1) % statuses.length];
    this.api.updateStatus(u.id, nextStatus).subscribe(() => {
      this.messages.add({ severity: 'info', summary: 'Status updated', detail: `${u.name} is now ${nextStatus.replace('_', ' ')}` });
      this.load();
    });
  }

  openCreateDialog() { this.formData.set({ mode: 'create' }); this.formVisible.set(true); }
  openEditDialog(u: Unit) { this.formData.set({ mode: 'edit', unit: u }); this.formVisible.set(true); }
  onFormClosed(result: boolean) { this.formVisible.set(false); if (result) this.load(); }
}
