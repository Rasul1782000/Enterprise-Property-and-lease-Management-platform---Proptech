import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { MatTableModule } from '@angular/material/table';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatSortModule, Sort } from '@angular/material/sort';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatMenuModule } from '@angular/material/menu';
import { MatCardModule } from '@angular/material/card';
import { TenantsApiService, Tenant } from '../../services/tenants-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { TenantFormComponent } from '../../components/tenant-form/tenant-form.component';

@Component({
  selector: 'app-tenants-list',
  standalone: false,
  templateUrl: './tenants-list.component.html',
  styleUrls: ['./tenants-list.component.scss']
})
export class TenantsListComponent implements OnInit {
  private api = inject(TenantsApiService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  cols = ['code', 'name', 'company', 'phone', 'status', 'leases_count', 'actions'];
  tenants = signal<Tenant[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Tenant>) => { this.tenants.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: Sort) { this.sort = e.active; this.dir = e.direction || 'desc'; this.load(); }
  onPage(e: PageEvent) { this.page = e.pageIndex + 1; this.perPage = e.pageSize; this.load(); }

  getStatusClass(s: string) { const c: Record<string,string> = {'active':'bg-emerald-100 text-emerald-700','inactive':'bg-slate-100','prospect':'bg-blue-100 text-blue-700','former':'bg-amber-100 text-amber-700'}; return c[s] || 'bg-slate-100'; }

  editTenant(t: Tenant) { this.openEditDialog(t); }
  deleteTenant(t: Tenant) { if (confirm(`Delete tenant "${t.first_name} ${t.last_name}"?`)) this.api.delete(t.id).subscribe(() => this.load()); }

  openCreateDialog() { const ref = this.dialog.open(TenantFormComponent, { width: '600px', data: { mode: 'create' } }); ref.afterClosed().subscribe(r => r && this.load()); }
  openEditDialog(t: Tenant) { const ref = this.dialog.open(TenantFormComponent, { width: '600px', data: { mode: 'edit', tenant: t } }); ref.afterClosed().subscribe(r => r && this.load()); }
}