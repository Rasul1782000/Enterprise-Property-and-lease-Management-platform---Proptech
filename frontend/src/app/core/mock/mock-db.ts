import { HttpEvent, HttpRequest, HttpResponse } from '@angular/common/http';
import { Observable, of, throwError } from 'rxjs';
import {
  MOCK_BUILDINGS,
  MOCK_INVOICES,
  MOCK_LEASES,
  MOCK_PAYMENTS,
  MOCK_PROPERTIES,
  MOCK_TENANTS,
  MOCK_TENANT_DOCUMENTS,
  MOCK_UNITS,
  MOCK_USERS,
  BuildingRecord,
  InvoiceRecord,
  LeaseRecord,
  PaymentRecord,
  PropertyRecord,
  TenantDocumentRecord,
  TenantRecord,
  UnitRecord
} from './mock-seed';
import {
  MOCK_BOUNCED_CHEQUES,
  MOCK_EJARI_CONTRACTS,
  MOCK_ESCROW_ACCOUNTS,
  MOCK_FIT_OUT_REQUESTS,
  MOCK_LEASE_AGREEMENTS,
  MOCK_LEASE_RENEWALS,
  MOCK_POST_DATED_CHEQUES,
  MOCK_SERVICE_CHARGES,
  MOCK_VAULT_ASSETS,
  BouncedChequeRecord,
  EjariRecord,
  EscrowAccountRecord,
  FitOutRequestRecord,
  LeaseAgreementRecord,
  LeaseRenewalRecord,
  PostDatedChequeRecord,
  ServiceChargeRecord,
  VaultAssetRecord
} from './module-seed';

type Any = any;

const DAY = 86400000;

/** ISO date (YYYY-MM-DD) for "today" — the default for workflow transitions. */
const today = (): string => new Date().toISOString().slice(0, 10);

/* ------------------------------------------------------------------ */
/* Operational module registry                                         */
/* ------------------------------------------------------------------ */

/** Endpoint -> MockDb collection, for the UAE-specific operational modules. */
const MODULE_ENDPOINTS: Record<string, string> = {
  'vault-assets': 'vaultAssets',
  'post-dated-cheques': 'postDatedCheques',
  'lease-agreements': 'leaseAgreements',
  'lease-renewals': 'leaseRenewals',
  'service-charges': 'serviceCharges',
  'escrow-accounts': 'escrowAccounts',
  'ejari-contracts': 'ejariContracts',
  'fit-out-requests': 'fitOutRequests',
  'bounced-cheques': 'bouncedCheques'
};

/** Field holding the status each module groups and filters by. */
const MODULE_STATUS_FIELD: Record<string, string> = {
  'fit-out-requests': 'permit_status',
  'bounced-cheques': 'stage'
};

/** Extra status field rolled up alongside the primary one, prefixed in the counts. */
const MODULE_SECONDARY_STATUS: Record<string, string> = {
  'fit-out-requests': 'noc_status'
};

/** Statuses that count as "needs attention" on the module's stat cards. */
const MODULE_ATTENTION: Record<string, string[]> = {
  'vault-assets': ['expired', 'released'],
  'post-dated-cheques': ['returned', 'pending'],
  'lease-agreements': ['draft', 'sent', 'partially_signed'],
  'lease-renewals': ['sent', 'under_negotiation', 'declined'],
  'service-charges': ['issued', 'partially_paid'],
  'escrow-accounts': ['frozen'],
  'ejari-contracts': ['draft', 'submitted', 'rejected'],
  'fit-out-requests': ['not_submitted', 'under_review', 'rejected'],
  'bounced-cheques': ['new', 'notified', 'promise_to_pay', 'partially_recovered', 'escalated', 'legal_action']
};

/** Numeric fields summed into each module's stat payload. */
const MODULE_SUM_FIELDS: Record<string, string[]> = {
  'vault-assets': ['value'],
  'post-dated-cheques': ['amount'],
  'lease-agreements': ['rent_amount', 'security_deposit'],
  'lease-renewals': ['current_rent', 'proposed_rent'],
  'service-charges': ['budgeted_amount', 'actual_amount', 'amount_collected', 'outstanding'],
  'escrow-accounts': ['opening_balance', 'deposits', 'withdrawals', 'closing_balance', 'authority_limit'],
  'ejari-contracts': ['registration_fee', 'penalty_amount'],
  'fit-out-requests': ['estimated_cost', 'deposit_amount'],
  'bounced-cheques': ['amount', 'recovered_amount', 'outstanding']
};

