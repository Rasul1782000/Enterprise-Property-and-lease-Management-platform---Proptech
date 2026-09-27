/**
 * Seed data for the operational modules that sit alongside the core
 * Property -> Building -> Unit -> Lease -> Invoice model:
 *
 *   - Deed / document vault
 *   - Post-dated cheque vault
 *   - Lease agreements
 *   - Lease renewal offers
 *   - Service charges & escrow
 *   - Ejari Tawtheeq (lease registration)
 *   - Fit-out & alterations board
 *   - Bounced cheque recovery workflow
 *
 * Records cross-reference the core entities by id so the module pages line up
 * with the properties / buildings / units / tenants / leases lists.
 */

import { MOCK_LEASES, MOCK_PROPERTIES, MOCK_TENANTS, MOCK_UNITS } from './mock-seed';

const DAY = 86400000;

/** ISO timestamp offset from today by `days` (negative = past). */
function iso(days: number, hour = 9): string {
  const d = new Date(Date.now() + days * DAY);
  d.setHours(hour, 0, 0, 0);
  return d.toISOString();
}

/** Date-only string (YYYY-MM-DD) offset from today. */
function isoDate(days: number): string {
  return iso(days).slice(0, 10);
}

const pad = (n: number, len = 5) => String(n).padStart(len, '0');

/** Core leases that are still running — the modules mostly operate on these. */
const LIVE_LEASES = MOCK_LEASES.filter(l => l.status === 'active' || l.status === 'expired');
const ACTIVE_PROPERTIES = MOCK_PROPERTIES.filter(p => p.status === 'active');
const LEASABLE_UNITS = MOCK_UNITS.filter(u => u.status !== 'under_maintenance');

const tenantName = (id: number): string => {
  const t = MOCK_TENANTS.find(x => x.id === id);
  return t ? `${t.first_name} ${t.last_name}` : 'Unassigned';
};

const tenantCompany = (id: number): string => MOCK_TENANTS.find(x => x.id === id)?.company ?? '';

const propertyName = (id: number): string => MOCK_PROPERTIES.find(p => p.id === id)?.name ?? 'Unknown property';

/* ================================================================== */
/* Deed / document vault                                               */
/* ================================================================== */

export type VaultAssetType =
  | 'title_deed' | 'noc' | 'ejari_certificate' | 'lease_original'
  | 'cheque_book' | 'insurance_policy' | 'deposit_receipt' | 'affection_plan';

export type VaultStatus = 'sealed' | 'active' | 'released' | 'expired';

export interface VaultAssetRecord {
  id: number;
  code: string;
  asset_type: VaultAssetType;
  title: string;
  property_id: number;
  property_name: string;
  unit_code: string | null;
  holder: string;
  safe_id: string;
  safe_location: string;
  status: VaultStatus;
  deposited_on: string;
  expires_on: string | null;
  value: number;
  document_count: number;
  notes: string;
  created_at: string;
  updated_at: string;
}

const ASSET_LABELS: Record<VaultAssetType, string> = {
  title_deed: 'Title Deed',
  noc: 'No Objection Certificate',
  ejari_certificate: 'Ejari Certificate',
  lease_original: 'Original Lease Agreement',
  cheque_book: 'Cheque Book',
  insurance_policy: 'Insurance Policy',
  deposit_receipt: 'Security Deposit Receipt',
  affection_plan: 'Affection Plan'
};

const ASSET_TYPES: VaultAssetType[] = [
  'title_deed', 'noc', 'ejari_certificate', 'lease_original', 'cheque_book', 'insurance_policy', 'deposit_receipt'
];

const VAULT_STATUSES: VaultStatus[] = ['sealed', 'active', 'sealed', 'released', 'active', 'expired'];

const SAFES = [
  { id: 'S-01', location: 'Ground floor safe — HQ Dubai' },
  { id: 'S-02', location: 'Ground floor safe — HQ Dubai' },
  { id: 'S-03', location: 'Mezzanine cabinet — HQ Dubai' },
  { id: 'S-04', location: 'Offsite — Emirates Towers' },
  { id: 'S-05', location: 'Offsite — Emirates Towers' }
];

