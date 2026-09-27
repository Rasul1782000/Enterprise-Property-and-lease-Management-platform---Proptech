import { Component, EventEmitter, Input, Output, inject, signal, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { LeasesApiService, Lease, CreateLeaseDto, UpdateLeaseDto } from '../../services/leases-api.service';

export interface LeaseFormDialogData { mode: 'create' | 'edit'; lease?: Lease; }

@Component({
  selector: 'app-lease-form',
  standalone: false,
  templateUrl: './lease-form.component.html',
  styleUrls: ['./lease-form.component.scss']
})
export class LeaseFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(LeasesApiService);

  @Input() data: LeaseFormDialogData = { mode: 'create' };
  @Output() closed = new EventEmitter<boolean>();

  loading = signal(false);

  typeOptions = [
    { label: 'Fixed Term', value: 'fixed' },
    { label: 'Periodic', value: 'periodic' },
    { label: 'Commercial', value: 'commercial' },
    { label: 'Residential', value: 'residential' }
  ];

  frequencyOptions = [
    { label: 'Monthly', value: 'monthly' },
    { label: 'Quarterly', value: 'quarterly' },
    { label: 'Annually', value: 'annually' }
  ];

  form: FormGroup = this.fb.group({
    code: ['', Validators.required],
    type: ['fixed', Validators.required],
    start_date: [null, Validators.required],
    end_date: [null, Validators.required],
    rent_amount: [0, [Validators.required, Validators.min(0)]],
    deposit_amount: [0, [Validators.required, Validators.min(0)]],
    payment_frequency: ['monthly', Validators.required],
    escalation_clause: [''],
    renewal_options: [0],
    terms: ['']
  });

  ngOnInit() { if (this.data.mode === 'edit' && this.data.lease) { const l = this.data.lease; this.form.patchValue({ ...l, start_date: new Date(l.start_date), end_date: new Date(l.end_date) }); } }

  isInvalid(name: string): boolean {
    const control = this.form.get(name);
    return !!control && control.invalid && (control.touched || control.dirty);
  }

  onSubmit() { if (this.form.invalid) return; this.loading.set(true); const dto = this.form.value; const req = this.data.mode === 'create' ? this.api.create(dto as CreateLeaseDto) : this.api.update(this.data.lease!.id, dto as UpdateLeaseDto); req.subscribe({ next: () => this.closed.emit(true), error: () => this.loading.set(false) }); }
  onCancel() { this.closed.emit(false); }
}
