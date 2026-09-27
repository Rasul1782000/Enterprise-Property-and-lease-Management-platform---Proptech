import { Component, inject, signal, output, input, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, FormArray, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import { LeasesApiService, Lease } from '../../../leases/services/leases-api.service';
import { InvoicesApiService, Invoice, CreateInvoiceDto, InvoiceLineItem } from '../../services/invoices-api.service';

export interface InvoiceFormDialogData { mode: 'create' | 'edit'; invoice?: Invoice; }

interface Option { label: string; value: any; }

@Component({
  selector: 'app-invoice-form',
  standalone: false,
  templateUrl: './invoice-form.component.html',
  styleUrls: ['./invoice-form.component.scss']
})
export class InvoiceFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private leasesApi = inject(LeasesApiService);
  private api = inject(InvoicesApiService);
  private messages = inject(MessageService);

  readonly mode = input<'create' | 'edit'>('create');
  readonly invoice = input<Invoice | undefined>(undefined);
  readonly closed = output<boolean>();

  loading = signal(false);

  leases = signal<Lease[]>([]);

  leaseOptions = signal<Option[]>([]);
  readonly typeOptions: Option[] = [
    { label: 'Rent', value: 'rent' },
    { label: 'Deposit', value: 'deposit' },
    { label: 'Late Fee', value: 'late_fee' },
    { label: 'Utility', value: 'utility' },
    { label: 'Maintenance', value: 'maintenance' },
    { label: 'Other', value: 'other' }
  ];

  get data(): InvoiceFormDialogData { return { mode: this.mode(), invoice: this.invoice() }; }

  form: FormGroup = this.fb.group({
    lease_id: [null, Validators.required],
    type: ['rent', Validators.required],
    issue_date: [new Date(), Validators.required],
    due_date: [new Date(Date.now() + 30*86400000), Validators.required],
    amount: [0],
    currency: ['USD'],
    description: [''],
    line_items: this.fb.array([])
  });

  get lineItems(): FormArray { return this.form.get('line_items') as FormArray; }

  ngOnInit() {
    this.leasesApi.getActive({ per_page: 1000 }).subscribe(res => {
      this.leases.set(res.data);
      this.leaseOptions.set(res.data.map(l => ({
        label: `${l.code} - ${l.tenant?.first_name} ${l.tenant?.last_name} ($${l.rent_amount}/mo)`,
        value: l.id
      })));
    });
    if (this.data.mode === 'edit' && this.data.invoice) {
      const inv = this.data.invoice;
      this.form.patchValue({ ...inv, issue_date: new Date(inv.issue_date), due_date: new Date(inv.due_date) });
      if (inv.line_items?.length) {
        inv.line_items.forEach(item => this.lineItems.push(this.createLineItemGroup(item)));
      }
    } else {
      this.addLineItem();
    }
    this.form.get('line_items')?.valueChanges.subscribe(() => this.updateTotal());
  }

  onLeaseChange(leaseId: number) {
    const lease = this.leases().find(l => l.id === leaseId);
    if (lease && this.data.mode === 'create') {
      this.form.patchValue({ amount: lease.rent_amount, type: 'rent' });
      this.lineItems.clear();
      this.lineItems.push(this.createLineItemGroup({ description: `Rent for ${new Date(this.form.value.issue_date).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}`, quantity: 1, unit_price: lease.rent_amount, tax_rate: 0, tax_amount: 0, amount: lease.rent_amount }));
    }
  }

  createLineItemGroup(item?: Partial<InvoiceLineItem>): FormGroup {
    return this.fb.group({
      description: [item?.description || ''],
      quantity: [item?.quantity || 1, [Validators.required, Validators.min(1)]],
      unit_price: [item?.unit_price || 0, [Validators.required, Validators.min(0)]],
      tax_rate: [item?.tax_rate || 0, [Validators.min(0), Validators.max(100)]],
      tax_amount: [item?.tax_amount || 0],
      amount: [item?.amount || 0]
    });
  }

  addLineItem() { this.lineItems.push(this.createLineItemGroup()); }
  removeLineItem(index: number) { this.lineItems.removeAt(index); }
  updateTotal() { const total = this.lineItems.controls.reduce((sum, c) => sum + (c.get('quantity')?.value || 0) * (c.get('unit_price')?.value || 0), 0); this.form.get('amount')?.setValue(total); }

  lineTotal(item: FormGroup): number { return (item.get('quantity')?.value || 0) * (item.get('unit_price')?.value || 0); }

  onSubmit() {
    if (this.form.invalid) { this.form.markAllAsTouched(); this.messages.add({ severity: 'error', summary: 'Invalid form', detail: 'Please review the highlighted fields.' }); return; }
    this.loading.set(true);
    const dto = this.form.value;
    const req = this.data.mode === 'create' ? this.api.create(dto as CreateInvoiceDto) : this.api.update(this.data.invoice!.id, dto);
    req.subscribe({
      next: () => { this.messages.add({ severity: 'success', summary: 'Saved', detail: this.data.mode === 'create' ? 'Invoice created.' : 'Invoice updated.' }); this.closed.emit(true); },
      error: () => { this.loading.set(false); this.messages.add({ severity: 'error', summary: 'Failed', detail: 'The invoice could not be saved.' }); }
    });
  }
  onCancel() { this.closed.emit(false); }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
}