export const MOCK_VAULT_ASSETS: VaultAssetRecord[] = (() => {
  const out: VaultAssetRecord[] = [];
  let id = 0;
  for (let i = 0; i < 26; i++) {
    id++;
    const property = ACTIVE_PROPERTIES[i % ACTIVE_PROPERTIES.length];
    const lease = LIVE_LEASES[i % LIVE_LEASES.length];
    const assetType = ASSET_TYPES[i % ASSET_TYPES.length];
    const safe = SAFES[i % SAFES.length];
    const status = VAULT_STATUSES[i % VAULT_STATUSES.length];
    const hasUnit = assetType === 'lease_original' || assetType === 'deposit_receipt' || assetType === 'ejari_certificate';
    out.push({
      id,
      code: `VLT-${pad(7000 + id, 6)}`,
      asset_type: assetType,
      title: `${ASSET_LABELS[assetType]} — ${property.name}${hasUnit ? ` / ${lease.unit?.code}` : ''}`,
      property_id: property.id,
      property_name: property.name,
      unit_code: hasUnit ? (lease.unit?.code ?? null) : null,
      holder: hasUnit ? tenantName(lease.tenant_id) : 'Lottly Property Management',
      safe_id: safe.id,
      safe_location: safe.location,
      status,
      deposited_on: isoDate(-900 + i * 26),
      expires_on: assetType === 'insurance_policy' || assetType === 'noc' ? isoDate(120 + (i % 7) * 45) : null,
      value: assetType === 'title_deed' || assetType === 'affection_plan' ? 0 : 12000 + (i % 9) * 3400,
      document_count: 1 + (i % 4),
      notes: i % 5 === 0 ? 'Original held on behalf of the landlord; copy registered with Ejari.' : '',
      created_at: iso(-900 + i * 26),
      updated_at: iso(-(i % 60))
    });
  }
  return out;
})();

/* ================================================================== */
/* Post-dated cheque vault                                             */
/* ================================================================== */

export type PdcStatus = 'pending' | 'deposited' | 'cleared' | 'returned' | 'replaced';

export interface PostDatedChequeRecord {
  id: number;
  cheque_no: string;
  bank: string;
  branch: string;
  amount: number;
  currency: string;
  payer: string;
  payer_company: string;
  payee: string;
  lease_code: string;
  unit_code: string;
  property_name: string;
  issued_on: string;
  due_on: string;
  deposited_on: string | null;
  status: PdcStatus;
  instalment_label: string;
  bounce_case_code: string | null;
  notes: string;
  created_at: string;
  updated_at: string;
}

const BANKS = ['Emirates NBD', 'Mashreq Bank', 'First Abu Dhabi Bank', 'RAKBANK', 'Dubai Islamic Bank', 'Union National Bank'];

export const MOCK_POST_DATED_CHEQUES: PostDatedChequeRecord[] = (() => {
  const out: PostDatedChequeRecord[] = [];
  let id = 0;
  for (let i = 0; i < 34; i++) {
    id++;
    const lease = LIVE_LEASES[i % LIVE_LEASES.length];
    const dueOffset = -150 + (i % 13) * 26;
    const status: PdcStatus = (['cleared', 'cleared', 'pending', 'deposited', 'returned', 'cleared', 'replaced'] as PdcStatus[])[i % 7];
    out.push({
      id,
      cheque_no: `${String(1000000 + i * 137).slice(0, 7)}`,
      bank: BANKS[i % BANKS.length],
      branch: `${['Dubai', 'Abu Dhabi', 'Sharjah', 'Al Ain'][i % 4]} Main`,
      amount: lease.rent_amount,
      currency: 'AED',
      payer: tenantName(lease.tenant_id),
      payer_company: tenantCompany(lease.tenant_id),
      payee: 'Lottly Property Management LLC',
      lease_code: lease.code,
      unit_code: lease.unit?.code ?? '—',
      property_name: propertyName(lease.property_id),
      issued_on: isoDate(dueOffset - 210),
      due_on: isoDate(dueOffset),
      deposited_on: status === 'cleared' || status === 'returned' ? isoDate(dueOffset + 1) : status === 'deposited' ? isoDate(Math.min(dueOffset + 1, -1)) : null,
      status,
      instalment_label: `${new Date(isoDate(dueOffset)).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })} rent`,
      bounce_case_code: status === 'returned' ? `BC-${pad(300 + (i % 6), 5)}` : null,
      notes: status === 'replaced' ? 'Replaced by a fresh cheque after a bounce case was closed.' : '',
      created_at: iso(dueOffset - 210),
      updated_at: iso(-(i % 45))
    });
  }
  return out;
})();

