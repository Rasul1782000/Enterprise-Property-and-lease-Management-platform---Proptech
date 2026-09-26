import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatTabsModule } from '@angular/material/tabs';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
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

  getUnitStatusClass(status: string): string {
    const classes: Record<string, string> = {
      'vacant': 'bg-emerald-100 text-emerald-700',
      'occupied': 'bg-blue-100 text-blue-700',
      'reserved': 'bg-amber-100 text-amber-700',
      'under_maintenance': 'bg-slate-100 text-slate-700'
    };
    return classes[status] || 'bg-slate-100';
  }
}