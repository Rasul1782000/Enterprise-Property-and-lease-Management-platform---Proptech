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
import { UnitsApiService, Unit } from '../../services/units-api.service';
import { LeasesApiService, Lease } from '../../../leases/services/leases-api.service';

@Component({
  selector: 'app-unit-detail',
  standalone: false,
  templateUrl: './unit-detail.component.html',
  styleUrls: ['./unit-detail.component.scss']
})
export class UnitDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private api = inject(UnitsApiService);
  private leasesApi = inject(LeasesApiService);

  unit = signal<Unit | null>(null);
  leases = signal<Lease[]>([]);
  loading = signal(true);
  leasesLoading = signal(false);
  leaseCols = ['code', 'tenant', 'type', 'start_date', 'end_date', 'status', 'rent_amount', 'actions'];

  ngOnInit() { const id = Number(this.route.snapshot.paramMap.get('id')); this.load(id); }

  load(id: number) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (u) => { this.unit.set(u); this.loading.set(false); this.loadLeases(id); },
      error: () => this.loading.set(false)
    });
  }

  loadLeases(unitId: number) {
    this.leasesLoading.set(true);
    this.leasesApi.getByUnit(unitId).subscribe({
      next: (res) => { this.leases.set(res.data); this.leasesLoading.set(false); },
      error: () => this.leasesLoading.set(false)
    });
  }

  editUnit() { const u = this.unit(); if (u) this.router.navigate(['/units', u.id, 'edit']); }

  getStatusClass(s: string) { const c: Record<string,string> = {'vacant':'bg-emerald-100 text-emerald-700','occupied':'bg-blue-100 text-blue-700','reserved':'bg-amber-100 text-amber-700','under_maintenance':'bg-slate-100'}; return c[s] || 'bg-slate-100'; }
  getLeaseStatusClass(s: string) { const c: Record<string,string> = {'active':'bg-emerald-100 text-emerald-700','draft':'bg-slate-100','expired':'bg-red-100 text-red-700','terminated':'bg-slate-100 text-slate-700','renewed':'bg-blue-100 text-blue-700'}; return c[s] || 'bg-slate-100'; }
}