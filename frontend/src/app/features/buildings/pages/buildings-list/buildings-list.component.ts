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
import { BuildingsApiService, Building } from '../../services/buildings-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { BuildingFormComponent } from '../../components/building-form/building-form.component';

@Component({
  selector: 'app-buildings-list',
  standalone: false,
  templateUrl: './buildings-list.component.html',
  styleUrls: ['./buildings-list.component.scss']
})
export class BuildingsListComponent implements OnInit {
  private api = inject(BuildingsApiService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  cols = ['code', 'name', 'property', 'floors', 'units_count', 'status', 'actions'];
  buildings = signal<Building[]>([]);
  total = signal(0);
  loading = signal(false);

  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Building>) => { this.buildings.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: Sort) { this.sort = e.active; this.dir = e.direction || 'desc'; this.load(); }
  onPage(e: PageEvent) { this.page = e.pageIndex + 1; this.perPage = e.pageSize; this.load(); }

  editBuilding(b: Building) { this.openEditDialog(b); }
  deleteBuilding(b: Building) { if (confirm(`Delete building "${b.name}"?`)) this.api.delete(b.id).subscribe(() => this.load()); }

  openCreateDialog() {
    const ref = this.dialog.open(BuildingFormComponent, { width: '600px', data: { mode: 'create' } });
    ref.afterClosed().subscribe(r => r && this.load());
  }

  openEditDialog(b: Building) {
    const ref = this.dialog.open(BuildingFormComponent, { width: '600px', data: { mode: 'edit', building: b } });
    ref.afterClosed().subscribe(r => r && this.load());
  }
}