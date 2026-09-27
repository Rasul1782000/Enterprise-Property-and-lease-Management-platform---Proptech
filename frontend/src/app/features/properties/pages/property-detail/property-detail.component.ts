import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { PropertiesApiService, Property } from '../../services/properties-api.service';
import { BuildingsApiService, Building } from '../../../buildings/services/buildings-api.service';
import { UnitsApiService, Unit } from '../../../units/services/units-api.service';
import { PropertyContextService } from '../../../../core/services/property-context.service';

@Component({
  selector: 'app-property-detail',
  standalone: false,
  templateUrl: './property-detail.component.html',
  styleUrls: ['./property-detail.component.scss']
})
export class PropertyDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private propertyApi = inject(PropertiesApiService);
  private buildingsApi = inject(BuildingsApiService);
  private unitsApi = inject(UnitsApiService);
  private ctx = inject(PropertyContextService);

  property = signal<Property | null>(null);
  buildings = signal<Building[]>([]);
  units = signal<Unit[]>([]);
  loading = signal(true);
  buildingsLoading = signal(false);
  unitsLoading = signal(false);

  buildingCols = ['code', 'name', 'floors', 'units_count', 'status', 'actions'];
  unitCols = ['code', 'name', 'type', 'floor', 'base_rent', 'status', 'current_tenant', 'actions'];

  ngOnInit() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.loadProperty(id);
  }

  loadProperty(id: number) {
    this.loading.set(true);
    this.propertyApi.get(id).subscribe({
      next: (p) => {
        this.property.set(p);
        this.loading.set(false);
        this.loadBuildings(id);
        this.loadUnits(id);
      },
      error: () => this.loading.set(false)
    });
  }

  loadBuildings(propertyId: number) {
    this.buildingsLoading.set(true);
    this.buildingsApi.getByProperty(propertyId).subscribe({
      next: (res) => { this.buildings.set(res.data); this.buildingsLoading.set(false); },
      error: () => this.buildingsLoading.set(false)
    });
  }

  loadUnits(propertyId: number) {
    this.unitsLoading.set(true);
    this.unitsApi.getByProperty(propertyId).subscribe({
      next: (res) => { this.units.set(res.data); this.unitsLoading.set(false); },
      error: () => this.unitsLoading.set(false)
    });
  }

  selectProperty() {
    const p = this.property();
    if (p) this.ctx.selectProperty(p.id);
  }

  editProperty() {
    const p = this.property();
    if (p) this.router.navigate(['/properties', p.id, 'edit']);
  }

  goToBuildings() {
    const p = this.property();
    if (p) this.router.navigate(['/buildings'], { queryParams: { property_id: p.id } });
  }

  goToUnits() {
    const p = this.property();
    if (p) this.router.navigate(['/units'], { queryParams: { property_id: p.id } });
  }

  goToBuilding(id: number) {
    this.router.navigate(['/buildings', id]);
  }

  goToUnit(id: number) {
    this.router.navigate(['/units', id]);
  }

  getUnitStatusClass(status: string): string {
    const classes: Record<string, string> = {
      'vacant': 'bg-primary-100 text-primary-800',
      'occupied': 'bg-zinc-200 text-zinc-800',
      'reserved': 'bg-primary-50 text-primary-700',
      'under_maintenance': 'bg-zinc-100 text-zinc-600'
    };
    return classes[status] || 'bg-zinc-100';
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