/** Workflow transitions each module exposes as `POST /{endpoint}/{id}/{action}`. */
const MODULE_ACTIONS: Record<string, Record<string, (record: Any, body: Any) => Any>> = {
  'vault-assets': {
    release: () => ({ status: 'released', notes: 'Released to the holder on request.' }),
    reseal: () => ({ status: 'sealed' }),
    activate: () => ({ status: 'active' })
  },
  'post-dated-cheques': {
    deposit: () => ({ status: 'deposited', deposited_on: today() }),
    clear: () => ({ status: 'cleared' }),
    return: () => ({ status: 'returned', bounce_case_code: null }),
    replace: () => ({ status: 'replaced' })
  },
  'lease-agreements': {
    send: () => ({ status: 'sent' }),
    countersign: () => ({ status: 'executed', tenant_signed_on: today() }),
    execute: () => ({ status: 'executed', landlord_signed_on: today(), tenant_signed_on: today() })
  },
  'lease-renewals': {
    send: () => ({ status: 'sent', offer_sent_on: today() }),
    accept: () => ({ status: 'accepted', responded_on: today() }),
    decline: () => ({ status: 'declined', responded_on: today() }),
    negotiate: () => ({ status: 'under_negotiation' })
  },
  'service-charges': {
    issue: () => ({ status: 'issued' }),
    collect: (r, body) => {
      const amount = Number(body.amount || 0);
      const collected = Number(r.amount_collected || 0) + amount;
      const actual = Number(r.actual_amount || 0);
      return { amount_collected: collected, outstanding: Math.max(0, actual - collected), status: collected >= actual ? 'paid' : 'partially_paid' };
    },
    close: () => ({ status: 'closed' })
  },
  'escrow-accounts': {
    freeze: () => ({ status: 'frozen' }),
    unfreeze: () => ({ status: 'active' }),
    reconcile: () => ({ last_reconciled_on: today() })
  },
  'ejari-contracts': {
    submit: (r) => ({ status: 'submitted', attempts: Number(r.attempts || 0) + 1, last_error: '' }),
    register: (r) => ({
      status: 'registered',
      attempts: Number(r.attempts || 0) + 1,
      last_error: '',
      ejari_number: r.ejari_number ?? `EJ-${Math.floor(100000 + Math.random() * 8999999)}`,
      registration_date: today(),
      certificate_issued_on: today()
    }),
    reject: (r) => ({ status: 'rejected', attempts: Number(r.attempts || 0) + 1, penalty_amount: 500 })
  },
  'fit-out-requests': {
    approve: () => ({ permit_status: 'approved' }),
    reject: () => ({ permit_status: 'rejected', notes: 'Rejected at board review — resubmit with the outstanding documents.' }),
    'schedule-inspection': () => ({ noc_status: 'inspection_scheduled', inspection_date: today() }),
    'issue-noc': () => ({ noc_status: 'noc_issued', progress_percent: 100, deposit_status: 'refunded' })
  },
  'bounced-cheques': {
    notify: (r) => ({ stage: 'notified', actions_taken: Number(r.actions_taken || 0) + 1 }),
    promise: (r) => ({ stage: 'promise_to_pay', actions_taken: Number(r.actions_taken || 0) + 1 }),
    recover: (r, body) => {
      const amount = Number(body.amount || r.outstanding || 0);
      const recovered = Number(r.recovered_amount || 0) + amount;
      return {
        stage: recovered >= Number(r.amount || 0) ? 'recovered' : 'partially_recovered',
        recovered_amount: recovered,
        outstanding: Math.max(0, Number(r.amount || 0) - recovered),
        recovery_percent: Math.round((recovered / Math.max(1, Number(r.amount || 1))) * 100),
        actions_taken: Number(r.actions_taken || 0) + 1
      };
    },
    escalate: (r) => ({ stage: 'escalated', priority: 'high', actions_taken: Number(r.actions_taken || 0) + 1 }),
    legal: (r) => ({ stage: 'legal_action', legal_notice_sent: true, priority: 'critical', actions_taken: Number(r.actions_taken || 0) + 1 }),
    close: (r) => ({ stage: 'recovered', recovered_amount: r.amount, outstanding: 0, recovery_percent: 100, actions_taken: Number(r.actions_taken || 0) + 1 }),
    'write-off': (r) => ({ stage: 'written_off', actions_taken: Number(r.actions_taken || 0) + 1 })
  }
};

/**
 * In-memory stand-in for the REST API. Holds mutable copies of the seed data and
 * implements just enough of the backend contract (list filters, sorting,
 * pagination, nested `include`) for every page to render meaningful content.
 */
export class MockDb {
  properties: PropertyRecord[] = structuredClone(MOCK_PROPERTIES);
  buildings: BuildingRecord[] = structuredClone(MOCK_BUILDINGS);
  units: UnitRecord[] = structuredClone(MOCK_UNITS);
  tenants: TenantRecord[] = structuredClone(MOCK_TENANTS);
  tenantDocuments: TenantDocumentRecord[] = structuredClone(MOCK_TENANT_DOCUMENTS);
  leases: LeaseRecord[] = structuredClone(MOCK_LEASES);
  invoices: InvoiceRecord[] = structuredClone(MOCK_INVOICES);
  payments: PaymentRecord[] = structuredClone(MOCK_PAYMENTS);

  /* Operational modules — see MODULE_ENDPOINTS for the endpoint mapping. */
  vaultAssets: VaultAssetRecord[] = structuredClone(MOCK_VAULT_ASSETS);
  postDatedCheques: PostDatedChequeRecord[] = structuredClone(MOCK_POST_DATED_CHEQUES);
  leaseAgreements: LeaseAgreementRecord[] = structuredClone(MOCK_LEASE_AGREEMENTS);
  leaseRenewals: LeaseRenewalRecord[] = structuredClone(MOCK_LEASE_RENEWALS);
  serviceCharges: ServiceChargeRecord[] = structuredClone(MOCK_SERVICE_CHARGES);
  escrowAccounts: EscrowAccountRecord[] = structuredClone(MOCK_ESCROW_ACCOUNTS);
  ejariContracts: EjariRecord[] = structuredClone(MOCK_EJARI_CONTRACTS);
  fitOutRequests: FitOutRequestRecord[] = structuredClone(MOCK_FIT_OUT_REQUESTS);
  bouncedCheques: BouncedChequeRecord[] = structuredClone(MOCK_BOUNCED_CHEQUES);

  private seq(entity: string): number {
    const list = (this as any)[entity] as any[];
    return list.reduce((m, r) => Math.max(m, Number(r.id) || 0), 0) + 1;
  }

  /* ------------------------------------------------------------------ */
  /* Router                                                              */
  /* ------------------------------------------------------------------ */

