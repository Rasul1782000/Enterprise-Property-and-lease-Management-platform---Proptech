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
import { BuildingsApiService, Building } from '../../../buildings/services/buildings-api.service';
import { PropertiesApiService, Property } from '../../../properties/services/properties-api.service';
import { UnitsApiService, Unit, CreateUnitDto, UpdateUnitDto } from '../../services/units-api.service';

export interface UnitFormDialogData { mode: 'create' | 'edit'; unit?: Unit; }

@Component({
  selector: 'app-unit-form',
  standalone: false,
  templateUrl: './unit-form.component.html',
  styleUrls: ['./unit-form.component.scss']
})
export class UnitFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private propertiesApi = inject(PropertiesApiService);
  private buildingsApi = inject(BuildingsApiService);
  private api = inject(UnitsApiService);
  private dialogRef = inject(MatDialogRef<UnitFormComponent>);
  public data = inject(MAT_DIALOG_DATA) as UnitFormDialogData;

  properties = signal<Property[]>([]);
  buildings = signal<Building[]>([]);
  loading = signal(false);

  form: FormGroup = this.fb.group({
    property_id: [null, Validators.required],
    building_id: [null, Validators.required],
    code: ['', [Validators.required, Validators.maxLength(20)]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    type: ['residential', Validators.required],
    floor: [0, [Validators.required, Validators.min(0), Validators.max(100)]],
    area_sqft: [0, [Validators.required, Validators.min(0)]],
    bedrooms: [0, [Validators.required, Validators.min(0), Validators.max(10)]],
    bathrooms: [0, [Validators.required, Validators.min(0), Validators.max(10)]],
    base_rent: [0, [Validators.required, Validators.min(0)]],
    status: ['vacant']
  });

  ngOnInit() {
    this.propertiesApi.list({ per_page: 1000 }).subscribe(res => this.properties.set(res.data));
    if (this.data.mode === 'edit' && this.data.unit) {
      this.form.patchValue(this.data.unit);
      this.onPropertyChange(this.data.unit.property_id);
    }
  }

  onPropertyChange(propertyId: number) {
    this.buildingsApi.getByProperty(propertyId, { per_page: 1000 }).subscribe(res => this.buildings.set(res.data));
    this.form.get('building_id')?.setValue(null);
  }

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);
    const dto = this.form.value;
    const req = this.data.mode === 'create' ? this.api.create(dto as CreateUnitDto) : this.api.update(this.data.unit!.id, dto as UpdateUnitDto);
    req.subscribe({ next: () => this.dialogRef.close(true), error: () => this.loading.set(false) });
  }
  onCancel() { this.dialogRef.close(false); }
}