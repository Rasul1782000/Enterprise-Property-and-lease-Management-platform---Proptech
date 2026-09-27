import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
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
  viewLease(l: Lease) { this.router.navigate(['/leases', l.id]); }

  getStatusClass(s: string) { const c: Record<string,string> = {'vacant':'bg-primary-100 text-primary-800','occupied':'bg-zinc-200 text-zinc-800','reserved':'bg-primary-50 text-primary-700','under_maintenance':'bg-zinc-100'}; return c[s] || 'bg-zinc-100'; }
  getLeaseStatusClass(s: string) { const c: Record<string,string> = {'active':'bg-primary-100 text-primary-800','draft':'bg-zinc-100','expired':'bg-primary-600 text-white','terminated':'bg-zinc-100 text-zinc-600','renewed':'bg-zinc-200 text-zinc-800'}; return c[s] || 'bg-zinc-100'; }

  statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      active: 'success', occupied: 'success', paid: 'success',
      inactive: 'secondary', former: 'secondary', cancelled: 'secondary',
      under_maintenance: 'warn', pending: 'warn', partial: 'warn', draft: 'warn', prospect: 'warn', reserved: 'warn',
      overdue: 'danger', terminated: 'danger', vacant: 'danger', expired: 'danger'
    };
    return map[status] ?? 'info';
  }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
  dateOnly(v: any): string { return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  pct(v: any): string { return Number(v || 0).toFixed(1) + '%'; }
}