/* ================================================================== */
/* Lease agreements                                                   */
/* ================================================================== */

export type AgreementStatus = 'draft' | 'sent' | 'partially_signed' | 'executed' | 'expired';

export interface LeaseAgreementRecord {
  id: number;
  code: string;
  lease_code: string;
  property_name: string;
  unit_code: string;
  tenant_name: string;
  tenant_company: string;
  type: string;
  start_date: string;
  end_date: string;
  rent_amount: number;
  security_deposit: number;
  payment_frequency: string;
  template: string;
  version: number;
  generated_on: string;
  status: AgreementStatus;
  landlord_signed_on: string | null;
  tenant_signed_on: string | null;
  witness_name: string | null;
  ejari_number: string | null;
  document_size_kb: number;
  notes: string;
  created_at: string;
  updated_at: string;
}

const TEMPLATES = ['Standard Residential (Ejari)', 'Standard Commercial (Ejari)', 'Fit-out Rider Annex', 'Renewal Addendum', 'Termination & Settlement'];

const AGREEMENT_STATUSES: AgreementStatus[] = ['executed', 'executed', 'partially_signed', 'sent', 'draft', 'expired', 'executed'];

export const MOCK_LEASE_AGREEMENTS: LeaseAgreementRecord[] = (() => {
  const out: LeaseAgreementRecord[] = [];
  let id = 0;
  for (let i = 0; i < 30; i++) {
    id++;
    const lease = LIVE_LEASES[i % LIVE_LEASES.length];
    const status = AGREEMENT_STATUSES[i % AGREEMENT_STATUSES.length];
    const signedOffset = -260 + (i % 16) * 18;
    out.push({
      id,
      code: `AGR-${pad(4000 + id, 6)}`,
      lease_code: lease.code,
      property_name: propertyName(lease.property_id),
      unit_code: lease.unit?.code ?? '—',
      tenant_name: tenantName(lease.tenant_id),
      tenant_company: tenantCompany(lease.tenant_id),
      type: lease.type,
      start_date: lease.start_date,
      end_date: lease.end_date,
      rent_amount: lease.rent_amount,
      security_deposit: lease.deposit_amount,
      payment_frequency: lease.payment_frequency,
      template: TEMPLATES[i % TEMPLATES.length],
      version: 1 + (i % 3),
      generated_on: isoDate(signedOffset - 9),
      status,
      landlord_signed_on: status === 'draft' || status === 'sent' ? null : isoDate(signedOffset),
      tenant_signed_on: status === 'executed' || status === 'expired' ? isoDate(signedOffset + 2) : null,
      witness_name: status === 'executed' || status === 'expired' ? `${['Mr. Saeed Al Mansoori', 'Ms. Fatima Al Zaabi', 'Mr. Ravi Menon', 'Ms. Huda Kareem'][i % 4]}` : null,
      ejari_number: status === 'executed' || status === 'expired' ? `EJ-${pad(100000 + i * 71, 7)}` : null,
      document_size_kb: 180 + (i % 7) * 24,
      notes: i % 6 === 0 ? 'Awaiting counter-signature from the tenant.' : '',
      created_at: iso(signedOffset - 9),
      updated_at: iso(-(i % 30))
    });
  }
  return out;
})();

/* ================================================================== */
/* Lease renewal engine                                                */
/* ================================================================== */

export type RenewalStatus = 'draft' | 'sent' | 'under_negotiation' | 'accepted' | 'declined' | 'expired';

