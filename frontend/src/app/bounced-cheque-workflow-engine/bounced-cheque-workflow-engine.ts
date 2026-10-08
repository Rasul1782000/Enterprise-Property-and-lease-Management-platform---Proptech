import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { BouncedCheque } from '@core/services/operations-api.service';
import { TagSeverity } from '@core/services/module-page.base';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STAGES: { value: BouncedCheque['stage']; label: string }[] = [
  { value: 'new', label: 'New' },
  { value: 'notified', label: 'Notified' },
  { value: 'promise_to_pay', label: 'Promise to pay' },
  { value: 'partially_recovered', label: 'Partially recovered' },
  { value: 'recovered', label: 'Recovered' },
  { value: 'escalated', label: 'Escalated' },
  { value: 'legal_action', label: 'Legal action' },
  { value: 'written_off', label: 'Written off' }
];

const REASONS: { value: string; label: string }[] = [
  { value: 'insufficient_funds', label: 'Insufficient funds' },
  { value: 'account_closed', label: 'Account closed' },
  { value: 'signature_mismatch', label: 'Signature mismatch' },
  { value: 'stale_dated', label: 'Stale dated' },
  { value: 'stop_payment', label: 'Stop payment' },
  { value: 'other', label: 'Other' }
];

const PIPELINE: BouncedCheque['stage'][] = [
  'new', 'notified', 'promise_to_pay', 'partially_recovered', 'recovered', 'escalated', 'legal_action', 'written_off'
];

@Component({
  selector: 'app-bounced-cheque-workflow-engine',
  imports: [SharedModule],
  templateUrl: './bounced-cheque-workflow-engine.html',
  styleUrl: './bounced-cheque-workflow-engine.css'
})
export class BouncedChequeWorkflowEngine extends ModulePage<BouncedCheque> {
  readonly endpoint = 'bounced-cheques';
  protected override defaultSort = 'bounced_on';

  stages = STAGES;
  reasons = REASONS;
  pipeline = PIPELINE;
  selected = signal<BouncedCheque | null>(null);
  reasonFilter: string | null = null;

  onReasonFilter(reason: string | null): void {
    this.reasonFilter = reason;
    this.page = 1;
    this.filters = reason ? { bounce_reason: reason } : {};
    this.load();
  }

  prioritySeverity(priority: string): TagSeverity {
    switch (priority) {
      case 'critical': return 'danger';
      case 'high': return 'warn';
      case 'normal': return 'info';
      default: return 'secondary';
    }
  }

  urgent = computed(() =>
    this.rows()
      .filter(r => r.stage !== 'recovered' && r.stage !== 'written_off')
      .sort((a, b) => b.aging_days - a.aging_days)
      .slice(0, 6)
  );

  recoveryRate = computed(() => {
    const s = this.stats();
    const bounced = Number(s.amount || 0);
    return bounced ? Math.round((Number(s.recovered_amount || 0) / bounced) * 100) : 0;
  });

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    return [
      this.card('Open cases', this.count('new') + this.count('notified') + this.count('promise_to_pay') + this.count('partially_recovered') + this.count('escalated') + this.count('legal_action'), 'pi pi-exclamation-circle', 'indigo', `${s.total ?? 0} cases in total`),
      this.card('Bounced value', this.money(s.amount), 'pi pi-dollar', 'blue', `${this.recoveryRate()}% recovered`),
      this.card('Still outstanding', this.money(s.outstanding), 'pi pi-clock', 'amber', 'Not yet recovered'),
      this.card('Legal / escalated', this.count('legal_action') + this.count('escalated'), 'pi pi-gavel', 'red', 'Referred for action')
    ];
  });

  stageCount(stage: string): number {
    return this.count(stage);
  }

  open(row: BouncedCheque): void {
    this.selected.set(row);
  }

  close(): void {
    this.selected.set(null);
  }

  notify(row: BouncedCheque): void {
    this.act(row, 'notify', `Bounced-cheque notice issued to ${row.tenant_name}.`);
  }

  promise(row: BouncedCheque): void {
    this.act(row, 'promise', `Promise to pay recorded for ${row.code}.`);
  }

  recover(row: BouncedCheque): void {
    this.act(row, 'recover', `Recovery posted against ${row.code}.`, { amount: row.outstanding });
  }

  closeCase(row: BouncedCheque): void {
    this.confirmation.confirm({
      header: 'Close case',
      message: `Close ${row.code} as fully recovered (${this.money(row.amount)})?`,
      icon: 'pi pi-check-circle',
      acceptLabel: 'Close case',
      rejectLabel: 'Cancel',
      accept: () => this.act(row, 'close', `${row.code} was closed as recovered.`)
    });
  }

  escalate(row: BouncedCheque): void {
    this.act(row, 'escalate', `${row.code} escalated to credit control.`);
  }

  legal(row: BouncedCheque): void {
    this.confirmation.confirm({
      header: 'Refer to legal',
      message: `Refer ${row.code} to legal and issue a notice to pay? This is recorded on the case file.`,
      icon: 'pi pi-gavel',
      acceptLabel: 'Refer to legal',
      rejectLabel: 'Cancel',
      accept: () => this.act(row, 'legal', `${row.code} referred to legal.`)
    });
  }

  writeOff(row: BouncedCheque): void {
    this.confirmation.confirm({
      header: 'Write off',
      message: `Write off ${this.money(row.outstanding)} on ${row.code}? This cannot be reversed.`,
      icon: 'pi pi-ban',
      acceptLabel: 'Write off',
      rejectLabel: 'Cancel',
      accept: () => this.act(row, 'write-off', `${row.code} was written off.`)
    });
  }
}
