import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BuildingsApiService, Building } from '../../services/buildings-api.service';
import { UnitsApiService, Unit } from '../../../units/services/units-api.service';

@Component({
  selector: 'app-building-detail',
  standalone: false,
  templateUrl: './building-detail.component.html',
  styleUrls: ['./building-detail.component.scss']
})
export class BuildingDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private api = inject(BuildingsApiService);
  private unitsApi = inject(UnitsApiService);

  building = signal<Building | null>(null);
  units = signal<Unit[]>([]);
  loading = signal(true);
  unitsLoading = signal(false);
  unitCols = ['code', 'name', 'type', 'floor', 'base_rent', 'status', 'actions'];

  ngOnInit() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.load(id);
  }

  load(id: number) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (b) => { this.building.set(b); this.loading.set(false); this.loadUnits(id); },
      error: () => this.loading.set(false)
    });
  }

  loadUnits(buildingId: number) {
    this.unitsLoading.set(true);
    this.unitsApi.getByBuilding(buildingId).subscribe({
      next: (res) => { this.units.set(res.data); this.unitsLoading.set(false); },
      error: () => this.unitsLoading.set(false)
    });
  }

  editBuilding() { const b = this.building(); if (b) this.router.navigate(['/buildings', b.id, 'edit']); }

  goToUnits() {
    const b = this.building();
    if (b) this.router.navigate(['/units'], { queryParams: { building_id: b.id } });
  }

  goToUnit(id: number) {
    this.router.navigate(['/units', id]);
  }

  getUnitStatusClass(s: string) { const c: Record<string,string> = {'vacant':'bg-primary-100 text-primary-800','occupied':'bg-zinc-200 text-zinc-800','reserved':'bg-primary-50 text-primary-700','under_maintenance':'bg-zinc-100'}; return c[s] || 'bg-zinc-100'; }

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
