import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import {
  BuildingsApiService,
  Building,
  CreateBuildingDto,
  UpdateBuildingDto
} from '../../services/buildings-api.service';
import { PropertiesApiService, Property } from '../../../properties/services/properties-api.service';

export interface BuildingFormDialogData {
  mode: 'create' | 'edit' | 'view';
  building?: Building;
}

@Component({
  selector: 'app-building-form',
  standalone: false,
  templateUrl: './building-form.component.html',
  styleUrls: ['./building-form.component.scss']
})
export class BuildingFormComponent implements OnInit, OnChanges {
  private fb = inject(FormBuilder);
  private propertiesApi = inject(PropertiesApiService);
  private api = inject(BuildingsApiService);
  private messages = inject(MessageService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Input() data: BuildingFormDialogData = { mode: 'create' };
  @Output() closed = new EventEmitter<boolean>();

  properties = signal<Property[]>([]);
  loading = signal(false);

  propertyOptions = signal<{ label: string; value: number }[]>([]);

  statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Under Maintenance', value: 'under_maintenance' }
  ];

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

  get title(): string {
    return this.data.mode === 'create' ? 'Add Building' : this.data.mode === 'edit' ? 'Edit Building' : 'Building Details';
  }

  get submitLabel(): string {
    return this.data.mode === 'create' ? 'Create' : 'Update';
  }

  ngOnInit() {
    this.propertiesApi.list({ per_page: 1000 }).subscribe(res => {
      this.properties.set(res.data);
      this.propertyOptions.set(res.data.map(p => ({ label: `${p.name} (${p.code})`, value: p.id })));
    });
    this.patch();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['data'] && !changes['data'].firstChange) {
      this.form.reset({ property_id: null, floors: 1, status: 'active' });
      this.patch();
    }
  }

  private patch() {
    if (this.data.mode === 'edit' && this.data.building) {
      this.form.patchValue(this.data.building);
    }
  }

  isViewMode() {
    return this.data.mode === 'view';
  }

  onSubmit() {
    if (this.isViewMode()) {
      this.close(true);
      return;
    }

    if (this.form.invalid) return;
    this.loading.set(true);
    const dto = this.form.value;
    const req = this.data.mode === 'create'
      ? this.api.create(dto as CreateBuildingDto)
      : this.api.update(this.data.building!.id, dto as UpdateBuildingDto);
    req.subscribe({
      next: (saved) => {
        this.loading.set(false);
        this.messages.add({
          severity: 'success',
          summary: 'Saved',
          detail: `${saved.name} was ${this.data.mode === 'create' ? 'created' : 'updated'}.`
        });
        this.close(true);
      },
      error: () => this.loading.set(false)
    });
  }

  onCancel() { this.close(false); }

  private close(result: boolean) {
    this.closed.emit(result);
    this.visibleChange.emit(false);
  }

  statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    switch (status) {
      case 'active':
      case 'occupied':
      case 'paid':
        return 'success';
      case 'inactive':
      case 'former':
      case 'cancelled':
        return 'secondary';
      case 'under_maintenance':
      case 'pending':
      case 'partial':
      case 'draft':
      case 'prospect':
      case 'reserved':
        return 'warn';
      case 'overdue':
      case 'terminated':
      case 'vacant':
      case 'expired':
        return 'danger';
      default:
        return 'info';
    }
  }

  money(v: any): string {
    return '$' + Number(v || 0).toLocaleString();
  }

  dateOnly(v: any): string {
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  pct(v: any): string {
    return Number(v || 0).toFixed(1) + '%';
  }
}
