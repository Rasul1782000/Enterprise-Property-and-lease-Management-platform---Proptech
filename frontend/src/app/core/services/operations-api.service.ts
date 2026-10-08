import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { ApiParams, PaginatedResponse } from '../types';

export type VaultStatus = 'sealed' | 'active' | 'released' | 'expired';
export type PdcStatus = 'pending' | 'deposited' | 'cleared' | 'returned' | 'replaced';
export type AgreementStatus = 'draft' | 'sent' | 'partially_signed' | 'executed' | 'expired';
export type RenewalStatus = 'draft' | 'sent' | 'under_negotiation' | 'accepted' | 'declined' | 'expired';
export type ServiceChargeStatus = 'draft' | 'issued' | 'partially_paid' | 'paid' | 'closed';
export type EscrowStatus = 'active' | 'frozen' | 'closed';
export type EjariStatus = 'draft' | 'submitted' | 'registered' | 'rejected' | 'cancelled';
export type PermitStatus = 'not_submitted' | 'under_review' | 'approved' | 'rejected' | 'expired';
export type NocStatus = 'pending' | 'inspection_scheduled' | 'noc_issued' | 'noc_rejected';
export type BounceStage =
  | 'new' | 'notified' | 'promise_to_pay' | 'partially_recovered'
  | 'recovered' | 'escalated' | 'legal_action' | 'written_off';

export interface VaultAsset {
  id: number; code: string; asset_type: string; title: string;
  property_id: number; property_name: string; unit_code: string | null; holder: string;
  safe_id: string; safe_location: string; status: VaultStatus;
  deposited_on: string; expires_on: string | null; value: number;
  document_count: number; notes: string; created_at: string; updated_at: string;
}

export interface PostDatedCheque {
  id: number; cheque_no: string; bank: string; branch: string; amount: number; currency: string;
  payer: string; payer_company: string; payee: string; lease_code: string; unit_code: string;
  property_name: string; issued_on: string; due_on: string; deposited_on: string | null;
  status: PdcStatus; instalment_label: string; bounce_case_code: string | null;
  notes: string; created_at: string; updated_at: string;
}

export interface LeaseAgreement {
  id: number; code: string; lease_code: string; property_name: string; unit_code: string;
  tenant_name: string; tenant_company: string; type: string; start_date: string; end_date: string;
  rent_amount: number; security_deposit: number; payment_frequency: string; template: string;
  version: number; generated_on: string; status: AgreementStatus;
  landlord_signed_on: string | null; tenant_signed_on: string | null; witness_name: string | null;
  ejari_number: string | null; document_size_kb: number; notes: string;
  created_at: string; updated_at: string;
}

export interface LeaseRenewal {
  id: number; code: string; lease_code: string; property_name: string; unit_code: string;
  tenant_name: string; tenant_company: string; current_rent: number; proposed_rent: number;
  uplift_percent: number; expiry_date: string; days_to_expiry: number;
  offer_sent_on: string | null; response_due_on: string; responded_on: string | null;
  status: RenewalStatus; term_months: number; new_start_date: string; new_end_date: string;
  discount_percent: number; owner_note: string; notes: string;
  created_at: string; updated_at: string;
}

export interface ServiceCharge {
  id: number; code: string; property_id: number; property_name: string; period_year: number;
  category: string; budgeted_amount: number; actual_amount: number; variance: number;
  total_area_sqft: number; rate_per_sqft: number; units_charged: number;
  amount_collected: number; outstanding: number; issued_on: string; due_date: string;
  status: ServiceChargeStatus; approved_by: string; notes: string;
  created_at: string; updated_at: string;
}

export interface EscrowAccount {
  id: number; code: string; property_id: number; property_name: string; bank: string; iban: string;
  account_number: string; opening_balance: number; deposits: number; withdrawals: number;
  closing_balance: number; currency: string; authority_limit: number; status: EscrowStatus;
  opened_on: string; last_reconciled_on: string; manager_name: string; notes: string;
  created_at: string; updated_at: string;
}

