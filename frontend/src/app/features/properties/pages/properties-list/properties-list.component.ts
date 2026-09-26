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
import { PropertiesApiService, Property } from '../../services/properties-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { PropertyContextService } from '../../../../core/services/property-context.service';
import { FilterBarComponent, FilterEvent } from '@shared/components/filter-bar/filter-bar.component';
import { PropertyFormComponent } from '../../components/property-form/property-form.component';

@Component({
  selector: 'app-properties-list',
  standalone: false,
  templateUrl: './properties-list.component.html',
  styleUrls: ['./properties-list.component.scss']
})
export class PropertiesListComponent implements OnInit {
  private api = inject(PropertiesApiService);
  private ctx = inject(PropertyContextService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  cols = ['code', 'name', 'type', 'city', 'buildings_count', 'units_count', 'occupancy', 'status', 'actions'];
  properties = signal<Property[]>([]);
  total = signal(0);
  loading = signal(false);

  perPage = 15;
  page = 1;
  sort = 'created_at';
  dir: 'asc' | 'desc' = 'desc';
  search = '';
  status = '';

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({
      per_page: this.perPage,
      page: this.page,
      sort: this.sort,
      direction: this.dir,
      'filter[name]': this.search || undefined,
      'filter[status]': this.status || undefined
    }).subscribe({
      next: (res: PaginatedResponse<Property>) => {
        this.properties.set(res.data);
        this.total.set(res.meta.total);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  onFilter(e: FilterEvent) {
    this.search = e.search;
    this.status = e.status;
    this.page = 1;
    this.load();
  }

  onSort(e: Sort) {
    this.sort = e.active;
    this.dir = e.direction || 'desc';
    this.load();
  }

  onPage(e: PageEvent) {
    this.page = e.pageIndex + 1;
    this.perPage = e.pageSize;
    this.load();
  }

  selectProperty(p: Property) {
    this.ctx.selectProperty(p.id);
    this.router.navigate(['/properties', p.id]);
  }

  viewProperty(p: Property) {
    this.router.navigate(['/properties', p.id]);
  }

  editProperty(p: Property) {
    this.openEditDialog(p);
  }

  deleteProperty(p: Property) {
    if (confirm(`Delete property "${p.name}"?`)) {
      this.api.delete(p.id).subscribe(() => this.load());
    }
  }

  openCreateDialog() {
    const dialogRef = this.dialog.open(PropertyFormComponent, {
      width: '600px',
      data: { mode: 'create' }
    });
    dialogRef.afterClosed().subscribe(result => { if (result) this.load(); });
  }

  openEditDialog(p: Property) {
    const dialogRef = this.dialog.open(PropertyFormComponent, {
      width: '600px',
      data: { mode: 'edit', property: p }
    });
    dialogRef.afterClosed().subscribe(result => { if (result) this.load(); });
  }
}