export interface LeaseRenewalRecord {
  id: number;
  code: string;
  lease_code: string;
  property_name: string;
  unit_code: string;
  tenant_name: string;
  tenant_company: string;
  current_rent: number;
  proposed_rent: number;
  uplift_percent: number;
  expiry_date: string;
  days_to_expiry: number;
  offer_sent_on: string | null;
  response_due_on: string;
  responded_on: string | null;
  status: RenewalStatus;
  term_months: number;
  new_start_date: string;
  new_end_date: string;
  discount_percent: number;
  owner_note: string;
  notes: string;
  created_at: string;
  updated_at: string;
}

const RENEWAL_STATUSES: RenewalStatus[] = ['sent', 'accepted', 'under_negotiation', 'sent', 'declined', 'draft', 'accepted', 'expired'];

const UPLIFTS = [0, 2.5, 3, 4, 5, 5, 7.5, 0];

export const MOCK_LEASE_RENEWALS: LeaseRenewalRecord[] = (() => {
  const out: LeaseRenewalRecord[] = [];
  let id = 0;
  for (let i = 0; i < 28; i++) {
    id++;
    const lease = LIVE_LEASES[i % LIVE_LEASES.length];
    const expiryOffset = 12 + (i % 12) * 26;
    const status = RENEWAL_STATUSES[i % RENEWAL_STATUSES.length];
    const uplift = UPLIFTS[i % UPLIFTS.length];
    const discount = status === 'under_negotiation' && i % 3 === 0 ? 2.5 : 0;
    const newStart = isoDate(expiryOffset + 1);
    const newEnd = isoDate(expiryOffset + 1 + 365);
    out.push({
      id,
      code: `RNW-${pad(6000 + id, 6)}`,
      lease_code: lease.code,
      property_name: propertyName(lease.property_id),
      unit_code: lease.unit?.code ?? '—',
      tenant_name: tenantName(lease.tenant_id),
      tenant_company: tenantCompany(lease.tenant_id),
      current_rent: lease.rent_amount,
      proposed_rent: Math.round(lease.rent_amount * (1 + uplift / 100)),
      uplift_percent: uplift,
      expiry_date: lease.end_date,
      days_to_expiry: Math.round((new Date(lease.end_date).getTime() - Date.now()) / DAY),
      offer_sent_on: status === 'draft' ? null : isoDate(-(18 - (i % 14))),
      response_due_on: isoDate(10 - (i % 12)),
      responded_on: status === 'accepted' || status === 'declined' ? isoDate(-(i % 9)) : null,
      status,
      term_months: i % 4 === 0 ? 24 : 12,
      new_start_date: newStart,
      new_end_date: newEnd,
      discount_percent: discount,
      owner_note: i % 7 === 0 ? 'Strong payment record — candidate for a longer term.' : '',
      notes: discount ? 'Tenant countered on rent; awaiting approval.' : '',
      created_at: iso(-(30 - (i % 20))),
      updated_at: iso(-(i % 12))
    });
  }
  return out;
})();

/* ================================================================== */
/* Service charges & escrow                                            */
/* ================================================================== */

export type ServiceChargeStatus = 'draft' | 'issued' | 'partially_paid' | 'paid' | 'closed';

export interface ServiceChargeRecord {
  id: number;
  code: string;
  property_id: number;
  property_name: string;
  period_year: number;
  category: string;
  budgeted_amount: number;
  actual_amount: number;
  variance: number;
  total_area_sqft: number;
  rate_per_sqft: number;
  units_charged: number;
  amount_collected: number;
  outstanding: number;
  issued_on: string;
  due_date: string;
  status: ServiceChargeStatus;
  approved_by: string;
  notes: string;
  created_at: string;
  updated_at: string;
}

const SC_CATEGORIES = [
  'Common area maintenance', 'Chiller & cooling', 'Security & access control',
  'Housekeeping & landscaping', 'Insurance', 'Lift maintenance', 'Waste management', 'Parking operations'
];

