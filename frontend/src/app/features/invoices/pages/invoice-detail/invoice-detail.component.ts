import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { MessageService } from 'primeng/api';
import { InvoicesApiService, Invoice, Payment } from '../../services/invoices-api.service';

type TagSeverity = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast';

@Component({
  selector: 'app-invoice-detail',
  standalone: false,
  templateUrl: './invoice-detail.component.html',
  styleUrls: ['./invoice-detail.component.scss']
})
export class InvoiceDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private api = inject(InvoicesApiService);
  private messages = inject(MessageService);

  invoice = signal<Invoice | null>(null);
  payments = signal<Payment[]>([]);
  loading = signal(true);
  paymentsLoading = signal(false);
  lineItemCols = ['description', 'quantity', 'unit_price', 'amount', 'tax_rate', 'tax_amount'];
  paymentCols = ['payment_date', 'amount', 'payment_method', 'reference'];

  ngOnInit() { const id = Number(this.route.snapshot.paramMap.get('id')); this.load(id); }

  load(id: number) {
    this.loading.set(true);
    this.api.get(id).subscribe({ next: (i) => { this.invoice.set(i); this.loading.set(false); this.loadPayments(id); }, error: () => this.loading.set(false) });
  }

  loadPayments(invoiceId: number) {
    this.paymentsLoading.set(true);
    this.api.getPayments(invoiceId).subscribe({ next: (res) => { this.payments.set(res.data); this.paymentsLoading.set(false); }, error: () => this.paymentsLoading.set(false) });
  }

  editInvoice() { const i = this.invoice(); if (i) this.router.navigate(['/invoices', i.id, 'edit']); }
  sendInvoice() {
    const i = this.invoice();
    if (!i) return;
    this.api.send(i.id).subscribe({
      next: () => { this.messages.add({ severity: 'success', summary: 'Sent', detail: `Invoice ${i.code} was sent.` }); this.load(i.id); },
      error: () => this.messages.add({ severity: 'error', summary: 'Failed', detail: `Invoice ${i.code} could not be sent.` })
    });
  }
  downloadPdf() { const i = this.invoice(); if (i) this.api.generatePdf(i.id).subscribe(blob => { const url = URL.createObjectURL(blob); window.open(url); }); }
  recordPayment() { this.messages.add({ severity: 'info', summary: 'Record payment', detail: 'Payment recording is coming soon.' }); }

  getStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-zinc-100','sent':'bg-zinc-200 text-zinc-800','paid':'bg-primary-100 text-primary-800','partial':'bg-primary-50 text-primary-700','overdue':'bg-primary-600 text-white','cancelled':'bg-zinc-100'}; return c[s] || 'bg-zinc-100'; }

  statusSeverity(status: string): TagSeverity {
    switch (status) {
      case 'paid': case 'active': return 'success';
      case 'sent': return 'info';
      case 'partial': case 'pending': case 'draft': return 'warn';
      case 'overdue': return 'danger';
      case 'cancelled': return 'secondary';
      default: return 'info';
    }
  }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
  dateOnly(v: any): string { return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  isOverdue(row: any): boolean { return row.status !== 'paid' && row.status !== 'cancelled' && new Date(row.due_date).getTime() < Date.now(); }
}
