import { Component, inject, signal, computed, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
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
  private messages = inject(MessageService);

  @Input() data: UnitFormDialogData = { mode: 'create' };
  @Output() closed = new EventEmitter<boolean>();

  properties = signal<Property[]>([]);
  buildings = signal<Building[]>([]);
  loading = signal(false);

  propertyOptions = computed(() => this.properties().map(p => ({ label: `${p.name} (${p.code})`, value: p.id })));
  buildingOptions = computed(() => this.buildings().map(b => ({ label: `${b.name} (${b.code})`, value: b.id })));
  typeOptions = [
    { label: 'Residential', value: 'residential' },
    { label: 'Commercial', value: 'commercial' },
    { label: 'Office', value: 'office' },
    { label: 'Retail', value: 'retail' },
    { label: 'Warehouse', value: 'warehouse' }
  ];
  statusOptions = [
    { label: 'Vacant', value: 'vacant' },
    { label: 'Occupied', value: 'occupied' },
    { label: 'Reserved', value: 'reserved' },
    { label: 'Under Maintenance', value: 'under_maintenance' }
  ];

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
      this.loadBuildings(this.data.unit.property_id);
    }
  }

  onPropertyChange(propertyId: number) {
    this.loadBuildings(propertyId);
    this.form.get('building_id')?.setValue(null);
  }

  private loadBuildings(propertyId: number) {
    this.buildingsApi.getByProperty(propertyId, { per_page: 1000 }).subscribe(res => this.buildings.set(res.data));
  }

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);
    const dto = this.form.value;
    const req = this.data.mode === 'create' ? this.api.create(dto as CreateUnitDto) : this.api.update(this.data.unit!.id, dto as UpdateUnitDto);
    req.subscribe({
      next: () => {
        this.loading.set(false);
        this.messages.add({ severity: 'success', summary: 'Saved', detail: this.data.mode === 'create' ? 'Unit created' : 'Unit updated' });
        this.closed.emit(true);
      },
      error: () => this.loading.set(false)
    });
  }
  onCancel() { this.closed.emit(false); }
}
