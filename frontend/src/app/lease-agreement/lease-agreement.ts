import { Component, computed, signal } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { ModulePage } from '@core/services/module-page.base';
import { LeaseAgreement as LeaseAgreementRow } from '@core/services/operations-api.service';
import { StatCard } from '@shared/components/stat-cards/stat-cards.component';

const STATUSES: { value: LeaseAgreementRow['status']; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'sent', label: 'Sent to tenant' },
  { value: 'partially_signed', label: 'Partially signed' },
  { value: 'executed', label: 'Executed' },
  { value: 'expired', label: 'Expired' }
];

@Component({
  selector: 'app-lease-agreement',
  imports: [SharedModule],
  templateUrl: './lease-agreement.html',
  styleUrl: './lease-agreement.scss'
})
export class LeaseAgreement extends ModulePage<LeaseAgreementRow> {
  readonly endpoint = 'lease-agreements';
  protected override defaultSort = 'generated_on';

  statuses = STATUSES;
  selected = signal<LeaseAgreementRow | null>(null);

  signatureSlots(row: LeaseAgreementRow): { role: string; signed: boolean; detail: string }[] {
    return [
      { role: 'Landlord', signed: !!row.landlord_signed_on, detail: row.landlord_signed_on ? this.dateOnly(row.landlord_signed_on) : '' },
      { role: 'Witness', signed: !!row.witness_name, detail: row.witness_name ?? '' },
      { role: 'Tenant', signed: !!row.tenant_signed_on, detail: row.tenant_signed_on ? this.dateOnly(row.tenant_signed_on) : '' }
    ];
  }

  signatureProgress(row: LeaseAgreementRow): number {
    return Math.round((this.signatureSlots(row).filter(s => s.signed).length / 3) * 100);
  }

  cards = computed<StatCard[]>(() => {
    const s = this.stats();
    return [
      this.card('Agreements', s.total ?? 0, 'pi pi-file', 'indigo', `${this.count('executed')} executed`),
      this.card('Awaiting signature', this.count('sent') + this.count('partially_signed'), 'pi pi-pen-to-square', 'amber', 'Chase the counterparties'),
      this.card('Contracted rent', this.money(s.rent_amount), 'pi pi-dollar', 'blue', 'Annual base across live files'),
      this.card('Deposits held', this.money(s.security_deposit), 'pi pi-shield', 'emerald', 'Security deposits on file')
    ];
  });

  open(row: LeaseAgreementRow): void {
    this.selected.set(row);
  }

  close(): void {
    this.selected.set(null);
  }

  send(row: LeaseAgreementRow): void {
    this.act(row, 'send', `${row.code} was sent to ${row.tenant_name}.`);
  }

  countersign(row: LeaseAgreementRow): void {
    this.act(row, 'countersign', `${row.code} was countersigned and executed.`);
  }

  execute(row: LeaseAgreementRow): void {
    this.act(row, 'execute', `${row.code} is now fully executed.`);
  }

  download(row: LeaseAgreementRow): void {
    this.messages.add({
      severity: 'info',
      summary: 'Generating',
      detail: `${row.code} (v${row.version}, ${row.document_size_kb} KB) is being prepared.`
    });
  }
}
