import { Injectable, signal, computed, effect } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class PropertyContextService {
  selectedPropertyId = signal<number | null>(Number(localStorage.getItem('selectedPropertyId')) || null);
  selectedTenantId = signal<number | null>(null);
  sidebarCollapsed = signal(false);
  hasPropertySelected = computed(() => this.selectedPropertyId() !== null);

  constructor() {
    effect(() => {
      const id = this.selectedPropertyId();
      if (id) localStorage.setItem('selectedPropertyId', String(id));
      else localStorage.removeItem('selectedPropertyId');
    });
  }

  selectProperty(id:number | null) { this.selectedPropertyId.set(id); }
  selectTenant(id:number | null) { this.selectedTenantId.set(id); }
  toggleSidebar() { this.sidebarCollapsed.update(v=>!v); }
}
