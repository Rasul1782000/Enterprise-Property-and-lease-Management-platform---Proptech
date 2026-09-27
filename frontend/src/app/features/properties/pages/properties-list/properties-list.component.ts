import { Component, OnInit, inject, signal, viewChild } from '@angular/core';
import { Router } from '@angular/router';
import { MessageService, ConfirmationService, MenuItem } from 'primeng/api';
import { PropertiesApiService, Property } from '../../services/properties-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { PropertyContextService } from '../../../../core/services/property-context.service';
import { FilterEvent } from '@shared/components/filter-bar/filter-bar.component';
import { PropertyFormDialogData } from '../../components/property-form/property-form.component';

interface TableSortEvent {
  field?: string | null;
  order?: number | null;
}

interface TablePageEvent {
  first: number;
  rows: number;
}

@Component({
  selector: 'app-properties-list',
  standalone: false,
  templateUrl: './properties-list.component.html',
  styleUrls: ['./properties-list.component.scss']
})
export class PropertiesListComponent implements OnInit {
  private api = inject(PropertiesApiService);
  private ctx = inject(PropertyContextService);
  private confirmation = inject(ConfirmationService);
  private messages = inject(MessageService);
  private router = inject(Router);

  private readonly rowMenu = viewChild<{ toggle: (event: Event) => void }>('rowMenu');

  cols = ['code', 'name', 'type', 'city', 'buildings_count', 'units_count', 'occupancy', 'status', 'actions'];
  properties = signal<Property[]>([]);
  total = signal(0);
  loading = signal(false);

  perPage = 15;
  page = 1;
  first = 0;
  sort = 'created_at';
  dir: 'asc' | 'desc' = 'desc';
  search = '';
  status = '';

  menuItems = signal<MenuItem[]>([]);

  formVisible = signal(false);
  formData = signal<PropertyFormDialogData>({ mode: 'create' });

  statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' }
  ];

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
    this.first = 0;
    this.load();
  }

  onSort(e: TableSortEvent) {
    this.sort = e.field || 'created_at';
    this.dir = e.order === 1 ? 'asc' : 'desc';
    this.page = 1;
    this.first = 0;
    this.load();
  }

  onPage(e: TablePageEvent) {
    this.first = e.first;
    this.perPage = e.rows;
    this.page = Math.floor(e.first / e.rows) + 1;
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
    this.confirmation.confirm({
      header: 'Delete property',
      message: `Delete property "${p.name}"?`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => {
        this.api.delete(p.id).subscribe({
          next: () => {
            this.messages.add({ severity: 'success', summary: 'Deleted', detail: p.name });
            this.load();
          }
        });
      }
    });
  }

  openCreateDialog() {
    this.formData.set({ mode: 'create' });
    this.formVisible.set(true);
  }

  openEditDialog(p: Property) {
    this.formData.set({ mode: 'edit', property: p });
    this.formVisible.set(true);
  }

  onFormClosed(result: boolean) {
    this.formVisible.set(false);
    if (result) this.load();
  }

  openRowMenu(row: Property, event: Event) {
    this.menuItems.set([
      { label: 'View', icon: 'pi pi-eye', command: () => this.viewProperty(row) },
      { label: 'Edit', icon: 'pi pi-pencil', command: () => this.editProperty(row) },
      { label: 'Select Property', icon: 'pi pi-check-circle', command: () => this.selectProperty(row) },
      { separator: true },
      { label: 'Delete', icon: 'pi pi-trash', command: () => this.deleteProperty(row) }
    ]);
    this.rowMenu()?.toggle(event);
  }

  statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    switch (status) {
      case 'active':
      case 'occupied':
      case 'paid':
        return 'success';
      case 'inactive':
      case 'former':
      case 'cancelled':
        return 'secondary';
      case 'under_maintenance':
      case 'pending':
      case 'partial':
      case 'draft':
      case 'prospect':
      case 'reserved':
        return 'warn';
      case 'overdue':
      case 'terminated':
      case 'vacant':
      case 'expired':
        return 'danger';
      default:
        return 'info';
    }
  }

  money(v: any): string {
    return '$' + Number(v || 0).toLocaleString();
  }

  dateOnly(v: any): string {
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  pct(v: any): string {
    return Number(v || 0).toFixed(1) + '%';
  }
}
