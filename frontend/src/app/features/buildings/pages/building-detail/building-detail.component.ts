import { Component, OnInit, inject, signal } from '@angular/core';
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

  getUnitStatusClass(s: string) { const c: Record<string,string> = {'vacant':'bg-emerald-100 text-emerald-700','occupied':'bg-blue-100 text-blue-700','reserved':'bg-amber-100 text-amber-700','under_maintenance':'bg-slate-100'}; return c[s] || 'bg-slate-100'; }
}