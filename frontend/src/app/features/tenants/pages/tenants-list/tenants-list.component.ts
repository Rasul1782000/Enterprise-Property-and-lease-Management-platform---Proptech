import { Component, OnInit, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService, MenuItem, MessageService } from 'primeng/api';
import { TenantsApiService, Tenant } from '../../services/tenants-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { TenantFormDialogData } from '../../components/tenant-form/tenant-form.component';

interface ListSortEvent { field: string; order: number | null | undefined; }
interface ListPageEvent { first: number; rows: number; }

@Component({
  selector: 'app-tenants-list',
  standalone: false,
  templateUrl: './tenants-list.component.html',
  styleUrls: ['./tenants-list.component.scss']
})
export class TenantsListComponent implements OnInit {
  private api = inject(TenantsApiService);
  private router = inject(Router);
  private confirmation = inject(ConfirmationService);
  private messages = inject(MessageService);

  cols = ['code', 'name', 'company', 'phone', 'status', 'leases_count', 'actions'];
  tenants = signal<Tenant[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';
  formVisible = signal(false);
  formData = signal<TenantFormDialogData>({ mode: 'create' });
  private menuCache = new Map<number, MenuItem[]>();

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Tenant>) => { this.tenants.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: ListSortEvent) { this.sort = e.field || this.sort; this.dir = e.order === 1 ? 'asc' : 'desc'; this.page = 1; this.load(); }
  onPage(e: ListPageEvent) { this.perPage = e.rows; this.page = Math.floor(e.first / e.rows) + 1; this.load(); }

  getStatusClass(s: string) { const c: Record<string,string> = {'active':'bg-primary-100 text-primary-800','inactive':'bg-zinc-100','prospect':'bg-zinc-200 text-zinc-800','former':'bg-primary-50 text-primary-700'}; return c[s] || 'bg-zinc-100'; }

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

  rowMenu(t: Tenant): MenuItem[] {
    let items = this.menuCache.get(t.id);
    if (!items) {
      items = [
        { label: 'View', icon: 'pi pi-eye', command: () => this.router.navigate(['/tenants', t.id]) },
        { label: 'Edit', icon: 'pi pi-pencil', command: () => this.editTenant(t) },
        { separator: true },
        { label: 'Delete', icon: 'pi pi-trash', command: () => this.deleteTenant(t) }
      ];
      this.menuCache.set(t.id, items);
    }
    return items;
  }

  editTenant(t: Tenant) { this.openEditDialog(t); }
  deleteTenant(t: Tenant) {
    this.confirmation.confirm({
      header: 'Delete tenant',
      message: `Delete tenant "${t.first_name} ${t.last_name}"?`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => this.api.delete(t.id).subscribe(() => {
        this.messages.add({ severity: 'success', summary: 'Deleted', detail: `Tenant "${t.first_name} ${t.last_name}" was deleted` });
        this.load();
      })
    });
  }

  openCreateDialog() { this.formData.set({ mode: 'create' }); this.formVisible.set(true); }
  openEditDialog(t: Tenant) { this.formData.set({ mode: 'edit', tenant: t }); this.formVisible.set(true); }
  onFormClosed(result: boolean) { this.formVisible.set(false); if (result) this.load(); }
}
