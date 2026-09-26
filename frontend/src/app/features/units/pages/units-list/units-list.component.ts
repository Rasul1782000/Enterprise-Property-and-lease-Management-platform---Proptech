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
import { UnitsApiService, Unit } from '../../services/units-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { UnitFormComponent } from '../../components/unit-form/unit-form.component';

@Component({
  selector: 'app-units-list',
  standalone: false,
  templateUrl: './units-list.component.html',
  styleUrls: ['./units-list.component.scss']
})
export class UnitsListComponent implements OnInit {
  private api = inject(UnitsApiService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  cols = ['code', 'name', 'type', 'floor', 'area_sqft', 'base_rent', 'status', 'actions'];
  units = signal<Unit[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Unit>) => { this.units.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: Sort) { this.sort = e.active; this.dir = e.direction || 'desc'; this.load(); }
  onPage(e: PageEvent) { this.page = e.pageIndex + 1; this.perPage = e.pageSize; this.load(); }

  getStatusClass(s: string) { const c: Record<string,string> = {'vacant':'bg-emerald-100 text-emerald-700','occupied':'bg-blue-100 text-blue-700','reserved':'bg-amber-100 text-amber-700','under_maintenance':'bg-slate-100'}; return c[s] || 'bg-slate-100'; }

  editUnit(u: Unit) { this.openEditDialog(u); }
  deleteUnit(u: Unit) { if (confirm(`Delete unit "${u.name}"?`)) this.api.delete(u.id).subscribe(() => this.load()); }

  updateStatus(u: Unit) {
    const statuses: Unit['status'][] = ['vacant', 'occupied', 'reserved', 'under_maintenance'];
    const currentIndex = statuses.indexOf(u.status);
    const nextStatus = statuses[(currentIndex + 1) % statuses.length];
    this.api.updateStatus(u.id, nextStatus).subscribe(() => this.load());
  }

  openCreateDialog() {
    const ref = this.dialog.open(UnitFormComponent, { width: '600px', data: { mode: 'create' } });
    ref.afterClosed().subscribe(r => r && this.load());
  }
  openEditDialog(u: Unit) {
    const ref = this.dialog.open(UnitFormComponent, { width: '600px', data: { mode: 'edit', unit: u } });
    ref.afterClosed().subscribe(r => r && this.load());
  }
}