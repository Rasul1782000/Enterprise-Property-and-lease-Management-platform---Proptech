import { Component, OnInit, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { MessageService, ConfirmationService } from 'primeng/api';
import { InvoicesApiService, Invoice } from '../../services/invoices-api.service';
import { PaginatedResponse } from '../../../../core/types';

type TagSeverity = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast';

interface PageEventLike { first: number; rows: number; }

@Component({
  selector: 'app-invoices-list',
  standalone: false,
  templateUrl: './invoices-list.component.html',
  styleUrls: ['./invoices-list.component.scss']
})
export class InvoicesListComponent implements OnInit {
  private api = inject(InvoicesApiService);
  private router = inject(Router);
  private messages = inject(MessageService);
  private confirmation = inject(ConfirmationService);

  cols = ['code', 'lease', 'tenant', 'type', 'issue_date', 'due_date', 'amount', 'paid_amount', 'balance', 'status', 'actions'];
  invoices = signal<Invoice[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';
  formVisible = signal(false);

  ngOnInit() { this.load(); }

  load() {
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Invoice>) => { this.invoices.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onLazyLoad(e: any) {
    if (e?.sortField) this.onSort(e);
    else this.onPage(e as PageEventLike);
  }

  onSort(e: any) { this.sort = e?.field ?? this.sort; this.dir = e?.order === 1 ? 'asc' : 'desc'; this.load(); }
  onPage(e: PageEventLike) { this.perPage = e.rows; this.page = Math.floor(e.first / e.rows) + 1; this.load(); }

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

  sendInvoice(i: Invoice) {
    this.api.send(i.id).subscribe({
      next: () => { this.messages.add({ severity: 'success', summary: 'Sent', detail: `Invoice ${i.code} was sent.` }); this.load(); },
      error: () => this.messages.add({ severity: 'error', summary: 'Failed', detail: `Invoice ${i.code} could not be sent.` })
    });
  }
  recordPayment(i: Invoice) { this.openPaymentDialog(i); }
  downloadPdf(i: Invoice) { this.api.generatePdf(i.id).subscribe((blob: Blob) => { const url = URL.createObjectURL(blob); window.open(url); }); }
  deleteInvoice(i: Invoice) {
    this.confirmation.confirm({
      header: 'Delete invoice',
      message: `Delete invoice "${i.code}"? This action cannot be undone.`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => {
        this.api.delete(i.id).subscribe({
          next: () => { this.messages.add({ severity: 'success', summary: 'Deleted', detail: `Invoice ${i.code} was deleted.` }); this.load(); },
          error: () => this.messages.add({ severity: 'error', summary: 'Failed', detail: `Invoice ${i.code} could not be deleted.` })
        });
      }
    });
  }

  viewInvoice(i: Invoice) { this.router.navigate(['/invoices', i.id]); }

  openCreateDialog() { this.formVisible.set(true); }
  openPaymentDialog(i: Invoice) { this.messages.add({ severity: 'info', summary: 'Record payment', detail: `Payment recording for ${i.code} is coming soon.` }); }
  onFormClosed(saved: boolean) { this.formVisible.set(false); if (saved) this.load(); }
}
