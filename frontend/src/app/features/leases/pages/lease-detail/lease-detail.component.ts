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
import { LeasesApiService, Lease } from '../../services/leases-api.service';
import { InvoicesApiService, Invoice } from '../../../invoices/services/invoices-api.service';

@Component({
  selector: 'app-lease-detail',
  standalone: false,
  templateUrl: './lease-detail.component.html',
  styleUrls: ['./lease-detail.component.scss']
})
export class LeaseDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private api = inject(LeasesApiService);
  private invoicesApi = inject(InvoicesApiService);

  lease = signal<Lease | null>(null);
  invoices = signal<Invoice[]>([]);
  loading = signal(true);
  invoicesLoading = signal(false);
  invoiceCols = ['code', 'type', 'issue_date', 'due_date', 'amount', 'status', 'actions'];

  ngOnInit() { const id = Number(this.route.snapshot.paramMap.get('id')); this.load(id); }

  load(id: number) {
    this.loading.set(true);
    this.api.get(id).subscribe({ next: (l) => { this.lease.set(l); this.loading.set(false); this.loadInvoices(id); }, error: () => this.loading.set(false) });
  }

  loadInvoices(leaseId: number) {
    this.invoicesLoading.set(true);
    this.invoicesApi.getByLease(leaseId).subscribe({ next: (res) => { this.invoices.set(res.data); this.invoicesLoading.set(false); }, error: () => this.invoicesLoading.set(false) });
  }

  editLease() { const l = this.lease(); if (l) this.router.navigate(['/leases', l.id, 'edit']); }
  generateDocument() { const l = this.lease(); if (l) this.api.generateDocument(l.id, 'standard').subscribe(blob => { const url = URL.createObjectURL(blob); window.open(url); }); }

  getStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-slate-100','active':'bg-emerald-100 text-emerald-700','expired':'bg-red-100 text-red-700','terminated':'bg-slate-100','renewed':'bg-blue-100 text-blue-700'}; return c[s] || 'bg-slate-100'; }
  getInvoiceStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-slate-100','sent':'bg-blue-100 text-blue-700','paid':'bg-emerald-100 text-emerald-700','partial':'bg-amber-100 text-amber-700','overdue':'bg-red-100 text-red-700','cancelled':'bg-slate-100'}; return c[s] || 'bg-slate-100'; }
}