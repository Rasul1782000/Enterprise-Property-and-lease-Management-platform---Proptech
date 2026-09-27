import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { OperationsApiService, EscrowAccount, ServiceCharge } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STATUSES: { value: ServiceCharge['status']; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'issued', label: 'Issued' },
  { value: 'partially_paid', label: 'Partially paid' },
  { value: 'paid', label: 'Paid' },
  { value: 'closed', label: 'Closed' }
];

@Component({
  selector: 'app-service-charge-and-escrow-management',
  imports: [SharedModule],
  templateUrl: './service-charge-and-escrow-management.html',
  styleUrl: './service-charge-and-escrow-management.css'
})
export class ServiceChargeAndEscrowManagement extends ModulePage<ServiceCharge> implements OnInit {
  readonly endpoint = 'service-charges';
  protected override defaultSort = 'code';

  private readonly ops = inject(OperationsApiService);

  statuses = STATUSES;
  activeTab: 'charges' | 'escrow' = 'charges';
  escrow = signal<EscrowAccount[]>([]);
  escrowLoading = signal(false);

  override ngOnInit(): void {
    super.ngOnInit();
    this.loadEscrow();
  }

  /** Budget vs actual across the whole portfolio, for the variance banner. */
  variancePct = computed(() => {
    const s = this.stats();
    const budgeted = Number(s.budgeted_amount || 0);
    if (!budgeted) return 0;
    return Math.round(((Number(s.actual_amount || 0) - budgeted) / budgeted) * 1000) / 10;
  });

  collectionRate = computed(() => {
    const s = this.stats();
    const actual = Number(s.actual_amount || 0);
    return actual ? Math.round((Number(s.amount_collected || 0) / actual) * 100) : 0;
  });

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    return [
      this.card('Budgeted', this.money(s.budgeted_amount), 'pi pi-wallet', 'indigo', `${s.total ?? 0} charge lines`),
      this.card('Actual spend', this.money(s.actual_amount), 'pi pi-chart-line', 'blue', `${this.variancePct() > 0 ? '+' : ''}${this.variancePct()}% vs budget`),
      this.card('Collected', this.money(s.amount_collected), 'pi pi-check-circle', 'emerald', `${this.collectionRate()}% recovery`),
      this.card('Outstanding', this.money(s.outstanding), 'pi pi-exclamation-triangle', 'red', `${this.count('issued')} lines unpaid`)
    ];
  });

  loadEscrow(): void {
    this.escrowLoading.set(true);
    this.ops.escrowAccounts({ per_page: 50 }).subscribe({
      next: res => {
        this.escrow.set(res.data);
        this.escrowLoading.set(false);
      },
      error: () => this.escrowLoading.set(false)
    });
  }

  escrowTotal(): number {
    return this.escrow().reduce((sum, a) => sum + Number(a.closing_balance || 0), 0);
  }

  issue(row: ServiceCharge): void {
    this.act(row, 'issue', `${row.code} was issued to owners.`);
  }

  collect(row: ServiceCharge): void {
    this.messages.add({
      severity: 'info',
      summary: 'Record collection',
      detail: `Posting a receipt against ${row.code} — ${this.money(row.outstanding)} outstanding.`
    });
  }

  closeCharge(row: ServiceCharge): void {
    this.act(row, 'close', `${row.code} was closed out.`);
  }

  freeze(account: EscrowAccount): void {
    this.ops.run<EscrowAccount>('escrow-accounts', account.id, 'freeze').subscribe({
      next: () => {
        this.messages.add({ severity: 'success', summary: 'Frozen', detail: `${account.code} is now frozen.` });
        this.loadEscrow();
      },
      error: () => this.messages.add({ severity: 'error', summary: 'Action failed', detail: 'Could not freeze the account.' })
    });
  }

  unfreeze(account: EscrowAccount): void {
    this.ops.run<EscrowAccount>('escrow-accounts', account.id, 'unfreeze').subscribe({
      next: () => {
        this.messages.add({ severity: 'success', summary: 'Unfrozen', detail: `${account.code} is active again.` });
        this.loadEscrow();
      },
      error: () => this.messages.add({ severity: 'error', summary: 'Action failed', detail: 'Could not unfreeze the account.' })
    });
  }

  reconcile(account: EscrowAccount): void {
    this.ops.run<EscrowAccount>('escrow-accounts', account.id, 'reconcile').subscribe({
      next: () => {
        this.messages.add({ severity: 'success', summary: 'Reconciled', detail: `${account.code} reconciled to today.` });
        this.loadEscrow();
      },
      error: () => this.messages.add({ severity: 'error', summary: 'Action failed', detail: 'Could not reconcile the account.' })
    });
  }
}
