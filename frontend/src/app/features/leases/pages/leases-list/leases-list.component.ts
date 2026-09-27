import { Component, OnInit, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService, MenuItem, MessageService } from 'primeng/api';
import { LeasesApiService, Lease } from '../../services/leases-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { LeaseFormDialogData } from '../../components/lease-form/lease-form.component';

interface PopupMenu { toggle(event: Event): void; }

@Component({
  selector: 'app-leases-list',
  standalone: false,
  templateUrl: './leases-list.component.html',
  styleUrls: ['./leases-list.component.scss']
})
export class LeasesListComponent implements OnInit {
  private api = inject(LeasesApiService);
  private messages = inject(MessageService);
  private confirmation = inject(ConfirmationService);
  private router = inject(Router);

  cols = ['code', 'property', 'unit', 'tenant', 'type', 'start_date', 'end_date', 'rent_amount', 'status', 'actions'];
  leases = signal<Lease[]>([]);
  total = signal(0);
  loading = signal(false);
  perPage = 15; page = 1; first = 0; sort = 'created_at'; dir: 'asc' | 'desc' = 'desc';

  menuItems: MenuItem[] = [];
  formVisible = signal(false);
  formData = signal<LeaseFormDialogData>({ mode: 'create' });

  ngOnInit() { this.load(); }

  load() {
    this.first = (this.page - 1) * this.perPage;
    this.loading.set(true);
    this.api.list({ per_page: this.perPage, page: this.page, sort: this.sort, direction: this.dir }).subscribe({
      next: (res: PaginatedResponse<Lease>) => { this.leases.set(res.data); this.total.set(res.meta.total); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  onSort(e: { field?: string | null; order?: number | null }) {
    this.sort = e.field || 'created_at';
    this.dir = (e.order ?? -1) === 1 ? 'asc' : 'desc';
    this.page = 1;
    this.load();
  }

  onPage(e: { first: number; rows: number }) { this.page = Math.floor(e.first / e.rows) + 1; this.perPage = e.rows; this.load(); }

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

  openRowMenu(lease: Lease, menu: PopupMenu, ev: Event) {
    const items: MenuItem[] = [
      { label: 'View', icon: 'pi pi-eye', routerLink: ['/leases', lease.id] },
      { label: 'Edit', icon: 'pi pi-pencil', command: () => this.editLease(lease) }
    ];
    if (lease.status === 'draft') items.push({ label: 'Sign', icon: 'pi pi-pencil', command: () => this.signLease(lease) });
    if (lease.status === 'active') items.push({ label: 'Terminate', icon: 'pi pi-times', command: () => this.terminateLease(lease) });
    this.menuItems = items;
    menu.toggle(ev);
  }

  editLease(l: Lease) { this.openEditDialog(l); }

  signLease(l: Lease) {
    this.confirmation.confirm({
      header: 'Sign lease',
      message: `Sign lease ${l.code}? This marks the lease as executed.`,
      icon: 'pi pi-pencil',
      acceptLabel: 'Sign',
      rejectLabel: 'Cancel',
      accept: () => this.api.sign(l.id).subscribe({
        next: () => { this.messages.add({ severity: 'success', summary: 'Signed', detail: `Lease ${l.code} was signed.` }); this.load(); },
        error: () => this.messages.add({ severity: 'error', summary: 'Error', detail: 'Could not sign the lease.' })
      })
    });
  }

  terminateLease(l: Lease) {
    const reason = prompt('Termination reason:');
    if (!reason) return;
    this.confirmation.confirm({
      header: 'Terminate lease',
      message: `Terminate lease ${l.code}? This cannot be undone.`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Terminate',
      rejectLabel: 'Cancel',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        const today = new Date().toISOString().split('T')[0];
        this.api.terminate(l.id, today, reason).subscribe({
          next: () => { this.messages.add({ severity: 'success', summary: 'Terminated', detail: `Lease ${l.code} was terminated.` }); this.load(); },
          error: () => this.messages.add({ severity: 'error', summary: 'Error', detail: 'Could not terminate the lease.' })
        });
      }
    });
  }

  openEditDialog(l: Lease) { this.formData.set({ mode: 'edit', lease: l }); this.formVisible.set(true); }

  onFormClosed(saved: boolean) {
    this.formVisible.set(false);
    if (saved) {
      this.messages.add({ severity: 'success', summary: 'Saved', detail: 'Lease was saved.' });
      this.load();
    }
  }
}
