import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { LeaseRenewal } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STATUSES: { value: LeaseRenewal['status']; label: string }[] = [
  { value: 'draft', label: 'Draft offer' },
  { value: 'sent', label: 'Offer sent' },
  { value: 'under_negotiation', label: 'Under negotiation' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'declined', label: 'Declined' },
  { value: 'expired', label: 'Expired' }
];

@Component({
  selector: 'app-lease-renewal-engine',
  imports: [SharedModule],
  templateUrl: './lease-renewal-engine.html',
  styleUrl: './lease-renewal-engine.scss'
})
export class LeaseRenewalEngine extends ModulePage<LeaseRenewal> {
  readonly endpoint = 'lease-renewals';
  protected override defaultSort = 'expiry_date';

  statuses = STATUSES;
  selected = signal<LeaseRenewal | null>(null);
  horizon = signal(180);

  horizonOptions = [
    { label: 'Next 30 days', value: 30 },
    { label: 'Next 60 days', value: 60 },
    { label: 'Next 90 days', value: 90 },
    { label: 'Next 180 days', value: 180 },
    { label: 'Next 365 days', value: 365 }
  ];

  pipeline = computed(() =>
    this.rows()
      .filter(r => r.days_to_expiry <= this.horizon() && r.days_to_expiry > 0 && r.status !== 'accepted' && r.status !== 'declined')
      .sort((a, b) => a.days_to_expiry - b.days_to_expiry)
  );

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    const uplift = Number(s.proposed_rent ?? 0) - Number(s.current_rent ?? 0);
    return [
      this.card('Renewal offers', s.total ?? 0, 'pi pi-sync', 'indigo', `${this.count('sent')} awaiting a reply`),
      this.card('Acceptance rate', this.acceptanceRate() + '%', 'pi pi-thumbs-up', 'emerald', 'Of all offers raised'),
      this.card('Rent uplift captured', this.money(uplift), 'pi pi-arrow-up-right', 'blue', 'Proposed vs current rent'),
      this.card('Expiring within 45d', this.count('sent') + this.count('under_negotiation'), 'pi pi-hourglass', 'amber', 'Needs a decision')
    ];
  });

  acceptanceRate(): number {
    const decided = this.count('accepted') + this.count('declined');
    return decided ? Math.round((this.count('accepted') / decided) * 100) : 0;
  }

  setHorizon(days: number): void {
    this.horizon.set(days);
  }

  countWithin(days: number): number {
    return this.rows().filter(r => r.days_to_expiry > 0 && r.days_to_expiry <= days).length;
  }

  open(row: LeaseRenewal): void {
    this.selected.set(row);
  }

  close(): void {
    this.selected.set(null);
  }

  send(row: LeaseRenewal): void {
    this.act(row, 'send', `Renewal offer ${row.code} sent to ${row.tenant_name}.`);
  }

  accept(row: LeaseRenewal): void {
    this.confirmation.confirm({
      header: 'Accept renewal',
      message: `Accept ${row.tenant_name}'s renewal at ${this.money(row.proposed_rent)} for ${row.term_months} months?`,
      icon: 'pi pi-check-circle',
      acceptLabel: 'Accept',
      rejectLabel: 'Cancel',
      accept: () => {
        this.act(row, 'accept', `Renewal ${row.code} accepted.`);
        this.selected.set(null);
      }
    });
  }

  decline(row: LeaseRenewal): void {
    this.act(row, 'decline', `Renewal ${row.code} declined.`);
  }

  negotiate(row: LeaseRenewal): void {
    this.act(row, 'negotiate', `Renewal ${row.code} moved to negotiation.`);
  }
}
