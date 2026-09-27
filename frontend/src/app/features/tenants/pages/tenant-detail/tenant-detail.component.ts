import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TenantsApiService, Tenant } from '../../services/tenants-api.service';
import { LeasesApiService, Lease } from '../../../leases/services/leases-api.service';

@Component({
  selector: 'app-tenant-detail',
  standalone: false,
  templateUrl: './tenant-detail.component.html',
  styleUrls: ['./tenant-detail.component.scss']
})
export class TenantDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private api = inject(TenantsApiService);
  private leasesApi = inject(LeasesApiService);

  tenant = signal<Tenant | null>(null);
  leases = signal<Lease[]>([]);
  loading = signal(true);
  leasesLoading = signal(false);
  leaseCols = ['code', 'property', 'unit', 'type', 'start_date', 'end_date', 'status', 'rent', 'actions'];

  ngOnInit() { const id = Number(this.route.snapshot.paramMap.get('id')); this.load(id); }

  load(id: number) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (t) => { this.tenant.set(t); this.loading.set(false); this.loadLeases(id); },
      error: () => this.loading.set(false)
    });
  }

  loadLeases(tenantId: number) {
    this.leasesLoading.set(true);
    this.api.getLeases(tenantId).subscribe({ next: (res) => { this.leases.set(res.data); this.leasesLoading.set(false); }, error: () => this.leasesLoading.set(false) });
  }

  editTenant() { const t = this.tenant(); if (t) this.router.navigate(['/tenants', t.id, 'edit']); }
  viewLease(l: Lease) { this.router.navigate(['/leases', l.id]); }
  getLeaseStatusClass(s: string) { const c: Record<string,string> = {'active':'bg-primary-100 text-primary-800','draft':'bg-zinc-100','expired':'bg-primary-600 text-white','terminated':'bg-zinc-100','renewed':'bg-zinc-200 text-zinc-800'}; return c[s] || 'bg-zinc-100'; }

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
