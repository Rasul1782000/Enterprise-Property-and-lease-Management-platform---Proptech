import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ConfirmationService, MessageService } from 'primeng/api';
import {
  TenantsApiService,
  Tenant,
  TenantDocument,
  TENANT_DOCUMENT_CATEGORIES,
  TENANT_DOCUMENT_EXTENSIONS,
  TENANT_DOCUMENT_MAX_KB
} from '../../services/tenants-api.service';
import { LeasesApiService, Lease } from '../../../leases/services/leases-api.service';
import { PaginatedResponse } from '../../../../core/types';
import { PickedDocument } from '../../../../shared/components/document-upload-dialog/document-upload-dialog.component';

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
  private confirmation = inject(ConfirmationService);
  private messages = inject(MessageService);

  tenant = signal<Tenant | null>(null);
  leases = signal<Lease[]>([]);
  loading = signal(true);
  leasesLoading = signal(false);
  leaseCols = ['code', 'property', 'unit', 'type', 'start_date', 'end_date', 'status', 'rent', 'actions'];

  /* Documents */
  readonly documents = signal<TenantDocument[]>([]);
  readonly documentsLoading = signal(false);
  readonly documentsTotal = signal(0);
  readonly uploading = signal(false);
  readonly uploadVisible = signal(false);
  readonly categoryFilter = signal<string | null>(null);
  readonly documentCategories = TENANT_DOCUMENT_CATEGORIES;
  /** "All categories" sentinel prepended to the filter dropdown. */
  readonly categoryFilterOptions = [{ label: 'All categories', value: null }, ...TENANT_DOCUMENT_CATEGORIES];
  readonly allowedExtensions = TENANT_DOCUMENT_EXTENSIONS;
  readonly maxKb = TENANT_DOCUMENT_MAX_KB;

  ngOnInit() { const id = Number(this.route.snapshot.paramMap.get('id')); this.load(id); }

  load(id: number) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (t) => { this.tenant.set(t); this.loading.set(false); this.loadLeases(id); this.loadDocuments(id); },
      error: () => this.loading.set(false)
    });
  }

  loadLeases(tenantId: number) {
    this.leasesLoading.set(true);
    this.api.getLeases(tenantId).subscribe({ next: (res) => { this.leases.set(res.data); this.leasesLoading.set(false); }, error: () => this.leasesLoading.set(false) });
  }

  loadDocuments(tenantId: number) {
    this.documentsLoading.set(true);
    const category = this.categoryFilter();
    this.api.getDocuments(tenantId, { per_page: 100, sort: 'created_at', direction: 'desc', ...(category ? { 'filter[category]': category } : {}) })
      .subscribe({
        next: (res: PaginatedResponse<TenantDocument>) => { this.documents.set(res.data); this.documentsTotal.set(res.meta.total); this.documentsLoading.set(false); },
        error: () => this.documentsLoading.set(false)
      });
  }

  onCategoryFilter(category: string | null): void {
    this.categoryFilter.set(category);
    const id = this.tenant()?.id;
    if (id) this.loadDocuments(id);
  }

  /* ------------------------------------------------------------------ */
  /* Document upload                                                     */
  /* ------------------------------------------------------------------ */

  openUpload(): void {
    this.uploadVisible.set(true);
  }

  onUploadPicked(picked: PickedDocument): void {
    const tenant = this.tenant();
    if (!tenant) return;

    this.uploading.set(true);
    this.api.uploadDocument(tenant.id, picked.file, picked.name, picked.category).subscribe({
      next: () => {
        this.uploading.set(false);
        this.uploadVisible.set(false);
        this.messages.add({ severity: 'success', summary: 'Uploaded', detail: `"${picked.name}" was attached to this tenant.` });
        this.loadDocuments(tenant.id);
      },
      error: () => this.uploading.set(false)
    });
  }

  onUploadCancelled(): void {
    this.uploadVisible.set(false);
  }

  downloadDocument(doc: TenantDocument): void {
    const tenant = this.tenant();
    if (!tenant) return;

    // Prefer the presigned object-storage link the backend hands back; fall
    // back to proxying the bytes through the API when it is a local path.
    if (doc.url && /^https?:/i.test(doc.url)) {
      window.open(doc.url, '_blank', 'noopener');
      return;
    }

    this.api.downloadDocument(tenant.id, doc.id).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60_000);
      }
    });
  }

  deleteDocument(doc: TenantDocument): void {
    const tenant = this.tenant();
    if (!tenant) return;

    this.confirmation.confirm({
      header: 'Delete document',
      message: `Delete "${doc.name}"? This removes the file from storage permanently.`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => this.api.deleteDocument(tenant.id, doc.id).subscribe({
        next: () => {
          this.messages.add({ severity: 'success', summary: 'Deleted', detail: `"${doc.name}" was deleted.` });
          this.loadDocuments(tenant.id);
        }
      })
    });
  }

  /* ------------------------------------------------------------------ */

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

  /** Human label for a stored category key. */
  categoryLabel(value: string): string {
    return this.documentCategories.find(c => c.value === value)?.label ?? value;
  }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
  dateOnly(v: any): string { return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  pct(v: any): string { return Number(v || 0).toFixed(1) + '%'; }
}