export const MOCK_SERVICE_CHARGES: ServiceChargeRecord[] = (() => {
  const out: ServiceChargeRecord[] = [];
  let id = 0;
  const year = new Date().getFullYear();
  for (const property of ACTIVE_PROPERTIES) {
    const units = MOCK_UNITS.filter(u => u.property_id === property.id);
    const totalArea = units.reduce((s, u) => s + u.area_sqft, 0);
    for (const category of SC_CATEGORIES) {
      id++;
      const budgeted = Math.round(totalArea * (2.6 + (id % 5) * 0.9));
      const overspend = id % 6 === 0 ? 1.14 : id % 5 === 0 ? 0.93 : 1;
      const actual = Math.round(budgeted * overspend);
      const collectedRatio = [1, 1, 0.86, 0.62, 1, 0.74, 0.45, 0.3][id % 8];
      const collected = Math.round(actual * collectedRatio);
      out.push({
        id,
        code: `SVC-${year}-${pad(id, 4)}`,
        property_id: property.id,
        property_name: property.name,
        period_year: year,
        category,
        budgeted_amount: budgeted,
        actual_amount: actual,
        variance: actual - budgeted,
        total_area_sqft: totalArea,
        rate_per_sqft: Number((budgeted / Math.max(1, totalArea)).toFixed(2)),
        units_charged: units.length,
        amount_collected: collected,
        outstanding: actual - collected,
        issued_on: isoDate(-((id % 6) * 12 + 20)),
        due_date: isoDate(((id % 4) * 15) + 25),
        status: collected === actual ? 'paid' : collectedRatio > 0.5 ? 'partially_paid' : 'issued',
        approved_by: ['Board of Managers', 'Managing Director', 'Finance Committee'][id % 3],
        notes: overspend > 1 ? 'Overspend driven by emergency chiller repair.' : '',
        created_at: iso(-120 - id * 3),
        updated_at: iso(-(id % 40))
      });
    }
  }
  return out;
})();

export type EscrowStatus = 'active' | 'frozen' | 'closed';

export interface EscrowAccountRecord {
  id: number;
  code: string;
  property_id: number;
  property_name: string;
  bank: string;
  iban: string;
  account_number: string;
  opening_balance: number;
  deposits: number;
  withdrawals: number;
  closing_balance: number;
  currency: string;
  authority_limit: number;
  status: EscrowStatus;
  opened_on: string;
  last_reconciled_on: string;
  manager_name: string;
  notes: string;
  created_at: string;
  updated_at: string;
}

export const MOCK_ESCROW_ACCOUNTS: EscrowAccountRecord[] = ACTIVE_PROPERTIES.map((property, i) => {
  const opening = 180000 + i * 42000;
  const deposits = 240000 + (i % 5) * 65000;
  const withdrawals = 205000 + (i % 4) * 51000;
  return {
    id: i + 1,
    code: `ESC-${pad(2200 + i + 1, 6)}`,
    property_id: property.id,
    property_name: property.name,
    bank: BANKS[i % BANKS.length],
    iban: `AE${String(100000000000 + i * 777777).padStart(15, '0')}`,
    account_number: `${String(8300000000 + i * 13571).slice(0, 10)}`,
    opening_balance: opening,
    deposits,
    withdrawals,
    closing_balance: opening + deposits - withdrawals,
    currency: 'AED',
    authority_limit: 250000,
    status: (['active', 'active', 'active', 'frozen', 'active'] as EscrowStatus[])[i % 5],
    opened_on: isoDate(-900 + i * 40),
    last_reconciled_on: isoDate(-(i * 5 + 3)),
    manager_name: ['Saeed Al Mansoori', 'Fatima Al Zaabi', 'Ravi Menon', 'Huda Kareem', 'Omar Nasser'][i % 5],
    notes: i === 3 ? 'Frozen pending a landlord audit.' : '',
    created_at: iso(-900 + i * 40),
    updated_at: iso(-(i * 5 + 3))
  };
});

/* ================================================================== */
/* Ejari Tawtheeq registration console                                */
/* ================================================================== */

export type EjariStatus = 'draft' | 'submitted' | 'registered' | 'rejected' | 'cancelled';
export type EjariType = 'new' | 'renewal' | 'amendment' | 'termination';

