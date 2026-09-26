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
import { InvoicesApiService, Invoice, Payment } from '../../services/invoices-api.service';

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
  sendInvoice() { const i = this.invoice(); if (i) this.api.send(i.id).subscribe(() => this.load(i.id)); }
  downloadPdf() { const i = this.invoice(); if (i) this.api.generatePdf(i.id).subscribe(blob => { const url = URL.createObjectURL(blob); window.open(url); }); }
  recordPayment() { /* implement */ }

  getStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-slate-100','sent':'bg-blue-100 text-blue-700','paid':'bg-emerald-100 text-emerald-700','partial':'bg-amber-100 text-amber-700','overdue':'bg-red-100 text-red-700','cancelled':'bg-slate-100'}; return c[s] || 'bg-slate-100'; }
}