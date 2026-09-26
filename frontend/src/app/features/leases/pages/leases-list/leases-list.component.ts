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
import { LeasesApiService, Lease } from '../../services/leases-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { LeaseFormComponent } from '../../components/lease-form/lease-form.component';

@Component({
  selector: 'app-leases-list',
  standalone: false,
  templateUrl: './leases-list.component.html',
  styleUrls: ['./leases-list.component.scss']
})
export class LeasesListComponent implements OnInit {
  private api = inject(LeasesApiService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  cols = ['code', 'property', 'unit', 'tenant', 'type', 'start_date', 'end_date', 'rent_amount', 'status', 'actions'];
  leases = signal<Lease[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Lease>) => { this.leases.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: Sort) { this.sort = e.active; this.dir = e.direction || 'desc'; this.load(); }
  onPage(e: PageEvent) { this.page = e.pageIndex + 1; this.perPage = e.pageSize; this.load(); }

  getStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-slate-100','active':'bg-emerald-100 text-emerald-700','expired':'bg-red-100 text-red-700','terminated':'bg-slate-100','renewed':'bg-blue-100 text-blue-700'}; return c[s] || 'bg-slate-100'; }

  editLease(l: Lease) { this.openEditDialog(l); }
  signLease(l: Lease) { if (confirm('Sign this lease?')) this.api.sign(l.id).subscribe(() => this.load()); }
  terminateLease(l: Lease) { const reason = prompt('Termination reason:'); if (reason) { const today = new Date().toISOString().split('T')[0]; this.api.terminate(l.id, today, reason).subscribe(() => this.load()); } }

  openEditDialog(l: Lease) { const ref = this.dialog.open(LeaseFormComponent, { width: '700px', data: { mode: 'edit', lease: l } }); ref.afterClosed().subscribe(r => r && this.load()); }
}