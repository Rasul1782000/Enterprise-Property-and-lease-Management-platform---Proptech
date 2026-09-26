import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { PropertiesApiService, Property } from '../../../properties/services/properties-api.service';
import { BuildingsApiService, Building } from '../../../buildings/services/buildings-api.service';
import { UnitsApiService, Unit } from '../../../units/services/units-api.service';
import { TenantsApiService, Tenant } from '../../../tenants/services/tenants-api.service';
import { LeasesApiService, CreateLeaseDto } from '../../services/leases-api.service';

@Component({
  selector: 'app-lease-wizard',
  standalone: false,
  templateUrl: './lease-wizard.component.html',
  styleUrls: ['./lease-wizard.component.scss']
})
export class LeaseWizardComponent implements OnInit {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private propertiesApi = inject(PropertiesApiService);
  private buildingsApi = inject(BuildingsApiService);
  private unitsApi = inject(UnitsApiService);
  private tenantsApi = inject(TenantsApiService);
  private leasesApi = inject(LeasesApiService);

  properties = signal<Property[]>([]);
  buildings = signal<Building[]>([]);
  units = signal<Unit[]>([]);
  tenants = signal<Tenant[]>([]);
  submitting = signal(false);

  step1Form = this.fb.group({ property_id: [null, Validators.required], building_id: [null, Validators.required], unit_id: [null, Validators.required] });
  step2Form = this.fb.group({ tenant_id: [null, Validators.required] });
  step3Form = this.fb.group({
    code: ['', Validators.required], type: ['fixed', Validators.required],
    start_date: [null, Validators.required], end_date: [null, Validators.required],
    rent_amount: [0, [Validators.required, Validators.min(0)]], deposit_amount: [0, [Validators.required, Validators.min(0)]],
    payment_frequency: ['monthly', Validators.required], escalation_clause: [''], renewal_options: [0], terms: ['']
  });

  ngOnInit() { this.propertiesApi.list({ per_page: 1000 }).subscribe(res => this.properties.set(res.data)); this.tenantsApi.list({ per_page: 1000 }).subscribe(res => this.tenants.set(res.data)); }

  onPropertyChange(propertyId: number) {
    this.buildingsApi.getByProperty(propertyId, { per_page: 1000 }).subscribe(res => { this.buildings.set(res.data); this.step1Form.get('building_id')?.setValue(null); this.units.set([]); });
    this.step1Form.get('building_id')?.valueChanges.subscribe(bid => { if (bid) this.unitsApi.getByBuilding(bid, { per_page: 1000 }).subscribe(res => this.units.set(res.data)); });
  }

getPropertyName() { return this.properties().find(p => p.id === this.step1Form.value.property_id!)?.name || '—'; }
  getBuildingName() { return this.buildings().find(b => b.id === this.step1Form.value.building_id!)?.name || '—'; }
  getUnitName() { return this.units().find(u => u.id === this.step1Form.value.unit_id!)?.name || '—'; }
  getTenantName() { const t = this.tenants().find(t => t.id === this.step2Form.value.tenant_id!); return t ? `${t.first_name} ${t.last_name}` : '—'; }

  onSubmit() {
    if (this.step1Form.invalid || this.step2Form.invalid || this.step3Form.invalid) return;
    this.submitting.set(true);
    const dto: CreateLeaseDto = { 
      ...this.step1Form.value as any, 
      ...this.step2Form.value as any, 
      ...this.step3Form.value as any 
    };
    this.leasesApi.create(dto).subscribe({ next: () => this.router.navigate(['/leases']), error: () => this.submitting.set(false) });
  }
}