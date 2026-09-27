import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { MessageService } from 'primeng/api';
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
  private messages = inject(MessageService);

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

  generateDocument() {
    const l = this.lease();
    if (!l) return;
    this.api.generateDocument(l.id, 'standard').subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        window.open(url);
        this.messages.add({ severity: 'success', summary: 'Document', detail: 'Lease PDF generated.' });
      },
      error: () => this.messages.add({ severity: 'error', summary: 'Error', detail: 'Could not generate the PDF.' })
    });
  }

  statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      active: 'success', signed: 'success', paid: 'success', renewed: 'success',
      inactive: 'secondary', former: 'secondary', cancelled: 'secondary',
      draft: 'warn', pending: 'warn', partial: 'warn', reserved: 'warn', prospect: 'warn',
      overdue: 'danger', terminated: 'danger', vacant: 'danger', expired: 'danger'
    };
    return map[status] ?? 'info';
  }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
  dateOnly(v: any): string { return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  daysUntil(v: any): number { return Math.ceil((new Date(v).getTime() - Date.now()) / 86400000); }

  getStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-zinc-100','active':'bg-primary-100 text-primary-800','expired':'bg-primary-600 text-white','terminated':'bg-zinc-100','renewed':'bg-zinc-200 text-zinc-800'}; return c[s] || 'bg-zinc-100'; }
  getInvoiceStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-zinc-100','sent':'bg-zinc-200 text-zinc-800','paid':'bg-primary-100 text-primary-800','partial':'bg-primary-50 text-primary-700','overdue':'bg-primary-600 text-white','cancelled':'bg-zinc-100'}; return c[s] || 'bg-zinc-100'; }
}
