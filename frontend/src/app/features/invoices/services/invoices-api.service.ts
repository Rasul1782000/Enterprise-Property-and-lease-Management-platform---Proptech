import { Injectable } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { PaginatedResponse, ApiParams } from '../../../core/types';
import { Observable } from 'rxjs';

export interface Invoice {
  id: number;
  lease_id: number;
  property_id: number;
  tenant_id: number;
  code: string;
  type: 'rent' | 'deposit' | 'late_fee' | 'utility' | 'maintenance' | 'other';
  status: 'draft' | 'sent' | 'paid' | 'partial' | 'overdue' | 'cancelled';
  issue_date: string;
  due_date: string;
  paid_date: string | null;
  amount: number;
  paid_amount: number;
  balance: number;
  currency: string;
  description: string;
  line_items: InvoiceLineItem[];
  created_at: string;
  updated_at: string;
  lease?: any;
  tenant?: any;
  property?: any;
  payments?: any[];
}

export interface InvoiceLineItem {
  id?: number;
  description: string;
  quantity: number;
  unit_price: number;
  amount: number;
  tax_rate: number;
  tax_amount: number;
}

export interface CreateInvoiceDto {
  lease_id: number;
  type: Invoice['type'];
  issue_date: string;
  due_date: string;
  amount: number;
  currency?: string;
  description?: string;
  line_items?: Omit<InvoiceLineItem, 'id'>[];
}

export type UpdateInvoiceDto = Partial<CreateInvoiceDto>;

export interface Payment {
  id: number;
  invoice_id: number;
  amount: number;
  payment_date: string;
  payment_method: 'cash' | 'check' | 'bank_transfer' | 'card' | 'online';
  reference: string;
  notes: string;
  created_at: string;
}

export interface CreatePaymentDto {
  invoice_id: number;
  amount: number;
  payment_date: string;
  payment_method: Payment['payment_method'];
  reference?: string;
  notes?: string;
}

@Injectable({ providedIn: 'root' })
export class InvoicesApiService {
  private readonly endpoint = 'invoices';

  constructor(private api: ApiService) {}

  list(params?: ApiParams): Observable<PaginatedResponse<Invoice>> {
    return this.api.getPaginated<Invoice>(this.endpoint, { ...params, include: 'lease,tenant,property,payments' });
  }

  get(id: number): Observable<Invoice> {
    return this.api.get<Invoice>(`${this.endpoint}/${id}`, { include: 'lease,tenant,property,payments,line_items' });
  }

  getByLease(leaseId: number, params?: ApiParams): Observable<PaginatedResponse<Invoice>> {
    return this.api.getPaginated<Invoice>(this.endpoint, { ...params, 'filter[lease_id]': leaseId });
  }

  getByTenant(tenantId: number, params?: ApiParams): Observable<PaginatedResponse<Invoice>> {
    return this.api.getPaginated<Invoice>(this.endpoint, { ...params, 'filter[tenant_id]': tenantId });
  }

  getByProperty(propertyId: number, params?: ApiParams): Observable<PaginatedResponse<Invoice>> {
    return this.api.getPaginated<Invoice>(this.endpoint, { ...params, 'filter[property_id]': propertyId });
  }

  getOverdue(params?: ApiParams): Observable<PaginatedResponse<Invoice>> {
    return this.api.getPaginated<Invoice>(this.endpoint, { ...params, 'filter[status]': 'overdue' });
  }

  getPending(params?: ApiParams): Observable<PaginatedResponse<Invoice>> {
    return this.api.getPaginated<Invoice>(this.endpoint, { ...params, 'filter[status]': 'sent' });
  }

  create(dto: CreateInvoiceDto): Observable<Invoice> {
    return this.api.post<Invoice>(this.endpoint, dto);
  }

  update(id: number, dto: UpdateInvoiceDto): Observable<Invoice> {
    return this.api.put<Invoice>(`${this.endpoint}/${id}`, dto);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`${this.endpoint}/${id}`);
  }

  send(id: number): Observable<Invoice> {
    return this.api.post<Invoice>(`${this.endpoint}/${id}/send`, {});
  }

  recordPayment(invoiceId: number, dto: CreatePaymentDto): Observable<Payment> {
    return this.api.post<Payment>(`${this.endpoint}/${invoiceId}/payments`, dto);
  }

  getPayments(invoiceId: number, params?: ApiParams): Observable<PaginatedResponse<Payment>> {
    return this.api.getPaginated<Payment>(`${this.endpoint}/${invoiceId}/payments`, params);
  }

  generatePdf(id: number): Observable<Blob> {
    return this.api.getBlob(`${this.endpoint}/${id}/pdf`);
  }

  bulkGenerate(leaseIds: number[], issueDate: string, dueDate: string): Observable<Invoice[]> {
    return this.api.post<Invoice[]>(`${this.endpoint}/bulk-generate`, { lease_ids: leaseIds, issue_date: issueDate, due_date: dueDate });
  }
}