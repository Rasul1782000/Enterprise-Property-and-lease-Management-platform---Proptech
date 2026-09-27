import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { MessageService } from 'primeng/api';
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
  private messages = inject(MessageService);
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

  stepIndex = signal(1);

  propertyOptions = computed(() => this.properties().map((p) => ({ label: `${p.name} (${p.code})`, value: p.id })));
  buildingOptions = computed(() => this.buildings().map((b) => ({ label: `${b.name} (${b.code})`, value: b.id })));
  unitOptions = computed(() => this.units().map((u) => ({ label: `${u.name} (${u.code}) - ${this.money(u.base_rent)}/mo`, value: u.id })));
  tenantOptions = computed(() => this.tenants().map((t) => ({ label: `${t.first_name} ${t.last_name} (${t.email})`, value: t.id })));

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

  onStepChange(index: number | undefined) { this.stepIndex.set(index ?? 1); }

  isInvalid(form: FormGroup, name: string): boolean {
    const control = form.get(name);
    return !!control && control.invalid && (control.touched || control.dirty);
  }

  next() {
    const form = this.stepIndex() === 1 ? this.step1Form : this.stepIndex() === 2 ? this.step2Form : this.stepIndex() === 3 ? this.step3Form : null;
    if (form && form.invalid) { form.markAllAsTouched(); return; }
    this.stepIndex.update(v => Math.min(v + 1, 4));
  }

  prev() { this.stepIndex.update(v => Math.max(v - 1, 1)); }

  getPropertyName() { return this.properties().find(p => p.id === this.step1Form.value.property_id!)?.name || '—'; }
  getBuildingName() { return this.buildings().find(b => b.id === this.step1Form.value.building_id!)?.name || '—'; }
  getUnitName() { return this.units().find(u => u.id === this.step1Form.value.unit_id!)?.name || '—'; }
  getTenantName() { const t = this.tenants().find(t => t.id === this.step2Form.value.tenant_id!); return t ? `${t.first_name} ${t.last_name}` : '—'; }

  statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      active: 'success', signed: 'success', paid: 'success', renewed: 'success',
      inactive: 'secondary', former: 'secondary', cancelled: 'secondary',
      draft: 'warn', pending: 'warn', partial: 'warn', reserved: 'warn', prospect: 'warn',
      overdue: 'danger', terminated: 'danger', vacant: 'danger', expired: 'danger'
    };
    return map[status] ?? 'info';
  }

  money(v: any): string { return '$' + Number(v || 0).toLocaleString(); }
  dateOnly(v: any): string { return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  daysUntil(v: any): number { return Math.ceil((new Date(v).getTime() - Date.now()) / 86400000); }

  onSubmit() {
    if (this.step1Form.invalid || this.step2Form.invalid || this.step3Form.invalid) return;
    this.submitting.set(true);
    const dto: CreateLeaseDto = { 
      ...this.step1Form.value as any, 
      ...this.step2Form.value as any, 
      ...this.step3Form.value as any 
    };
    this.leasesApi.create(dto).subscribe({
      next: () => { this.messages.add({ severity: 'success', summary: 'Created', detail: `Lease ${dto.code} was created.` }); this.router.navigate(['/leases']); },
      error: () => { this.submitting.set(false); this.messages.add({ severity: 'error', summary: 'Error', detail: 'Could not create the lease.' }); }
    });
  }
}
