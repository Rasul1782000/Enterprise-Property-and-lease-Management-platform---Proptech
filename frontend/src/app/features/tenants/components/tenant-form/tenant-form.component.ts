import { Component, inject, signal, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import { TenantsApiService, Tenant, CreateTenantDto, UpdateTenantDto } from '../../services/tenants-api.service';

export interface TenantFormDialogData { mode: 'create' | 'edit'; tenant?: Tenant; }

@Component({
  selector: 'app-tenant-form',
  standalone: false,
  templateUrl: './tenant-form.component.html',
  styleUrls: ['./tenant-form.component.scss']
})
export class TenantFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(TenantsApiService);
  private messages = inject(MessageService);

  @Input() data: TenantFormDialogData = { mode: 'create' };
  @Output() closed = new EventEmitter<boolean>();

  loading = signal(false);

  statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Prospect', value: 'prospect' },
    { label: 'Former', value: 'former' }
  ];

  form: FormGroup = this.fb.group({
    code: ['', [Validators.required, Validators.maxLength(20)]],
    first_name: ['', [Validators.required, Validators.maxLength(50)]],
    last_name: ['', [Validators.required, Validators.maxLength(50)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(100)]],
    phone: ['', [Validators.required, Validators.maxLength(20)]],
    company: [''],
    tax_id: [''],
    status: ['active'],
    emergency_contact_name: [''],
    emergency_contact_phone: [''],
    notes: ['']
  });

  ngOnInit() { if (this.data.mode === 'edit' && this.data.tenant) this.form.patchValue(this.data.tenant); }

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);
    const dto = this.form.value;
    const req = this.data.mode === 'create' ? this.api.create(dto as CreateTenantDto) : this.api.update(this.data.tenant!.id, dto as UpdateTenantDto);
    req.subscribe({
      next: () => {
        this.loading.set(false);
        this.messages.add({ severity: 'success', summary: 'Saved', detail: this.data.mode === 'create' ? 'Tenant created' : 'Tenant updated' });
        this.closed.emit(true);
      },
      error: () => this.loading.set(false)
    });
  }
  onCancel() { this.closed.emit(false); }
}
