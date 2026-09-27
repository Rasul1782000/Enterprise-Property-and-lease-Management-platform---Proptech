import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { FitOutRequest } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const PERMIT_STATUSES: { value: FitOutRequest['permit_status']; label: string }[] = [
  { value: 'not_submitted', label: 'Not submitted' },
  { value: 'under_review', label: 'Under review' },
  { value: 'approved', label: 'Permit approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'expired', label: 'Permit expired' }
];

const PRIORITIES: { value: FitOutRequest['priority']; label: string; severity: 'danger' | 'warn' | 'secondary' }[] = [
  { value: 'high', label: 'High', severity: 'danger' },
  { value: 'normal', label: 'Normal', severity: 'warn' },
  { value: 'low', label: 'Low', severity: 'secondary' }
];

@Component({
  selector: 'app-fit-out-alterations-board-queue',
  imports: [SharedModule],
  templateUrl: './fit-out-alterations-board-queue.html',
  styleUrl: './fit-out-alterations-board-queue.scss'
})
export class FitOutAlterationsBoardQueue extends ModulePage<FitOutRequest> {
  readonly endpoint = 'fit-out-requests';
  protected override defaultSort = 'requested_on';

  permitStatuses = PERMIT_STATUSES;
  priorities = PRIORITIES;
  selected = signal<FitOutRequest | null>(null);

  /** Approved permits still waiting on a final NOC. */
  awaitingNoc = computed(() => this.rows().filter(r => r.permit_status === 'approved' && r.noc_status !== 'noc_issued'));

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    return [
      this.card('Requests', s.total ?? 0, 'pi pi-hammer', 'indigo', `${this.count('under_review')} in review`),
      this.card('Permits approved', this.count('approved'), 'pi pi-check-circle', 'emerald', `${this.count('noc_issued')} NOCs issued`),
      this.card('Pipeline value', this.money(s.estimated_cost), 'pi pi-dollar', 'blue', 'Estimated fit-out spend'),
      this.card('Rejected', this.count('rejected'), 'pi pi-ban', 'red', 'Awaiting resubmission')
    ];
  });

  prioritySeverity(priority: string): 'danger' | 'warn' | 'secondary' {
    return PRIORITIES.find(p => p.value === priority)?.severity ?? 'secondary';
  }

  open(row: FitOutRequest): void {
    this.selected.set(row);
  }

  close(): void {
    this.selected.set(null);
  }

  approve(row: FitOutRequest): void {
    this.confirmation.confirm({
      header: 'Approve permit',
      message: `Approve the fit-out permit for ${row.unit_code} (${row.contractor_name})? A ${this.money(Math.round(row.estimated_cost * 0.1))} deposit is requested.`,
      icon: 'pi pi-check-circle',
      acceptLabel: 'Approve',
      rejectLabel: 'Cancel',
      accept: () => this.act(row, 'approve', `Permit approved for ${row.code}.`)
    });
  }

  reject(row: FitOutRequest): void {
    this.act(row, 'reject', `Permit rejected for ${row.code}.`);
  }

  scheduleInspection(row: FitOutRequest): void {
    this.act(row, 'schedule-inspection', `Inspection scheduled for ${row.code}.`);
  }

  issueNoc(row: FitOutRequest): void {
    this.confirmation.confirm({
      header: 'Issue NOC',
      message: `Issue the final no-objection certificate for ${row.unit_code}? The deposit is refunded.`,
      icon: 'pi pi-verified',
      acceptLabel: 'Issue NOC',
      rejectLabel: 'Cancel',
      accept: () => this.act(row, 'issue-noc', `NOC issued for ${row.code}.`)
    });
  }
}
