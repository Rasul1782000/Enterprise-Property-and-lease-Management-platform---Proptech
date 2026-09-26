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
import { PropertiesApiService, Property } from '../../../properties/services/properties-api.service';
import { BuildingsApiService, Building, CreateBuildingDto, UpdateBuildingDto } from '../../services/buildings-api.service';

export interface BuildingFormDialogData {
  mode: 'create' | 'edit';
  building?: Building;
}

@Component({
  selector: 'app-building-form',
  standalone: false,
  templateUrl: './building-form.component.html',
  styleUrls: ['./building-form.component.scss']
})
export class BuildingFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private propertiesApi = inject(PropertiesApiService);
  private api = inject(BuildingsApiService);
  private dialogRef = inject(MatDialogRef<BuildingFormComponent>);
  public data = inject(MAT_DIALOG_DATA) as BuildingFormDialogData;

  properties = signal<Property[]>([]);
  loading = signal(false);

  form: FormGroup = this.fb.group({
    property_id: [null, Validators.required],
    code: ['', [Validators.required, Validators.maxLength(20)]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    address: ['', [Validators.required, Validators.maxLength(255)]],
    city: ['', [Validators.required, Validators.maxLength(100)]],
    state: ['', [Validators.required, Validators.maxLength(50)]],
    zip: ['', [Validators.required, Validators.maxLength(20)]],
    floors: [1, [Validators.required, Validators.min(1), Validators.max(100)]],
    status: ['active']
  });

  ngOnInit() {
    this.propertiesApi.list({ per_page: 1000 }).subscribe(res => this.properties.set(res.data));
    if (this.data.mode === 'edit' && this.data.building) {
      this.form.patchValue(this.data.building);
    }
  }

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);
    const dto = this.form.value;
    const req = this.data.mode === 'create'
      ? this.api.create(dto as CreateBuildingDto)
      : this.api.update(this.data.building!.id, dto as UpdateBuildingDto);
    req.subscribe({ next: () => this.dialogRef.close(true), error: () => this.loading.set(false) });
  }

  onCancel() { this.dialogRef.close(false); }
}