export interface EjariRecord {
  id: number;
  code: string;
  ejari_number: string | null;
  lease_code: string;
  property_name: string;
  unit_code: string;
  landlord_name: string;
  tenant_name: string;
  tenant_company: string;
  ejari_type: EjariType;
  registration_date: string | null;
  certificate_issued_on: string | null;
  expiry_date: string;
  status: EjariStatus;
  registration_fee: number;
  penalty_amount: number;
  deed_reference: string;
  free_zone_property: boolean;
  attempts: number;
  last_error: string;
  notes: string;
  created_at: string;
  updated_at: string;
}

const EJARI_STATUSES: EjariStatus[] = ['registered', 'registered', 'submitted', 'draft', 'registered', 'rejected', 'cancelled'];
const EJARI_TYPES: EjariType[] = ['new', 'new', 'renewal', 'amendment', 'new', 'termination'];
const EJARI_ERRORS = ['', '', '', 'Deed number mismatch with the tenancy contract.', 'Tenant signature missing on page 12.', ''];

export const MOCK_EJARI_CONTRACTS: EjariRecord[] = (() => {
  const out: EjariRecord[] = [];
  let id = 0;
  for (let i = 0; i < 30; i++) {
    id++;
    const lease = LIVE_LEASES[i % LIVE_LEASES.length];
    const status = EJARI_STATUSES[i % EJARI_STATUSES.length];
    const registered = status === 'registered';
    out.push({
      id,
      code: `EJR-${pad(900 + id, 6)}`,
      ejari_number: registered ? `EJ-${pad(100000 + i * 71, 7)}` : status === 'rejected' ? `EJ-REJ-${pad(5000 + id, 5)}` : null,
      lease_code: lease.code,
      property_name: propertyName(lease.property_id),
      unit_code: lease.unit?.code ?? '—',
      landlord_name: 'Lottly Property Management LLC',
      tenant_name: tenantName(lease.tenant_id),
      tenant_company: tenantCompany(lease.tenant_id),
      ejari_type: EJARI_TYPES[i % EJARI_TYPES.length],
      registration_date: registered ? isoDate(-250 + (i % 16) * 16) : null,
      certificate_issued_on: registered ? isoDate(-250 + (i % 16) * 16 + 1) : null,
      expiry_date: lease.end_date,
      status,
      registration_fee: 220,
      penalty_amount: status === 'rejected' ? 500 : status === 'submitted' ? 0 : i % 4 === 0 ? 100 : 0,
      deed_reference: `${pad(1200 + i * 13, 6)}/${new Date().getFullYear()}`,
      free_zone_property: i % 5 === 0,
      attempts: status === 'rejected' ? 2 : status === 'submitted' ? 1 : registered ? 1 : 0,
      last_error: EJARI_ERRORS[i % EJARI_ERRORS.length],
      notes: status === 'submitted' ? 'Queued with the Ejari gateway; usually clears within 24 hours.' : '',
      created_at: iso(-260 + (i % 18) * 16),
      updated_at: iso(-(i % 22))
    });
  }
  return out;
})();

/* ================================================================== */
/* Fit-out & alterations board                                         */
/* ================================================================== */

export type PermitStatus = 'not_submitted' | 'under_review' | 'approved' | 'rejected' | 'expired';
export type NocStatus = 'pending' | 'inspection_scheduled' | 'noc_issued' | 'noc_rejected';
export type DepositStatus = 'pending' | 'received' | 'refunded' | 'forfeited';

export interface FitOutRequestRecord {
  id: number;
  code: string;
  property_name: string;
  unit_code: string;
  tenant_name: string;
  tenant_company: string;
  contractor_name: string;
  contractor_trn: string;
  scope: string;
  permit_status: PermitStatus;
  noc_status: NocStatus;
  deposit_amount: number;
  deposit_status: DepositStatus;
  requested_on: string;
  expected_completion: string;
  inspection_date: string | null;
  progress_percent: number;
  priority: 'low' | 'normal' | 'high';
  estimated_cost: number;
  owner_name: string;
  notes: string;
  created_at: string;
  updated_at: string;
}