  handle(req: HttpRequest<Any>): Observable<HttpEvent<Any>> | null {
    const path = this.pathOf(req);
    const method = req.method.toUpperCase();
    const body = (req.body ?? {}) as Any;

    const send = (value: Any) => of(new HttpResponse({ status: 200, body: value }) as HttpEvent<Any>);
    const created = (value: Any) =>
      of(new HttpResponse({ status: 201, body: value }) as HttpEvent<Any>);
    const noContent = () => of(new HttpResponse({ status: 204, body: null }) as unknown as HttpEvent<Any>);
    const notFound = () => throwError(() => ({ status: 404, error: { message: 'Resource not found' } }));

    /* ------------------------------ auth ----------------------------- */
    if (path === 'auth/login' && method === 'POST') {
      const email = String(body.email || '').toLowerCase();
      const user = MOCK_USERS.find(u => u.email === email) ?? MOCK_USERS[0];
      return send({ user, token: 'mock-jwt-token' });
    }
    if (path === 'auth/logout' && method === 'POST') return send({ success: true });
    if (path === 'auth/me' && method === 'GET') return send(MOCK_USERS[0]);
    if (path === 'auth/register' && method === 'POST') {
      return created({ user: { ...MOCK_USERS[0], email: body.email }, token: 'mock-jwt-token' });
    }

    /* ---------------------------- dashboard -------------------------- */
    if (path === 'dashboard/stats') return send(this.dashboardStats());
    if (path === 'dashboard/recent-activity') return send(this.recentActivity(Number(req.params.get('limit')) || 10));
    if (path === 'dashboard/occupancy-chart') return send(this.occupancyChart());
    if (path === 'dashboard/revenue-chart') return send(this.revenueChart());
    if (path === 'dashboard/expiring-leases-chart') return send(this.expiringLeasesChart());
    if (path.startsWith('dashboard/property-performance/')) {
      return send(this.propertyPerformance(Number(path.split('/').pop())) ?? null);
    }

    /* ---------------------------- properties ------------------------- */
    if (path === 'properties' && method === 'GET') return send(this.listProperties(req));
    if (path === 'properties' && method === 'POST') {
      const record = this.store('properties', body, (r, id) => ({
        ...r, id, occupancy_rate: 0, status: 'active',
        buildings_count: 0, units_count: 0, created_at: new Date().toISOString(), updated_at: new Date().toISOString()
      }));
      return created(record);
    }
    if (path === 'properties/export') return send(this.exportCsv(this.properties as any[], ['code', 'name', 'type', 'city', 'status']));
    if (path.startsWith('properties/')) {
      const rest = path.slice('properties/'.length);
      const id = Number(rest);
      const record = this.properties.find(p => p.id === id);
      if (!record) return notFound();
      if (rest.endsWith('/occupancy')) return send({ occupancy_rate: record.occupancy_rate });
      if (method === 'GET') return send(this.decorateProperty(record, req));
      if (method === 'PUT' || method === 'PATCH') return send(this.store('properties', body, () => ({ ...record, ...body, id, updated_at: new Date().toISOString() })));
      if (method === 'DELETE') {
        this.properties = this.properties.filter(p => p.id !== id);
        this.buildings = this.buildings.filter(b => b.property_id !== id);
        return noContent();
      }
    }

    /* ----------------------------- buildings ------------------------- */
    if (path === 'buildings' && method === 'GET') return send(this.listBuildings(req));
    if (path === 'buildings' && method === 'POST') {
      const record = this.store('buildings', body, (r, id) => {
        const property = this.properties.find(p => p.id === r.property_id);
        return {
          ...r, id, units_count: 0, status: 'active',
          property: property ? { id: property.id, name: property.name, code: property.code } : undefined,
          created_at: new Date().toISOString(), updated_at: new Date().toISOString()
        };
      });
      return created(record);
    }
    if (path.startsWith('buildings/')) {
      const id = Number(path.slice('buildings/'.length));
      const record = this.buildings.find(b => b.id === id);
      if (!record) return notFound();
      if (method === 'GET') return send(this.decorateBuilding(record));
      if (method === 'PUT' || method === 'PATCH') return send(this.store('buildings', body, () => ({ ...record, ...body, id, updated_at: new Date().toISOString() })));
      if (method === 'DELETE') {
        this.buildings = this.buildings.filter(b => b.id !== id);
        this.units = this.units.filter(u => u.building_id !== id);
        return noContent();
      }
    }

    /* ------------------------------- units --------------------------- */
    if (path === 'units' && method === 'GET') return send(this.listUnits(req));
    if (path === 'units' && method === 'POST') {
      const record = this.store('units', body, (r, id) => this.decorateUnit({
        ...r, id, status: 'vacant', current_lease_id: null, current_tenant_id: null,
        created_at: new Date().toISOString(), updated_at: new Date().toISOString()
      } as any));
      return created(record);
    }
    if (path.startsWith('units/')) {
      const rest = path.slice('units/'.length);
      const id = Number(rest);
      const record = this.units.find(u => u.id === id);
      if (!record) return notFound();
      if (rest.endsWith('/status') && (method === 'PATCH' || method === 'POST')) {
        return send(this.store('units', { status: body.status }, () => ({ ...record, status: body.status, updated_at: new Date().toISOString() })));
      }
      if (method === 'GET') return send(this.decorateUnit(record));
      if (method === 'PUT' || method === 'PATCH') return send(this.store('units', body, () => this.decorateUnit({ ...record, ...body, id, updated_at: new Date().toISOString() } as any)));
      if (method === 'DELETE') {
        this.units = this.units.filter(u => u.id !== id);
        return noContent();
      }
    }

    /* ------------------------------ tenants -------------------------- */
    if (path === 'tenants' && method === 'GET') return send(this.listTenants(req));
    if (path === 'tenants' && method === 'POST') {
      const record = this.store('tenants', body, (r, id) => ({
        ...r, id, status: 'active', leases_count: 0,
        created_at: new Date().toISOString(), updated_at: new Date().toISOString()
      }));
      return created(record);
    }
    if (path.startsWith('tenants/')) {
      const rest = path.slice('tenants/'.length);
      const [idPart, ...tail] = rest.split('/');
      const id = Number(idPart);
      const record = this.tenants.find(t => t.id === id);
      if (!record) return notFound();

      /* Documents live under /tenants/{id}/documents[/{docId}][/download]. */
      if (tail[0] === 'documents') {
        if (method === 'POST' && tail.length === 1) return this.addTenantDocument(id, req.body);
        if (method === 'GET' && tail.length === 1) {
          let rows = this.tenantDocuments.filter(d => d.tenant_id === id);
          const category = req.params.get('filter[category]');
          if (category) rows = rows.filter(d => d.category === category);
          const term = req.params.get('search')?.toLowerCase();
          if (term) rows = rows.filter(d => d.name.toLowerCase().includes(term));
          // Newest first unless a sort is requested, matching the backend.
          const sorted = req.params.get('sort')
            ? this.applySort(rows, req)
            : [...rows].sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)));
          return send(this.paginate(sorted, req));
        }
        const docId = Number(tail[1]);
        const doc = this.tenantDocuments.find(d => d.id === docId && d.tenant_id === id);
        if (!doc) return notFound();
        if (method === 'GET' && tail[2] === 'download') {
          // Returned unwrapped: `send()` would wrap the Blob in a second response.
          return of(new HttpResponse({
            status: 200,
            body: new Blob([`Mock content for ${doc.name}`], { type: doc.mime_type ?? 'application/octet-stream' })
          }) as HttpEvent<Any>);
        }
        if (method === 'DELETE') {
          this.tenantDocuments = this.tenantDocuments.filter(d => d.id !== docId);
          return noContent();
        }
        return notFound();
      }

      if (rest.endsWith('/leases')) return send(this.paginate(this.leases.filter(l => l.tenant_id === id), req));
      if (method === 'GET') return send({ ...record, leases: this.leases.filter(l => l.tenant_id === id), units: this.units.filter(u => u.current_tenant_id === id) });
      if (method === 'PUT' || method === 'PATCH') return send(this.store('tenants', body, () => ({ ...record, ...body, id, updated_at: new Date().toISOString() })));
      if (method === 'DELETE') {
        this.tenants = this.tenants.filter(t => t.id !== id);
        return noContent();
      }
    }

    /* ------------------------------- leases -------------------------- */
    if (path === 'leases' && method === 'GET') return send(this.listLeases(req));
    if (path === 'leases' && method === 'POST') {
      const record = this.store('leases', body, (r, id) => this.decorateLease({
        ...r, id, status: 'draft', signed_at: null, terminated_at: null,
        created_at: new Date().toISOString(), updated_at: new Date().toISOString()
      } as any));
      return created(record);
    }
    if (path.startsWith('leases/')) {
      const rest = path.slice('leases/'.length);
      const id = Number(rest.split('/')[0]);
      const record = this.leases.find(l => l.id === id);
      if (!record) return notFound();

      if (rest.endsWith('/sign')) {
        return send(this.store('leases', {}, () => this.decorateLease({
          ...record, status: 'active', signed_at: new Date().toISOString(), updated_at: new Date().toISOString()
        } as any)));
      }
      if (rest.endsWith('/terminate')) {
        return send(this.store('leases', {}, () => this.decorateLease({
          ...record, status: 'terminated', terminated_at: body.terminated_at ?? new Date().toISOString(), updated_at: new Date().toISOString()
        } as any)));
      }
      if (rest.endsWith('/renew')) {
        return send(this.store('leases', {}, () => this.decorateLease({
          ...record, status: 'renewed', end_date: body.end_date ?? record.end_date,
          rent_amount: Number(body.rent_amount ?? record.rent_amount), updated_at: new Date().toISOString()
        } as any)));
      }
      if (rest.endsWith('/document')) return send(this.exportCsv([record] as any[], ['code', 'status', 'start_date', 'end_date', 'rent_amount']));

      if (method === 'GET') {
        return send({
          ...this.decorateLease(record),
          invoices: this.invoices.filter(i => i.lease_id === id),
          payments: this.payments.filter(p => this.invoices.some(i => i.id === p.invoice_id && i.lease_id === id))
        });
      }
      if (method === 'PUT' || method === 'PATCH') return send(this.store('leases', body, () => this.decorateLease({ ...record, ...body, id, updated_at: new Date().toISOString() } as any)));
      if (method === 'DELETE') {
        this.leases = this.leases.filter(l => l.id !== id);
        this.invoices = this.invoices.filter(i => i.lease_id !== id);
        return noContent();
      }
    }

    /* ------------------------------ invoices ------------------------- */
    if (path === 'invoices' && method === 'GET') return send(this.listInvoices(req));
    if (path === 'invoices/bulk-generate' && method === 'POST') {
      const createdLeases: InvoiceRecord[] = (body.lease_ids ?? []).map((leaseId: number, i: number) => {
        const lease = this.leases.find(l => l.id === leaseId);
        const tenant = lease ? this.tenants.find(t => t.id === lease.tenant_id) : undefined;
        const record: InvoiceRecord = {
          id: this.seq('invoices') + i,
          lease_id: leaseId,
          property_id: lease?.property_id ?? 0,
          tenant_id: lease?.tenant_id ?? 0,
          code: `INV-2024-${String(this.seq('invoices') + i).padStart(5, '0')}`,
          type: 'rent',
          status: 'draft',
          issue_date: body.issue_date ?? new Date().toISOString().slice(0, 10),
          due_date: body.due_date ?? new Date().toISOString().slice(0, 10),
          paid_date: null,
          amount: lease?.rent_amount ?? 0,
          paid_amount: 0,
          balance: lease?.rent_amount ?? 0,
          currency: 'USD',
          description: 'Monthly rent',
          line_items: [{ description: 'Base rent', quantity: 1, unit_price: lease?.rent_amount ?? 0, amount: lease?.rent_amount ?? 0, tax_rate: 0, tax_amount: 0 }],
          lease: lease ? { id: lease.id, code: lease.code } : undefined,
          tenant: tenant ? { id: tenant.id, first_name: tenant.first_name, last_name: tenant.last_name } : undefined,
          property: this.properties.find(p => p.id === lease?.property_id),
          payments: [],
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };
        this.invoices.push(record);
        return record;
      });
      return created(createdLeases);
    }
    if (path.startsWith('invoices/')) {
      const rest = path.slice('invoices/'.length);
      const parts = rest.split('/');
      const id = Number(parts[0]);
      const record = this.invoices.find(i => i.id === id);
      if (!record) return notFound();

      if (parts[1] === 'payments') {
        if (method === 'POST') {
          const payment: PaymentRecord = {
            id: this.payments.length + 1,
            invoice_id: id,
            amount: Number(body.amount || 0),
            payment_date: body.payment_date ?? new Date().toISOString().slice(0, 10),
            payment_method: body.payment_method ?? 'bank_transfer',
            reference: body.reference ?? `PMT-${Date.now().toString().slice(-6)}`,
            notes: body.notes ?? '',
            created_at: new Date().toISOString()
          };
          this.payments.push(payment);
          record.payments = [...(record.payments ?? []), payment];
          record.paid_amount += payment.amount;
          record.balance = Math.max(0, record.amount - record.paid_amount);
          if (record.balance === 0) {
            record.status = 'paid';
            record.paid_date = payment.payment_date;
          } else {
            record.status = 'partial';
          }
          record.updated_at = new Date().toISOString();
          return created(payment);
        }
        if (method === 'GET') return send(this.paginate(record.payments ?? [], req));
      }
      if (parts[1] === 'send') {
        return send(this.store('invoices', {}, () => ({ ...record, status: 'sent', updated_at: new Date().toISOString() })));
      }
      if (parts[1] === 'pdf') return send(this.exportCsv([record] as any[], ['code', 'status', 'amount', 'balance']));

      if (method === 'GET') return send({ ...record, payments: record.payments ?? [] });
      if (method === 'PUT' || method === 'PATCH') {
        return send(this.store('invoices', body, () => {
          const next = { ...record, ...body, id, balance: Number((body.amount ?? record.amount)) - record.paid_amount, updated_at: new Date().toISOString() };
          return next;
        }));
      }
      if (method === 'DELETE') {
        this.invoices = this.invoices.filter(i => i.id !== id);
        return noContent();
      }
    }

    /* ---------------------- operational modules ---------------------- */
    const statsMatch = path.match(/^([a-z-]+)\/stats$/);
    if (statsMatch) {
      const entity = MODULE_ENDPOINTS[statsMatch[1]];
      if (entity) return send(this.moduleStats(statsMatch[1], entity));
    }

    const collection = MODULE_ENDPOINTS[path];
    if (collection) {
      if (method === 'GET') return send(this.listModule(collection, req));
      if (method === 'POST') {
        return created(this.store(collection, body, (r, id) => ({
          ...r, id, created_at: new Date().toISOString(), updated_at: new Date().toISOString()
        })));
      }
    }

    const itemMatch = path.match(/^([a-z-]+)\/(\d+)(?:\/([a-z-]+))?$/);
    if (itemMatch) {
      const [, key, rawId, action] = itemMatch;
      const entity = MODULE_ENDPOINTS[key];
      if (entity) {
        const id = Number(rawId);
        const rows = (this as Any)[entity] as Any[];
        const record = rows.find(r => r.id === id);
        if (!record) return notFound();

        if (action) {
          const transition = MODULE_ACTIONS[key]?.[action];
          if (!transition) return throwError(() => ({ status: 422, error: { message: `Unsupported action "${action}" for ${key}` } }));
          return send(this.update(entity, id, transition(record, body)));
        }
        if (method === 'GET') return send(record);
        if (method === 'PUT' || method === 'PATCH') return send(this.update(entity, id, body));
        if (method === 'DELETE') {
          (this as Any)[entity] = rows.filter(r => r.id !== id);
          return noContent();
        }
      }
    }

    return null;
  }

  /* ------------------------------------------------------------------ */
  /* Stores & helpers                                                    */
  /* ------------------------------------------------------------------ */

  /**
   * Record an uploaded tenant document. Mirrors the backend's
   * POST /tenants/{id}/documents, including the multipart payload: the file
   * itself is not kept, only the metadata the API returns.
   */
  private addTenantDocument(tenantId: number, body: Any): Observable<HttpEvent<Any>> {
    const form = body instanceof FormData ? body : null;
    const file = form?.get('file');

    if (!(file instanceof Blob)) {
      return throwError(() => ({
        status: 422,
        error: { message: 'The file field is required.', errors: { file: ['The file field is required.'] } }
      }));
    }

    const originalName = (file as File).name || 'upload';
    const extension = originalName.split('.').pop()?.toLowerCase() ?? '';
    const stamp = new Date().toISOString();

    const record = this.store('tenantDocuments', {}, (r, id) => ({
      ...r,
      id,
      tenant_id: tenantId,
      name: (form?.get('name') as string) || originalName,
      category: (form?.get('category') as string) || 'other',
      disk: 'documents',
      path: `tenants/${tenantId}/${stamp.replace(/[:.]/g, '-')}-${originalName}`,
      mime_type: file.type || 'application/octet-stream',
      size_kb: Math.max(1, Math.round(file.size / 1024)),
      uploaded_by: 1,
      url: '',
      extension,
      created_at: stamp,
      updated_at: stamp
    }));

    return of(new HttpResponse({ status: 201, body: record }) as HttpEvent<Any>);
  }

  private store(entity: string, payload: Any, build: (record: Any, id: number) => Any): Any {
    const id = this.seq(entity);
    const record = build({ ...payload }, id);
    ((this as Any)[entity] as Any[]).push(record);
    return record;
  }

  /** Merge `payload` into an existing record, keeping its id and stamping `updated_at`. */
  private update(entity: string, id: number, payload: Any): Any {
    const rows = (this as Any)[entity] as Any[];
    const index = rows.findIndex(r => r.id === id);
    if (index < 0) return null;
    rows[index] = { ...rows[index], ...payload, id, updated_at: new Date().toISOString() };
    return rows[index];
  }

  /**
   * Resource path for a request, e.g. `bounced-cheques/12/escalate` for
   * `http://localhost:8000/api/bounced-cheques/12/escalate`. The origin and the
   * API base segment are dropped so the router can match on the path alone.
   */
  private pathOf(req: HttpRequest<Any>): string {
    const withoutOrigin = req.url.split('?')[0].replace(/^[a-z][a-z0-9+.-]*:\/\/[^/]*/i, '');
    const segments = withoutOrigin.split('/').filter(Boolean);
    if (segments[0] === 'api') segments.shift();
    return segments.join('/');
  }

  private search(rows: any[], term: string | null): any[] {
    if (!term) return rows;
    const q = term.toLowerCase();
    return rows.filter(r => Object.values(r).some(v => v != null && String(v).toLowerCase().includes(q)));
  }

  private applyFilters(rows: any[], req: HttpRequest<Any>): any[] {
    let out = [...rows];
    req.params.keys().forEach(key => {
      if (!key.startsWith('filter[')) return;
      const field = key.slice(7, -1);
      const value = req.params.get(key);
      if (value == null || value === '') return;
      if (field === 'expiring_within') {
        const days = Number(value);
        const limit = Date.now() + days * DAY;
        out = out.filter(r => {
          const end = new Date(String(r.end_date)).getTime();
          return r.status === 'active' && end <= limit && end >= Date.now();
        });
        return;
      }
      if (field === 'due_before') {
        out = out.filter(r => new Date(String(r.due_date)).getTime() <= new Date(value).getTime());
        return;
      }
      if (field === 'min_amount') { out = out.filter(r => Number(r.amount) >= Number(value)); return; }
      if (field === 'max_amount') { out = out.filter(r => Number(r.amount) <= Number(value)); return; }
      if (field === 'max_days_to_expiry') { out = out.filter(r => Number(r.days_to_expiry) <= Number(value)); return; }
      if (field === 'has_outstanding') { out = out.filter(r => Number(r.outstanding) > 0); return; }
      if (field === 'overdue') { out = out.filter(r => new Date(String(r.due_date ?? r.next_action_due)).getTime() < Date.now()); return; }
      out = out.filter(r => String(r[field]) === String(value));
    });
    return out;
  }

  private applySort(rows: any[], req: HttpRequest<Any>): any[] {
    const field = req.params.get('sort');
    if (!field) return rows;
    const dir = req.params.get('direction') === 'asc' ? 1 : -1;
    return [...rows].sort((a, b) => {
      const av = a[field];
      const bv = b[field];
      if (av == null) return 1;
      if (bv == null) return -1;
      if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
      return String(av).localeCompare(String(bv)) * dir;
    });
  }

  private paginate(rows: any[], req: HttpRequest<Any>) {
    const total = rows.length;
    const perPage = Number(req.params.get('per_page')) || 15;
    const currentPage = Number(req.params.get('page')) || 1;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const safePage = Math.min(currentPage, lastPage);
    const start = (safePage - 1) * perPage;
    return {
      data: rows.slice(start, start + perPage),
      meta: { total, per_page: perPage, current_page: safePage, last_page: lastPage }
    };
  }

  private listProperties(req: HttpRequest<Any>) {
    let rows = this.applySort(this.applyFilters(this.search(this.properties, req.params.get('search')), req), req);
    rows = rows.map(p => ({ ...p, buildings_count: this.buildings.filter(b => b.property_id === p.id).length, units_count: this.units.filter(u => u.property_id === p.id).length }));
    return this.paginate(rows, req);
  }

  private decorateProperty(p: PropertyRecord, req?: HttpRequest<Any>) {
    return {
      ...p,
      buildings: req?.params.get('include')?.includes('buildings')
        ? this.buildings.filter(b => b.property_id === p.id)
        : undefined,
      units: req?.params.get('include')?.includes('units')
        ? this.units.filter(u => u.property_id === p.id)
        : undefined
    };
  }

  private listBuildings(req: HttpRequest<Any>) {
    const rows = this.applySort(this.applyFilters(this.search(this.buildings, req.params.get('search')), req), req);
    return this.paginate(rows.map(b => ({ ...b, property: this.properties.find(p => p.id === b.property_id) })), req);
  }

  private decorateBuilding(b: BuildingRecord) {
    return {
      ...b,
      property: this.properties.find(p => p.id === b.property_id),
      units: this.units.filter(u => u.building_id === b.id)
    };
  }

  private decorateUnit(u: UnitRecord) {
    const building = this.buildings.find(b => b.id === u.building_id);
    const tenant = this.tenants.find(t => t.id === u.current_tenant_id);
    const lease = this.leases.find(l => l.id === u.current_lease_id);
    return {
      ...u,
      building: building ? { id: building.id, name: building.name, code: building.code } : undefined,
      property: this.properties.find(p => p.id === u.property_id),
      tenant: tenant ? { id: tenant.id, first_name: tenant.first_name, last_name: tenant.last_name, company: tenant.company } : undefined,
      lease: lease ? { id: lease.id, code: lease.code, start_date: lease.start_date, end_date: lease.end_date, rent_amount: lease.rent_amount } : undefined
    };
  }

  private listUnits(req: HttpRequest<Any>) {
    const rows = this.applySort(this.applyFilters(this.search(this.units, req.params.get('search')), req), req);
    return this.paginate(rows.map(u => this.decorateUnit(u)), req);
  }

  private listTenants(req: HttpRequest<Any>) {
    const rows = this.applySort(this.applyFilters(this.search(this.tenants, req.params.get('search')), req), req);
    return this.paginate(rows.map(t => ({ ...t, leases_count: this.leases.filter(l => l.tenant_id === t.id).length })), req);
  }

  private decorateLease(l: LeaseRecord) {
    const tenant = this.tenants.find(t => t.id === l.tenant_id);
    return {
      ...l,
      property: this.properties.find(p => p.id === l.property_id),
      building: this.buildings.find(b => b.id === l.building_id),
      unit: this.units.find(u => u.id === l.unit_id),
      tenant: tenant ? { id: tenant.id, first_name: tenant.first_name, last_name: tenant.last_name, company: tenant.company, email: tenant.email, phone: tenant.phone } : undefined
    };
  }

  private listLeases(req: HttpRequest<Any>) {
    const rows = this.applySort(this.applyFilters(this.search(this.leases, req.params.get('search')), req), req);
    return this.paginate(rows.map(l => this.decorateLease(l)), req);
  }

  private listInvoices(req: HttpRequest<Any>) {
    const rows = this.applySort(this.applyFilters(this.search(this.invoices, req.params.get('search')), req), req);
    return this.paginate(rows.map(i => this.decorateInvoice(i)), req);
  }

  private listModule(entity: string, req: HttpRequest<Any>) {
    const rows = this.applySort(this.applyFilters(this.search(this[entity as keyof MockDb] as Any[], req.params.get('search')), req), req);
    return this.paginate(rows, req);
  }

  /**
   * Stat payload for a module, derived from the live collection so edits made in
   * the UI are reflected in the cards immediately.
   */
  private moduleStats(key: string, entity: string) {
    const rows = (this as Any)[entity] as Any[];
    const statusField = MODULE_STATUS_FIELD[key] ?? 'status';
    const count = (field: string, prefixFor?: (value: string) => string): Record<string, number> => {
      const out: Record<string, number> = {};
      rows.forEach(r => {
        const s = String(r[field] ?? 'unknown');
        const prefix = prefixFor?.(s) ?? '';
        const k = prefix ? `${prefix}_${s}` : s;
        out[k] = (out[k] ?? 0) + 1;
      });
      return out;
    };

    const by_status = count(statusField);
    const secondary = MODULE_SECONDARY_STATUS[key];
    if (secondary) {
      // `noc_status` values already read `noc_issued`, so only prefix when needed.
      const prefix = secondary.split('_')[0];
      Object.assign(by_status, count(secondary, (value) => (value.startsWith(`${prefix}_`) ? '' : prefix)));
    }

    const attentionStatuses = MODULE_ATTENTION[key] ?? [];
    const sums: Record<string, number> = {};
    (MODULE_SUM_FIELDS[key] ?? []).forEach(field => {
      sums[field] = rows.reduce((total, r) => total + Number(r[field] || 0), 0);
    });

    return {
      total: rows.length,
      attention: rows.filter(r => attentionStatuses.includes(String(r[statusField]))).length,
      by_status,
      ...by_status,
      ...sums
    };
  }

  private decorateInvoice(i: InvoiceRecord) {
    const lease = this.leases.find(l => l.id === i.lease_id);
    const tenant = this.tenants.find(t => t.id === i.tenant_id);
    return {
      ...i,
      lease: lease ? { id: lease.id, code: lease.code, rent_amount: lease.rent_amount, start_date: lease.start_date, end_date: lease.end_date } : undefined,
      tenant: tenant ? { id: tenant.id, first_name: tenant.first_name, last_name: tenant.last_name, company: tenant.company, email: tenant.email } : undefined,
      property: this.properties.find(p => p.id === i.property_id),
      payments: i.payments ?? []
    };
  }

  private exportCsv(rows: Any[], columns: string[]): string {
    const header = columns.join(',');
    const lines = rows.map(r => columns.map(c => `"${String((r as Any)[c] ?? '').replace(/"/g, '""')}"`).join(','));
    return [header, ...lines].join('\n');
  }

  /* ------------------------------------------------------------------ */
  /* Dashboard aggregations                                              */
  /* ------------------------------------------------------------------ */

  private daysUntil(date: string): number {
    return Math.round((new Date(date).getTime() - Date.now()) / DAY);
  }

  dashboardStats() {
    const activeLeases = this.leases.filter(l => l.status === 'active');
    const expiring30 = activeLeases.filter(l => this.daysUntil(l.end_date) <= 30).length;
    const expiring90 = activeLeases.filter(l => this.daysUntil(l.end_date) <= 90).length;
    const paidInvoices = this.invoices.filter(i => i.status === 'paid');
    const month = new Date().toISOString().slice(0, 7);
    const lastMonthDate = new Date();
    lastMonthDate.setMonth(lastMonthDate.getMonth() - 1);
    const lastMonth = lastMonthDate.toISOString().slice(0, 7);

    return {
      properties: {
        total: this.properties.length,
        active: this.properties.filter(p => p.status === 'active').length,
        inactive: this.properties.filter(p => p.status === 'inactive').length
      },
      buildings: {
        total: this.buildings.length,
        active: this.buildings.filter(b => b.status === 'active').length,
        under_maintenance: this.buildings.filter(b => b.status === 'under_maintenance').length
      },
      units: {
        total: this.units.length,
        vacant: this.units.filter(u => u.status === 'vacant').length,
        occupied: this.units.filter(u => u.status === 'occupied').length,
        reserved: this.units.filter(u => u.status === 'reserved').length
      },
      tenants: {
        total: this.tenants.length,
        active: this.tenants.filter(t => t.status === 'active').length,
        prospects: this.tenants.filter(t => t.status === 'prospect').length
      },
      leases: {
        total: this.leases.length,
        active: activeLeases.length,
        expiring_30: expiring30,
        expiring_90: expiring90,
        expired: this.leases.filter(l => l.status === 'expired').length
      },
      invoices: {
        total: this.invoices.length,
        paid: paidInvoices.length,
        pending: this.invoices.filter(i => i.status === 'sent' || i.status === 'partial').length,
        overdue: this.invoices.filter(i => i.status === 'overdue').length,
        total_outstanding: this.invoices.reduce((sum, i) => sum + (i.status === 'cancelled' ? 0 : i.balance), 0)
      },
      revenue: {
        this_month: this.invoices.filter(i => i.paid_date?.startsWith(month)).reduce((s, i) => s + i.paid_amount, 0),
        last_month: this.invoices.filter(i => i.paid_date?.startsWith(lastMonth)).reduce((s, i) => s + i.paid_amount, 0),
        ytd: this.invoices.filter(i => i.status === 'paid' && i.paid_date?.startsWith(String(new Date().getFullYear()))).reduce((s, i) => s + i.paid_amount, 0),
        outstanding: this.invoices.reduce((s, i) => s + (i.status === 'cancelled' ? 0 : i.balance), 0)
      }
    };
  }

  recentActivity(limit: number) {
    const types = ['lease_created', 'payment_received', 'invoice_overdue', 'tenant_added', 'maintenance_request', 'lease_expiring', 'lease_signed'] as const;
    const titles: Record<string, [string, string]> = {
      lease_created: ['New lease created', 'Lease draft prepared for a vacant unit'],
      payment_received: ['Payment received', 'Rent payment settled by tenant'],
      invoice_overdue: ['Invoice overdue', 'Invoice passed its due date without full payment'],
      tenant_added: ['Tenant added', 'New contact added to the directory'],
      maintenance_request: ['Maintenance request', 'Unit reported for repair work'],
      lease_expiring: ['Lease expiring soon', 'Lease end date is within the next 60 days'],
      lease_signed: ['Lease signed', 'All parties signed the lease agreement']
    };
    const out: Any[] = [];
    for (let i = 0; i < limit; i++) {
      const type = types[i % types.length];
      const [title, description] = titles[type];
      out.push({
        id: i + 1,
        type,
        title,
        description,
        timestamp: new Date(Date.now() - (i * 3.4 + 0.5) * 3600000).toISOString(),
        related_id: (i % 12) + 1,
        related_type: 'lease'
      });
    }
    return out;
  }

  private lastMonths(n: number): string[] {
    const out: string[] = [];
    const d = new Date();
    d.setDate(1);
    for (let i = n - 1; i >= 0; i--) {
      const m = new Date(d);
      m.setMonth(m.getMonth() - i);
      out.push(m.toLocaleDateString('en-US', { month: 'short' }));
    }
    return out;
  }

  occupancyChart() {
    const labels = this.lastMonths(12);
    return {
      labels,
      datasets: [
        { label: 'Occupied units', data: labels.map((_, i) => 92 + ((i * 3) % 7)), backgroundColor: '#4ECDC4', borderColor: '#3DBEB5' },
        { label: 'Vacant units', data: labels.map((_, i) => 6 + ((i * 2) % 6)), backgroundColor: '#FFE66D', borderColor: '#F5D44E' }
      ]
    };
  }

  revenueChart() {
    const labels = this.lastMonths(12);
    const paid = labels.map((_, i) => 120000 + ((i * 17500) % 60000));
    return {
      labels,
      datasets: [
        { label: 'Collected', data: paid, backgroundColor: 'rgba(78,205,196,.85)', borderColor: '#3DBEB5' },
        { label: 'Outstanding', data: paid.map(v => Math.round(v * 0.18)), backgroundColor: 'rgba(255,107,107,.85)', borderColor: '#E85D5D' }
      ]
    };
  }

  expiringLeasesChart() {
    const labels = this.lastMonths(6);
    return {
      labels,
      datasets: [{
        label: 'Leases expiring',
        data: labels.map((_, i) => 2 + ((i * 2) % 5)),
        backgroundColor: 'rgba(255,230,109,.8)',
        borderColor: '#F5D44E'
      }]
    };
  }

  propertyPerformance(propertyId: number) {
    const property = this.properties.find(p => p.id === propertyId);
    if (!property) return null;
    const units = this.units.filter(u => u.property_id === propertyId);
    const leases = this.leases.filter(l => l.property_id === propertyId);
    return {
      property,
      totals: {
        units: units.length,
        occupied: units.filter(u => u.status === 'occupied').length,
        leases: leases.length,
        active_leases: leases.filter(l => l.status === 'active').length
      },
      occupancy_rate: property.occupancy_rate,
      monthly_rent_roll: leases.filter(l => l.status === 'active').reduce((s, l) => s + l.rent_amount, 0)
    };
  }
}
