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
  getLeaseStatusClass(s: string) { const c: Record<string,string> = {'active':'bg-emerald-100 text-emerald-700','draft':'bg-slate-100','expired':'bg-red-100 text-red-700','terminated':'bg-slate-100','renewed':'bg-blue-100 text-blue-700'}; return c[s] || 'bg-slate-100'; }
}