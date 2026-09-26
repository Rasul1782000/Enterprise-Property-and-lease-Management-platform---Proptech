import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
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
  private dialogRef = inject(MatDialogRef<TenantFormComponent>);
  public data = inject(MAT_DIALOG_DATA) as TenantFormDialogData;
  loading = signal(false);

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

  onSubmit() { if (this.form.invalid) return; this.loading.set(true); const dto = this.form.value; const req = this.data.mode === 'create' ? this.api.create(dto as CreateTenantDto) : this.api.update(this.data.tenant!.id, dto as UpdateTenantDto); req.subscribe({ next: () => this.dialogRef.close(true), error: () => this.loading.set(false) }); }
  onCancel() { this.dialogRef.close(false); }
}