import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
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
  private dialogRef = inject(MatDialogRef<LeaseFormComponent>);
  public data = inject(MAT_DIALOG_DATA) as LeaseFormDialogData;
  loading = signal(false);

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

  onSubmit() { if (this.form.invalid) return; this.loading.set(true); const dto = this.form.value; const req = this.data.mode === 'create' ? this.api.create(dto as CreateLeaseDto) : this.api.update(this.data.lease!.id, dto as UpdateLeaseDto); req.subscribe({ next: () => this.dialogRef.close(true), error: () => this.loading.set(false) }); }
  onCancel() { this.dialogRef.close(false); }
}