import { Injectable, signal, computed, effect } from '@angular/core';

/**
 * Signals-based global context (selected property, tenant session)
 * Alternative to NgRx: lightweight, fine-grained reactivity.
 * Swap to NgRx by providing same API via facade if needed.
 */
@Injectable({ providedIn: 'root' })
export class PropertyContextService {
  // selected property across routes
  selectedPropertyId = signal<number | null>(Number(localStorage.getItem('selectedPropertyId')) || null);
  selectedTenantId = signal<number | null>(null);
  // UI signals
  sidebarCollapsed = signal(false);
  // derived
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
