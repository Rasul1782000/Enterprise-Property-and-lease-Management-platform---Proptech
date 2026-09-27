import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { PostDatedCheque } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STATUSES: { value: PostDatedCheque['status']; label: string }[] = [
  { value: 'pending', label: 'Awaiting due date' },
  { value: 'deposited', label: 'Deposited' },
  { value: 'cleared', label: 'Cleared' },
  { value: 'returned', label: 'Returned' },
  { value: 'replaced', label: 'Replaced' }
];

@Component({
  selector: 'app-post-date-cheque-vault',
  imports: [SharedModule],
  templateUrl: './post-date-cheque-vault.html',
  styleUrl: './post-date-cheque-vault.css'
})
export class PostDateChequeVault extends ModulePage<PostDatedCheque> {
  readonly endpoint = 'post-dated-cheques';
  protected override defaultSort = 'due_on';

  statuses = STATUSES;
  onlyOverdue = signal(false);

  /** Cheques whose due date has passed but which have not been cleared or replaced. */
  overdue = computed(() => this.rows().filter(c => this.isOverdue(c.due_on) && c.status !== 'cleared' && c.status !== 'replaced'));

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    return [
      this.card('Instruments held', s.total ?? 0, 'pi pi-wallet', 'indigo', `${this.count('pending')} awaiting clearance`),
      this.card('Face value', this.money(s.amount), 'pi pi-dollar', 'blue', 'Total cheque value on file'),
      this.card('Cleared', this.count('cleared'), 'pi pi-check-circle', 'emerald', 'Presented and honoured'),
      this.card('Returned', this.count('returned'), 'pi pi-exclamation-triangle', 'red', 'Linked to a bounce case')
    ];
  });

  toggleOverdue(): void {
    this.onlyOverdue.set(!this.onlyOverdue());
    this.onStatusFilter(this.onlyOverdue() ? 'returned' : null);
  }

  deposit(row: PostDatedCheque): void {
    this.act(row, 'deposit', `Cheque ${row.cheque_no} was presented for deposit.`);
  }

  clear(row: PostDatedCheque): void {
    this.act(row, 'clear', `Cheque ${row.cheque_no} cleared.`);
  }

  bounce(row: PostDatedCheque): void {
    this.confirmation.confirm({
      header: 'Mark as returned',
      message: `Return cheque ${row.cheque_no}? A bounce case should be raised in the recovery workflow.`,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Return cheque',
      rejectLabel: 'Cancel',
      accept: () => this.act(row, 'return', `Cheque ${row.cheque_no} was returned unpaid.`)
    });
  }

  replace(row: PostDatedCheque): void {
    this.act(row, 'replace', `Cheque ${row.cheque_no} was replaced.`);
  }
}
