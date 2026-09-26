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
import { PropertiesApiService, Property, CreatePropertyDto, UpdatePropertyDto } from '../../services/properties-api.service';

export interface PropertyFormDialogData {
  mode: 'create' | 'edit';
  property?: Property;
}

@Component({
  selector: 'app-property-form',
  standalone: false,
  templateUrl: './property-form.component.html',
  styleUrls: ['./property-form.component.scss']
})
export class PropertyFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(PropertiesApiService);
  private dialogRef = inject(MatDialogRef<PropertyFormComponent>);
  public data = inject(MAT_DIALOG_DATA) as PropertyFormDialogData;

  loading = signal(false);

  form: FormGroup = this.fb.group({
    code: ['', [Validators.required, Validators.maxLength(20)]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    type: ['commercial', Validators.required],
    address: ['', [Validators.required, Validators.maxLength(255)]],
    city: ['', [Validators.required, Validators.maxLength(100)]],
    state: ['', [Validators.required, Validators.maxLength(50)]],
    zip: ['', [Validators.required, Validators.maxLength(20)]],
    country: ['USA']
  });

  ngOnInit() {
    if (this.data.mode === 'edit' && this.data.property) {
      this.form.patchValue(this.data.property);
    }
  }

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);

    const dto = this.form.value;
    const request = this.data.mode === 'create'
      ? this.api.create(dto as CreatePropertyDto)
      : this.api.update(this.data.property!.id, dto as UpdatePropertyDto);

    request.subscribe({
      next: () => this.dialogRef.close(true),
      error: () => this.loading.set(false)
    });
  }

  onCancel() {
    this.dialogRef.close(false);
  }
}