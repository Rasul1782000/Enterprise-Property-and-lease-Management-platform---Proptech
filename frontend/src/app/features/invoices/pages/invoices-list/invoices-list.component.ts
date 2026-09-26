import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { MatTableModule } from '@angular/material/table';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatSortModule, Sort } from '@angular/material/sort';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatMenuModule } from '@angular/material/menu';
import { MatCardModule } from '@angular/material/card';
import { InvoicesApiService, Invoice } from '../../services/invoices-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { InvoiceFormComponent } from '../../components/invoice-form/invoice-form.component';

@Component({
  selector: 'app-invoices-list',
  standalone: false,
  templateUrl: './invoices-list.component.html',
  styleUrls: ['./invoices-list.component.scss']
})
export class InvoicesListComponent implements OnInit {
  private api = inject(InvoicesApiService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  cols = ['code', 'lease', 'tenant', 'type', 'issue_date', 'due_date', 'amount', 'paid_amount', 'balance', 'status', 'actions'];
  invoices = signal<Invoice[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Invoice>) => { this.invoices.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: Sort) { this.sort = e.active; this.dir = e.direction || 'desc'; this.load(); }
  onPage(e: PageEvent) { this.page = e.pageIndex + 1; this.perPage = e.pageSize; this.load(); }

  getStatusClass(s: string) { const c: Record<string,string> = {'draft':'bg-slate-100','sent':'bg-blue-100 text-blue-700','paid':'bg-emerald-100 text-emerald-700','partial':'bg-amber-100 text-amber-700','overdue':'bg-red-100 text-red-700','cancelled':'bg-slate-100'}; return c[s] || 'bg-slate-100'; }

  sendInvoice(i: Invoice) { this.api.send(i.id).subscribe(() => this.load()); }
  recordPayment(i: Invoice) { this.openPaymentDialog(i); }
  downloadPdf(i: Invoice) { this.api.generatePdf(i.id).subscribe((blob: Blob) => { const url = URL.createObjectURL(blob); window.open(url); }); }
  deleteInvoice(i: Invoice) { if (confirm(`Delete invoice "${i.code}"?`)) this.api.delete(i.id).subscribe(() => this.load()); }

  openCreateDialog() { const ref = this.dialog.open(InvoiceFormComponent, { width: '700px', data: { mode: 'create' } }); ref.afterClosed().subscribe(r => r && this.load()); }
  openPaymentDialog(i: Invoice) { /* implement payment dialog */ }
}