const PERMIT_STATUSES: PermitStatus[] = ['approved', 'under_review', 'approved', 'not_submitted', 'approved', 'rejected', 'approved'];
const NOC_STATUSES: NocStatus[] = ['noc_issued', 'inspection_scheduled', 'noc_issued', 'pending', 'noc_rejected', 'pending', 'noc_issued'];
const SCOPES = [
  'Partitioned office fit-out with two additional cabins',
  'Full kitchen joinery replacement and flooring',
  'Shopfront re-branding and signage upgrade',
  'AC ducting relocation and additional diffusers',
  'Warehouse racking and loading bay marking',
  'Clinic fit-out including dental operatory',
  'Demolition of existing partition and new drywall'
];
const CONTRACTORS = ['Al Habtoor Interiors', 'Meridian Fitout LLC', 'Brightnest Contracting', 'Vertex Design & Build', 'Gulf Facades', 'Nordic Joinery Works'];
const OWNERS = ['Rami Haddad', 'Leila Farsi', 'Omar Nasser', 'Priya Raman', 'Tarek Mansour'];

export const MOCK_FIT_OUT_REQUESTS: FitOutRequestRecord[] = (() => {
  const out: FitOutRequestRecord[] = [];
  let id = 0;
  for (let i = 0; i < 27; i++) {
    id++;
    const unit = LEASABLE_UNITS[(i * 5) % LEASABLE_UNITS.length];
    const lease = LIVE_LEASES.find(l => l.unit_id === unit.id) ?? LIVE_LEASES[i % LIVE_LEASES.length];
    const permit = PERMIT_STATUSES[i % PERMIT_STATUSES.length];
    const noc = NOC_STATUSES[i % NOC_STATUSES.length];
    const requestedOffset = -70 + (i % 9) * 14;
    const cost = 45000 + (i % 8) * 22000;
    out.push({
      id,
      code: `FIT-${pad(1100 + id, 6)}`,
      property_name: propertyName(unit.property_id),
      unit_code: unit.code,
      tenant_name: tenantName(lease.tenant_id),
      tenant_company: tenantCompany(lease.tenant_id),
      contractor_name: CONTRACTORS[i % CONTRACTORS.length],
      contractor_trn: `100${pad(23456789 + i * 1234, 11)}`,
      scope: SCOPES[i % SCOPES.length],
      permit_status: permit,
      noc_status: noc,
      deposit_amount: permit === 'approved' ? Math.round(cost * 0.1) : 0,
      deposit_status: permit === 'approved' ? (noc === 'noc_issued' ? 'refunded' : 'received') : 'pending',
      requested_on: isoDate(requestedOffset),
      expected_completion: isoDate(requestedOffset + 60 + (i % 4) * 20),
      inspection_date: noc === 'inspection_scheduled' ? isoDate(4 + (i % 20)) : null,
      progress_percent: permit === 'rejected' ? 0 : noc === 'noc_issued' ? 100 : 10 + (i % 6) * 15,
      priority: (['normal', 'high', 'normal', 'low', 'normal'] as FitOutRequestRecord['priority'][])[i % 5],
      estimated_cost: cost,
      owner_name: OWNERS[i % OWNERS.length],
      notes: permit === 'rejected' ? 'Structural load report missing — resubmit with a third-party certification.' : noc === 'noc_issued' ? 'Final NOC issued; deposit refunded.' : '',
      created_at: iso(requestedOffset),
      updated_at: iso(-(i % 25))
    });
  }
  return out;
})();

/* ================================================================== */
/* Bounced cheque workflow engine                                      */
/* ================================================================== */

export type BounceReason =
  | 'insufficient_funds' | 'account_closed' | 'signature_mismatch'
  | 'stale_dated' | 'stop_payment' | 'other';

export type BounceStage =
  | 'new' | 'notified' | 'promise_to_pay' | 'partially_recovered'
  | 'recovered' | 'escalated' | 'legal_action' | 'written_off';

