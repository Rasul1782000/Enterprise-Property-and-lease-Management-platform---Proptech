import { Component, OnInit, inject, signal, viewChild } from '@angular/core';
import { Router } from '@angular/router';
import { MessageService, ConfirmationService, MenuItem } from 'primeng/api';
import { BuildingsApiService, Building } from '../../services/buildings-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { BuildingFormDialogData } from '../../components/building-form/building-form.component';

interface TableSortEvent {
  field?: string | null;
  order?: number | null;
}

interface TablePageEvent {
  first: number;
  rows: number;
}

@Component({
  selector: 'app-buildings-list',
  standalone: false,
  templateUrl: './buildings-list.component.html',
  styleUrls: ['./buildings-list.component.scss']
})
export class BuildingsListComponent implements OnInit {
  private api = inject(BuildingsApiService);
  private confirmation = inject(ConfirmationService);
  private messages = inject(MessageService);
  private router = inject(Router);

  private readonly rowMenu = viewChild<{ toggle: (event: Event) => void }>('rowMenu');

  cols = ['code', 'name', 'property', 'floors', 'units_count', 'status', 'actions'];
  buildings = signal<Building[]>([]);
  total = signal(0);
  loading = signal(false);

  perPage = 15;
  page = 1;
  first = 0;
  sort = 'created_at';
  dir: 'asc' | 'desc' = 'desc';

  menuItems = signal<MenuItem[]>([]);

  formVisible = signal(false);
  formData = signal<BuildingFormDialogData>({ mode: 'create' });

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Building>) => { this.buildings.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
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

  editBuilding(b: Building) { this.openEditDialog(b); }

  deleteBuilding(b: Building) {
    this.confirmation.confirm({
      header: 'Delete building',
      message: `Delete building "${b.name}"?`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => {
        this.api.delete(b.id).subscribe({
          next: () => {
            this.messages.add({ severity: 'success', summary: 'Deleted', detail: b.name });
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

  openEditDialog(b: Building) {
    this.formData.set({ mode: 'edit', building: b });
    this.formVisible.set(true);
  }

  showBuildingInfo(b: Building) {
    if (!b) return;
    this.messages.add({
      severity: 'info',
      summary: `${b.code} — ${b.name}`,
      detail: `${b.address}, ${b.city} · Floors: ${b.floors} · Units: ${b.units_count} · Status: ${b.status} · Property: ${b.property?.name || 'N/A'}`,
      life: 8000
    });
  }

  openViewDialog(b: Building) {
    this.formData.set({ mode: 'view', building: b });
    this.formVisible.set(true);
  }

  ShowDetails(b: Building): void {
    if (b) {
      this.router.navigate(['/buildings', b.id]);
    } else {
      console.warn('no data is present');
    }
  }

  onFormClosed(result: boolean) {
    this.formVisible.set(false);
    if (result) this.load();
  }

  openRowMenu(row: Building, event: Event) {
    this.menuItems.set([
      { label: 'View', icon: 'pi pi-eye', command: () => this.ShowDetails(row) },
      { label: 'View Details', icon: 'pi pi-eye', command: () => this.openViewDialog(row) },
      { label: 'Quick Info', icon: 'pi pi-info-circle', command: () => this.showBuildingInfo(row) },
      { label: 'Edit', icon: 'pi pi-pencil', command: () => this.editBuilding(row) },
      { separator: true },
      { label: 'Delete', icon: 'pi pi-trash', command: () => this.deleteBuilding(row) }
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
