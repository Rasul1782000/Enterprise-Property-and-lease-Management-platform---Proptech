import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { ModuleStats, VaultAsset } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STATUSES: { value: VaultAsset['status']; label: string }[] = [
  { value: 'sealed', label: 'Sealed' },
  { value: 'active', label: 'Active' },
  { value: 'released', label: 'Released' },
  { value: 'expired', label: 'Expired' }
];

@Component({
  selector: 'app-vault-management',
  imports: [SharedModule],
  templateUrl: './vault-management.html',
  styleUrl: './vault-management.css'
})
export class VaultManagement extends ModulePage<VaultAsset> {
  readonly endpoint = 'vault-assets';
  protected override defaultSort = 'code';

  statuses = STATUSES;
  selected = signal<VaultAsset | null>(null);
  depositFilter = signal<string | null>(null);

  /** Safes currently holding at least one asset, for the "safe contents" panel. */
  safes = computed(() => {
    const map = new Map<string, { safe_id: string; safe_location: string; assets: VaultAsset[]; value: number }>();
    this.rows().forEach(a => {
      const entry = map.get(a.safe_id) ?? { safe_id: a.safe_id, safe_location: a.safe_location, assets: [], value: 0 };
      entry.assets.push(a);
      entry.value += Number(a.value || 0);
      map.set(a.safe_id, entry);
    });
    return [...map.values()].sort((x, y) => x.safe_id.localeCompare(y.safe_id));
  });

  cards = computed<StatCard[]>(() => {
    const s: ModuleStats = this.stats();
    return [
      this.card('Assets in vault', s.total ?? 0, 'pi pi-box', 'indigo', `${this.count('sealed')} sealed`),
      this.card('Sealed & held', this.count('sealed'), 'pi pi-lock', 'blue', 'Originals in the safe'),
      this.card('Released', this.count('released'), 'pi pi-unlock', 'emerald', 'Returned to the holder'),
      this.card('Expired / review', this.count('expired'), 'pi pi-exclamation-triangle', 'amber', 'Needs renewal or return')
    ];
  });

  open(row: VaultAsset): void {
    this.selected.set(row);
  }

  close(): void {
    this.selected.set(null);
  }

  release(row: VaultAsset): void {
    this.confirmation.confirm({
      header: 'Release asset',
      message: `Release "${row.title}" to ${row.holder}?`,
      icon: 'pi pi-unlock',
      acceptLabel: 'Release',
      rejectLabel: 'Cancel',
      accept: () => {
        this.act(row, 'release', `${row.code} was released.`);
        this.selected.set(null);
      }
    });
  }

  seal(row: VaultAsset): void {
    this.act(row, 'reseal', `${row.code} was returned to the safe.`);
  }

  activate(row: VaultAsset): void {
    this.act(row, 'activate', `${row.code} was taken out of storage.`);
  }
}