export interface BouncedChequeRecord {
  id: number;
  code: string;
  cheque_no: string;
  bank: string;
  amount: number;
  currency: string;
  tenant_name: string;
  tenant_company: string;
  lease_code: string;
  unit_code: string;
  property_name: string;
  bounced_on: string;
  bounce_reason: BounceReason;
  stage: BounceStage;
  recovered_amount: number;
  outstanding: number;
  recovery_percent: number;
  actions_taken: number;
  next_action_due: string;
  aging_days: number;
  case_manager: string;
  priority: 'low' | 'normal' | 'high' | 'critical';
  legal_notice_sent: boolean;
  notes: string;
  created_at: string;
  updated_at: string;
}

const BOUNCE_REASONS: BounceReason[] = [
  'insufficient_funds', 'insufficient_funds', 'account_closed',
  'signature_mismatch', 'stale_dated', 'stop_payment'
];

const BOUNCE_STAGES: BounceStage[] = [
  'notified', 'promise_to_pay', 'partially_recovered', 'escalated',
  'recovered', 'new', 'legal_action', 'partially_recovered', 'written_off'
];

const CASE_MANAGERS = ['Amina Farouk', 'Daniel Reeves', 'Sana Qureshi', 'Michael Boone', 'Yara Sultan'];
const STAGE_NOTES: Record<BounceStage, string> = {
  new: 'Awaiting first contact with the payer.',
  notified: 'Formal bounced-cheque notice issued and acknowledged.',
  promise_to_pay: 'Payer committed to a settlement date; reminder scheduled.',
  partially_recovered: 'Instalment received against the bounced amount.',
  recovered: 'Full amount recovered and the case is closed.',
  escalated: 'Payer unresponsive — passed to the credit control team.',
  legal_action: 'Case file referred to legal for a notice to pay.',
  written_off: 'Recovery abandoned after the statutory window elapsed.'
};

export const MOCK_BOUNCED_CHEQUES: BouncedChequeRecord[] = (() => {
  const out: BouncedChequeRecord[] = [];
  let id = 0;
  for (let i = 0; i < 24; i++) {
    id++;
    const lease = LIVE_LEASES[i % LIVE_LEASES.length];
    const bouncedOffset = -(4 + (i % 11) * 12);
    const stage = BOUNCE_STAGES[i % BOUNCE_STAGES.length];
    const amount = lease.rent_amount;
    const recoveredRatio = {
      new: 0, notified: 0, promise_to_pay: 0, partially_recovered: 0.5,
      recovered: 1, escalated: 0, legal_action: 0.15, written_off: 0
    }[stage];
    const recovered = Math.round(amount * recoveredRatio);
    out.push({
      id,
      code: `BC-${pad(300 + id, 5)}`,
      cheque_no: `${String(1000000 + ((i + 3) % 34) * 137).slice(0, 7)}`,
      bank: BANKS[(i + 2) % BANKS.length],
      amount,
      currency: 'AED',
      tenant_name: tenantName(lease.tenant_id),
      tenant_company: tenantCompany(lease.tenant_id),
      lease_code: lease.code,
      unit_code: lease.unit?.code ?? '—',
      property_name: propertyName(lease.property_id),
      bounced_on: isoDate(bouncedOffset),
      bounce_reason: BOUNCE_REASONS[i % BOUNCE_REASONS.length],
      stage,
      recovered_amount: recovered,
      outstanding: amount - recovered,
      recovery_percent: Math.round(recoveredRatio * 100),
      actions_taken: 1 + (i % 4),
      next_action_due: stage === 'recovered' || stage === 'written_off' ? isoDate(bouncedOffset) : isoDate(bouncedOffset + 10 + (i % 5) * 5),
      aging_days: -bouncedOffset,
      case_manager: CASE_MANAGERS[i % CASE_MANAGERS.length],
      priority: (['normal', 'high', 'critical', 'normal', 'low'] as BouncedChequeRecord['priority'][])[i % 5],
      legal_notice_sent: stage === 'legal_action' || stage === 'written_off',
      notes: STAGE_NOTES[stage],
      created_at: iso(bouncedOffset),
      updated_at: iso(-(i % 14))
    });
  }
  return out;
})();