export interface EjariContract {
  id: number; code: string; ejari_number: string | null; lease_code: string;
  property_name: string; unit_code: string; landlord_name: string; tenant_name: string;
  tenant_company: string; ejari_type: string; registration_date: string | null;
  certificate_issued_on: string | null; expiry_date: string; status: EjariStatus;
  registration_fee: number; penalty_amount: number; deed_reference: string;
  free_zone_property: boolean; attempts: number; last_error: string; notes: string;
  created_at: string; updated_at: string;
}

export interface FitOutRequest {
  id: number; code: string; property_name: string; unit_code: string; tenant_name: string;
  tenant_company: string; contractor_name: string; contractor_trn: string; scope: string;
  permit_status: PermitStatus; noc_status: NocStatus; deposit_amount: number; deposit_status: string;
  requested_on: string; expected_completion: string; inspection_date: string | null;
  progress_percent: number; priority: 'low' | 'normal' | 'high'; estimated_cost: number;
  owner_name: string; notes: string; created_at: string; updated_at: string;
}

export interface BouncedCheque {
  id: number; code: string; cheque_no: string; bank: string; amount: number; currency: string;
  tenant_name: string; tenant_company: string; lease_code: string; unit_code: string;
  property_name: string; bounced_on: string; bounce_reason: string; stage: BounceStage;
  recovered_amount: number; outstanding: number; recovery_percent: number; actions_taken: number;
  next_action_due: string; aging_days: number; case_manager: string;
  priority: 'low' | 'normal' | 'high' | 'critical'; legal_notice_sent: boolean; notes: string;
  created_at: string; updated_at: string;
}

export interface ModuleStats {
  total: number;
  attention: number;
  by_status: Record<string, number>;
  [key: string]: any;
}

@Injectable({ providedIn: 'root' })
export class OperationsApiService {
  constructor(private api: ApiService) {}

  list<T>(endpoint: string, params?: ApiParams): Observable<PaginatedResponse<T>> {
    return this.api.getPaginated<T>(endpoint, params);
  }

  get<T>(endpoint: string, id: number): Observable<T> {
    return this.api.get<T>(`${endpoint}/${id}`);
  }

  stats(endpoint: string): Observable<ModuleStats> {
    return this.api.get<ModuleStats>(`${endpoint}/stats`);
  }

  create<T>(endpoint: string, payload: Partial<T>): Observable<T> {
    return this.api.post<T>(endpoint, payload);
  }

  update<T>(endpoint: string, id: number, payload: Partial<T>): Observable<T> {
    return this.api.patch<T>(`${endpoint}/${id}`, payload);
  }

  remove(endpoint: string, id: number): Observable<void> {
    return this.api.delete<void>(`${endpoint}/${id}`);
  }

  run<T>(endpoint: string, id: number, action: string, payload: Record<string, any> = {}): Observable<T> {
    return this.api.post<T>(`${endpoint}/${id}/${action}`, payload);
  }

  vaultAssets(params?: ApiParams) { return this.list<VaultAsset>('vault-assets', params); }
  postDatedCheques(params?: ApiParams) { return this.list<PostDatedCheque>('post-dated-cheques', params); }
  leaseAgreements(params?: ApiParams) { return this.list<LeaseAgreement>('lease-agreements', params); }
  leaseRenewals(params?: ApiParams) { return this.list<LeaseRenewal>('lease-renewals', params); }
  serviceCharges(params?: ApiParams) { return this.list<ServiceCharge>('service-charges', params); }
  escrowAccounts(params?: ApiParams) { return this.list<EscrowAccount>('escrow-accounts', params); }
  ejariContracts(params?: ApiParams) { return this.list<EjariContract>('ejari-contracts', params); }
  fitOutRequests(params?: ApiParams) { return this.list<FitOutRequest>('fit-out-requests', params); }
  bouncedCheques(params?: ApiParams) { return this.list<BouncedCheque>('bounced-cheques', params); }
}
