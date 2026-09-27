import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { EjariContract } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STATUSES: { value: EjariContract['status']; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'submitted', label: 'Submitted to Ejari' },
  { value: 'registered', label: 'Registered' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' }
];

const TYPES = ['All types', 'new', 'renewal', 'amendment', 'termination'];

@Component({
  selector: 'app-ejari-tawtheeq-management-console',
  imports: [SharedModule],
  templateUrl: './ejari-tawtheeq-management-console.html',
  styleUrl: './ejari-tawtheeq-management-console.scss'
})
export class EjariTawtheeqManagementConsole extends ModulePage<EjariContract> {
  readonly endpoint = 'ejari-contracts';
  protected override defaultSort = 'expiry_date';

  statuses = STATUSES;
  types = TYPES;
  typeFilter = 'All types';
  selected = signal<EjariContract | null>(null);

  /** Cases the gateway has thrown back at us, with the reason to work from. */
  failures = computed(() => this.rows().filter(r => r.status === 'rejected' || (r.status === 'submitted' && r.last_error)));

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    const registered = this.count('registered');
    const total = s.total ?? 0;
    return [
      this.card('Registrations', total, 'pi pi-verified', 'indigo', `${registered} with a certificate`),
      this.card('Clearance rate', `${total ? Math.round((registered / total) * 100) : 0}%`, 'pi pi-check-circle', 'emerald', 'Registered vs total'),
      this.card('Awaiting gateway', this.count('submitted') + this.count('draft'), 'pi pi-send', 'blue', 'Submitted or in draft'),
      this.card('Penalties', this.money(s.penalty_amount), 'pi pi-exclamation-triangle', 'red', `${this.count('rejected')} rejected`)
    ];
  });

  onTypeFilter(type: string): void {
    this.typeFilter = type;
    this.page = 1;
    this.filters = type === 'All types' ? {} : { ejari_type: type };
    this.load();
  }

  open(row: EjariContract): void {
    this.selected.set(row);
  }

  close(): void {
    this.selected.set(null);
  }

  submit(row: EjariContract): void {
    this.act(row, 'submit', `${row.code} submitted to the Ejari gateway.`);
  }

  register(row: EjariContract): void {
    this.confirmation.confirm({
      header: 'Issue Ejari certificate',
      message: `Mark ${row.code} as registered and issue certificate ${row.ejari_number ?? '(auto)'}?`,
      icon: 'pi pi-verified',
      acceptLabel: 'Register',
      rejectLabel: 'Cancel',
      accept: () => {
        this.act(row, 'register', `${row.code} is now registered.`);
        this.selected.set(null);
      }
    });
  }

  reject(row: EjariContract): void {
    this.act(row, 'reject', `${row.code} was rejected and a penalty applied.`);
  }

  resubmit(row: EjariContract): void {
    this.submit(row);
  }
}
