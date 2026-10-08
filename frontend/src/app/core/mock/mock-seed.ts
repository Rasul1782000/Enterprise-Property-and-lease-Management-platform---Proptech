export interface PropertyRecord {
  id: number; code: string; name: string; type: string; address: string; city: string;
  state: string; zip: string; country: string; occupancy_rate: number;
  status: 'active' | 'inactive'; buildings_count: number; units_count: number;
  created_at: string; updated_at: string;
}

export interface BuildingRecord {
  id: number; property_id: number; code: string; name: string; address: string;
  city: string; state: string; zip: string; floors: number; units_count: number;
  status: 'active' | 'inactive' | 'under_maintenance';
  property?: { id: number; name: string; code: string };
  created_at: string; updated_at: string;
}

export interface UnitRecord {
  id: number; building_id: number; property_id: number; code: string; name: string;
  type: 'residential' | 'commercial' | 'office' | 'retail' | 'warehouse';
  floor: number; area_sqft: number; bedrooms: number; bathrooms: number; base_rent: number;
  status: 'vacant' | 'occupied' | 'reserved' | 'under_maintenance';
  current_lease_id: number | null; current_tenant_id: number | null;
  building?: { id: number; name: string; code: string };
  property?: { id: number; name: string; code: string };
  created_at: string; updated_at: string;
}

export interface TenantRecord {
  id: number; code: string; first_name: string; last_name: string; email: string;
  phone: string; company: string; tax_id: string;
  status: 'active' | 'inactive' | 'prospect' | 'former';
  emergency_contact_name: string; emergency_contact_phone: string; notes: string;
  leases_count: number; created_at: string; updated_at: string;
}

export interface LeaseRecord {
  id: number; property_id: number; building_id: number; unit_id: number; tenant_id: number;
  code: string; type: 'fixed' | 'periodic' | 'commercial' | 'residential';
  status: 'draft' | 'active' | 'expired' | 'terminated' | 'renewed';
  start_date: string; end_date: string; rent_amount: number; deposit_amount: number;
  payment_frequency: 'monthly' | 'quarterly' | 'annually';
  escalation_clause: string; renewal_options: number; terms: string;
  signed_at: string | null; terminated_at: string | null;
  property?: any; building?: any; unit?: any; tenant?: any;
  invoices?: any[]; payments?: any[];
  created_at: string; updated_at: string;
}

export interface InvoiceLineItemRecord {
  id?: number; description: string; quantity: number; unit_price: number;
  amount: number; tax_rate: number; tax_amount: number;
}

export interface PaymentRecord {
  id: number; invoice_id: number; amount: number; payment_date: string;
  payment_method: 'cash' | 'check' | 'bank_transfer' | 'card' | 'online';
  reference: string; notes: string; created_at: string;
}

export interface InvoiceRecord {
  id: number; lease_id: number; property_id: number; tenant_id: number; code: string;
  type: 'rent' | 'deposit' | 'late_fee' | 'utility' | 'maintenance' | 'other';
  status: 'draft' | 'sent' | 'paid' | 'partial' | 'overdue' | 'cancelled';
  issue_date: string; due_date: string; paid_date: string | null;
  amount: number; paid_amount: number; balance: number; currency: string;
  description: string; line_items: InvoiceLineItemRecord[];
  lease?: any; tenant?: any; property?: any; payments?: PaymentRecord[];
  created_at: string; updated_at: string;
}

const DAY = 86400000;

function iso(days: number, hour = 9): string {
  const d = new Date(Date.now() + days * DAY);
  d.setHours(hour, 0, 0, 0);
  return d.toISOString();
}

function isoDate(days: number): string {
  return iso(days).slice(0, 10);
}

const NOW = new Date().toISOString();

export const MOCK_PROPERTIES: PropertyRecord[] = [
  { id: 1, code: 'PRP-1001', name: 'Harbor Point Residences', type: 'multifamily', address: '1200 Harbor View Blvd', city: 'Seattle', state: 'WA', zip: '98101', country: 'USA', occupancy_rate: 94.5, status: 'active', buildings_count: 3, units_count: 48, created_at: iso(-720), updated_at: iso(-6) },
  { id: 2, code: 'PRP-1002', name: 'Cedar Grove Apartments', type: 'multifamily', address: '845 Cedar Grove Ln', city: 'Portland', state: 'OR', zip: '97205', country: 'USA', occupancy_rate: 88.2, status: 'active', buildings_count: 2, units_count: 32, created_at: iso(-640), updated_at: iso(-3) },
  { id: 3, code: 'PRP-1003', name: 'Meridian Business Park', type: 'commercial', address: '4800 Meridian Pkwy', city: 'Bellevue', state: 'WA', zip: '98004', country: 'USA', occupancy_rate: 76.8, status: 'active', buildings_count: 2, units_count: 24, created_at: iso(-540), updated_at: iso(-9) },
  { id: 4, code: 'PRP-1004', name: 'Sunset Retail Center', type: 'retail', address: '77 Sunset Highway', city: 'Tacoma', state: 'WA', zip: '98402', country: 'USA', occupancy_rate: 61.4, status: 'active', buildings_count: 1, units_count: 12, created_at: iso(-410), updated_at: iso(-21) },
  { id: 5, code: 'PRP-1005', name: 'Northgate Industrial', type: 'industrial', address: '2250 Northgate Way', city: 'Everett', state: 'WA', zip: '98201', country: 'USA', occupancy_rate: 100, status: 'active', buildings_count: 1, units_count: 8, created_at: iso(-380), updated_at: iso(-30) },
  { id: 6, code: 'PRP-1006', name: 'Lakeside Villas', type: 'multifamily', address: '9 Lakeshore Terrace', city: 'Bellingham', state: 'WA', zip: '98225', country: 'USA', occupancy_rate: 0, status: 'inactive', buildings_count: 1, units_count: 10, created_at: iso(-300), updated_at: iso(-120) },
  { id: 7, code: 'PRP-1007', name: 'Summit Plaza Offices', type: 'office', address: '310 Summit Plaza', city: 'Spokane', state: 'WA', zip: '99201', country: 'USA', occupancy_rate: 82.0, status: 'active', buildings_count: 1, units_count: 16, created_at: iso(-260), updated_at: iso(-14) }
];

const BUILDING_DEFS: [number, number, string, string, number, BuildingRecord['status']][] = [
  [1, 12, 'Harbor Tower A', 'HB-A', 20, 'active'],
  [1, 8, 'Harbor Tower B', 'HB-B', 16, 'active'],
  [1, 4, 'Harbor Annex', 'HB-C', 12, 'under_maintenance'],
  [2, 6, 'Cedar Grove North', 'CG-N', 18, 'active'],
  [2, 4, 'Cedar Grove South', 'CG-S', 14, 'active'],
  [3, 9, 'Meridian Tower', 'MD-T', 14, 'active'],
  [3, 3, 'Meridian Flex', 'MD-F', 10, 'active'],
  [4, 2, 'Sunset Plaza', 'SP-1', 12, 'active'],
  [5, 1, 'Northgate Shed', 'NG-1', 8, 'active'],
  [6, 3, 'Lakeside Lodge', 'LK-1', 10, 'inactive'],
  [7, 5, 'Summit Plaza', 'SM-1', 16, 'active']
];

export const MOCK_BUILDINGS: BuildingRecord[] = BUILDING_DEFS.map((def, i) => {
  const [propertyId, floors, name, code, unitsCount, status] = def;
  const property = MOCK_PROPERTIES.find(p => p.id === propertyId)!;
  return {
    id: i + 1,
    property_id: propertyId,
    code,
    name,
    address: property.address,
    city: property.city,
    state: property.state,
    zip: property.zip,
    floors,
    units_count: unitsCount,
    status,
    property: { id: property.id, name: property.name, code: property.code },
    created_at: property.created_at,
    updated_at: property.updated_at
  };
});

const UNIT_TYPES: UnitRecord['type'][] = ['residential', 'residential', 'residential', 'office', 'retail', 'commercial', 'warehouse'];
const UNIT_STATUSES: UnitRecord['status'][] = ['occupied', 'occupied', 'occupied', 'vacant', 'reserved', 'occupied', 'under_maintenance'];

export const MOCK_UNITS: UnitRecord[] = (() => {
  const out: UnitRecord[] = [];
  let id = 0;
  for (const b of MOCK_BUILDINGS) {
    const isResidential = b.code.startsWith('HB') || b.code.startsWith('CG') || b.code.startsWith('LK');
    for (let u = 1; u <= b.units_count; u++) {
      id++;
      const type = isResidential ? 'residential' : UNIT_TYPES[(id + b.id) % UNIT_TYPES.length];
      const status = b.status === 'inactive'
        ? 'vacant'
        : (b.status === 'under_maintenance' && u <= 2 ? 'under_maintenance' : UNIT_STATUSES[(id * 3 + b.id) % UNIT_STATUSES.length]);
      const area = isResidential ? 620 + (u % 5) * 90 : 1400 + (u % 7) * 260;
      out.push({
        id,
        building_id: b.id,
        property_id: b.property_id,
        code: `${b.code}-${String(u).padStart(2, '0')}`,
        name: isResidential ? `Unit ${u}` : `Suite ${u}`,
        type,
        floor: isResidential ? (u % b.floors) + 1 : 1,
        area_sqft: area,
        bedrooms: isResidential ? 1 + (u % 3) : 0,
        bathrooms: isResidential ? 1 + (u % 2) : 2,
        base_rent: (isResidential ? 1450 : 2600) + (u % 6) * 175,
        status,
        current_lease_id: null,
        current_tenant_id: null,
        building: { id: b.id, name: b.name, code: b.code },
        property: { id: b.property_id, name: b.property!.name, code: b.property!.code },
        created_at: b.created_at,
        updated_at: b.updated_at
      });
    }
  }
  return out;
})();

const TENANT_DEFS: [string, string, string, TenantRecord['status'], number][] = [
  ['Amelia', 'Hartley', 'Hartley Legal Group', 'active', 1],
  ['Noah', 'Bennett', 'Bennett & Co Consulting', 'active', 0],
  ['Priya', 'Raman', 'Raman Diagnostics', 'active', 1],
  ['Lucas', 'Moreau', 'Moreau Design Studio', 'active', 0],
  ['Sofia', 'Alvarez', 'Alvarez Logistics', 'active', 1],
  ['Ethan', 'Whitfield', 'Whitfield Dental', 'active', 0],
  ['Grace', 'Lindqvist', 'Lindqvist Imports', 'active', 1],
  ['Omar', 'Haddad', 'Haddad Textiles', 'active', 0],
  ['Maya', 'Kowalski', 'Kowalski Bakery', 'active', 1],
  ['Daniel', 'Osei', 'Osei Fitness', 'active', 0],
  ['Hannah', 'Feldman', 'Feldman Legal', 'active', 1],
  ['Tobias', 'Nguyen', 'Nguyen Motors', 'active', 0],
  ['Isabel', 'Ferreira', 'Ferreira Cafe', 'active', 0],
  ['Julian', 'Ashworth', 'Ashworth Ventures', 'former', 2],
  ['Clara', 'Novak', 'Novak Interiors', 'active', 1],
  ['Rashid', 'Khan', 'Khan Pharmacy', 'active', 0],
  ['Elena', 'Petrova', 'Petrova Media', 'prospect', 0],
  ['Marcus', 'Delgado', 'Delgado Construction', 'prospect', 0],
  ['Yuki', 'Tanaka', 'Tanaka Robotics', 'active', 1],
  ['Fiona', 'Gallagher', 'Gallagher Accounting', 'active', 0],
  ['Samuel', 'Okafor', 'Okafor Logistics', 'active', 1],
  ['Nadia', 'Rahman', 'Rahman Consulting', 'inactive', 0],
  ['Victor', 'Larsen', 'Larsen Marine', 'active', 0],
  ['Alice', 'Moreau', 'Moreau Design Studio', 'active', 0]
];

export const MOCK_TENANTS: TenantRecord[] = TENANT_DEFS.map((def, i) => {
  const [first, last, company, status, leases] = def;
  return {
    id: i + 1,
    code: `TEN-${2000 + i + 1}`,
    first_name: first,
    last_name: last,
    email: `${first.toLowerCase()}.${last.toLowerCase()}@example.com`,
    phone: `+1 (206) 555-${String(1000 + i * 7).slice(-4)}`,
    company,
    tax_id: `${82 + i}-${4000000 + i * 137}`,
    status,
    emergency_contact_name: `${last} Contact`,
    emergency_contact_phone: `+1 (425) 555-${String(2000 + i * 3).slice(-4)}`,
    notes: i % 4 === 0 ? 'Prefers email communication. Renews early.' : '',
    leases_count: leases,
    created_at: iso(-500 + i * 11),
    updated_at: iso(-40 + i * 3)
  };
});

const LEASE_TYPES: LeaseRecord['type'][] = ['fixed', 'fixed', 'residential', 'commercial', 'fixed', 'periodic'];
const FREQUENCIES: LeaseRecord['payment_frequency'][] = ['monthly', 'monthly', 'monthly', 'quarterly', 'annually'];

const END_OFFSETS = [12, 21, 28, 45, 62, 78, 88, 120, 155, 200, 240, 300, 360, 420, -20, -60, -110, 640];

export const MOCK_LEASES: LeaseRecord[] = (() => {
  const out: LeaseRecord[] = [];
  const occupiable = MOCK_UNITS.filter(u => u.status === 'occupied' || u.status === 'reserved');
  let id = 0;
  for (let i = 0; i < 40; i++) {
    id++;
    const unit = occupiable[i % occupiable.length];
    const tenant = MOCK_TENANTS[i % MOCK_TENANTS.length];
    const endOffset = END_OFFSETS[i % END_OFFSETS.length];
    const duration = 365;
    const signed = endOffset - duration;
    const status: LeaseRecord['status'] = endOffset < 0
      ? (i % 2 === 0 ? 'expired' : 'terminated')
      : 'active';
    out.push({
      id,
      property_id: unit.property_id,
      building_id: unit.building_id,
      unit_id: unit.id,
      tenant_id: tenant.id,
      code: `LS-${5000 + id}`,
      type: LEASE_TYPES[i % LEASE_TYPES.length],
      status,
      start_date: isoDate(signed),
      end_date: isoDate(endOffset),
      rent_amount: unit.base_rent,
      deposit_amount: unit.base_rent,
      payment_frequency: FREQUENCIES[i % FREQUENCIES.length],
      escalation_clause: i % 3 === 0 ? '3% annual increase on each anniversary' : '',
      renewal_options: (i % 2) + 1,
      terms: 'Standard 12-month commercial terms. Tenant responsible for utilities and routine maintenance.',
      signed_at: status === 'active' ? isoDate(signed + 3) : null,
      terminated_at: status === 'terminated' ? isoDate(endOffset) : null,
      property: MOCK_PROPERTIES.find(p => p.id === unit.property_id),
      building: MOCK_BUILDINGS.find(b => b.id === unit.building_id),
      unit: { id: unit.id, code: unit.code, name: unit.name, type: unit.type },
      tenant: { id: tenant.id, first_name: tenant.first_name, last_name: tenant.last_name, company: tenant.company, email: tenant.email },
      created_at: isoDate(signed),
      updated_at: iso(-i)
    });
  }
  return out;
})();

MOCK_LEASES.forEach(l => {
  if (l.status === 'active' || l.status === 'expired') {
    const unit = MOCK_UNITS.find(u => u.id === l.unit_id);
    if (unit && l.status === 'active') {
      unit.current_lease_id = l.id;
      unit.current_tenant_id = l.tenant_id;
    }
  }
});

function monthKey(offset: number): string {
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export const MOCK_INVOICES: InvoiceRecord[] = (() => {
  const out: InvoiceRecord[] = [];
  let id = 0;
  const activeLeases = MOCK_LEASES.filter(l => l.status === 'active' || l.status === 'expired');

  for (let back = 7; back >= 1; back--) {
    activeLeases.forEach((lease, idx) => {
      id++;
      out.push(buildInvoice(id, lease, monthKey(-back), true, (idx + back) % 5));
    });
  }
  activeLeases.forEach((lease, idx) => {
    id++;
    out.push(buildInvoice(id, lease, monthKey(0), false, idx % 6));
  });
  return out;
})();

function buildInvoice(
  id: number,
  lease: LeaseRecord,
  month: string,
  historical: boolean,
  variant: number
): InvoiceRecord {
  const issue = `${month}-01`;
  const due = `${month}-05`;
  const base = lease.rent_amount;
  const fee = variant === 4 ? 75 : 0;
  const utility = variant === 3 ? 120 : 0;
  const amount = base + fee + utility;

  let status: InvoiceRecord['status'];
  if (historical) {
    status = variant === 5 ? 'cancelled' : (variant === 3 ? 'partial' : 'paid');
  } else {
    status = (['paid', 'paid', 'sent', 'partial', 'overdue', 'draft'] as InvoiceRecord['status'][])[variant] ?? 'sent';
  }

  let paidAmount = 0;
  if (status === 'paid') paidAmount = amount;
  if (status === 'partial') paidAmount = Math.round(amount * 0.5);
  if (status === 'overdue') paidAmount = variant === 0 ? 0 : Math.round(amount * 0.25);

  const type: InvoiceRecord['type'] = fee ? 'late_fee' : utility ? 'utility' : variant === 5 ? 'maintenance' : 'rent';

  const lineItems: InvoiceLineItemRecord[] = [
    { description: `Base rent — ${month}`, quantity: 1, unit_price: base, amount: base, tax_rate: 0, tax_amount: 0 }
  ];
  if (utility) lineItems.push({ description: 'Utilities (water/electric)', quantity: 1, unit_price: utility, amount: utility, tax_rate: 0, tax_amount: 0 });
  if (fee) lineItems.push({ description: 'Late payment fee', quantity: 1, unit_price: fee, amount: fee, tax_rate: 0, tax_amount: 0 });

  const payments: PaymentRecord[] = [];
  if (paidAmount > 0) {
    payments.push({
      id: id * 10,
      invoice_id: id,
      amount: paidAmount,
      payment_date: `${month}-${historical ? '03' : '02'}`,
      payment_method: ['bank_transfer', 'check', 'online', 'card'][id % 4] as PaymentRecord['payment_method'],
      reference: `PMT-${1000 + id}`,
      notes: '',
      created_at: `${month}-${historical ? '03' : '02'}T10:00:00.000Z`
    });
  }

  return {
    id,
    lease_id: lease.id,
    property_id: lease.property_id,
    tenant_id: lease.tenant_id,
    code: `INV-${2024}-${String(id).padStart(5, '0')}`,
    type,
    status,
    issue_date: issue,
    due_date: due,
    paid_date: paidAmount === amount ? `${month}-03` : null,
    amount,
    paid_amount: paidAmount,
    balance: amount - paidAmount,
    currency: 'USD',
    description: fee ? 'Rent + late fee' : utility ? 'Rent + utilities' : 'Monthly rent',
    line_items: lineItems,
    lease: { id: lease.id, code: lease.code, rent_amount: lease.rent_amount },
    tenant: lease.tenant,
    property: lease.property,
    payments,
    created_at: `${issue}T08:00:00.000Z`,
    updated_at: `${month}-03T10:00:00.000Z`
  };
}

export const MOCK_PAYMENTS: PaymentRecord[] = MOCK_INVOICES.flatMap(i => i.payments ?? []);

export interface TenantDocumentRecord {
  id: number;
  tenant_id: number;
  name: string;
  category: string;
  disk: string;
  path: string;
  mime_type: string | null;
  size_kb: number;
  uploaded_by: number | null;
  url: string;
  extension: string;
  created_at: string;
  updated_at: string;
}

export const MOCK_TENANT_DOCUMENTS: TenantDocumentRecord[] = MOCK_TENANTS.flatMap((tenant, ti) => {
  const defs = [
    { name: 'Signed lease agreement.pdf', category: 'lease_agreement', size_kb: 412, days: -300 },
    { name: 'Emirates ID (both sides).pdf', category: 'id_document', size_kb: 180, days: -290 },
    { name: 'Proof of insurance.pdf', category: 'insurance', size_kb: 96, days: -120 },
    ...(ti % 2 === 0 ? [{ name: 'Tax residency certificate.pdf', category: 'tax_form', size_kb: 64, days: -45 }] : [])
  ];

  return defs.map((d, di) => ({
    id: tenant.id * 100 + di + 1,
    tenant_id: tenant.id,
    name: d.name,
    category: d.category,
    disk: 'documents',
    path: `tenants/${tenant.id}/${d.name}`,
    mime_type: 'application/pdf',
    size_kb: d.size_kb,
    uploaded_by: 1,
    url: '',
    extension: 'pdf',
    created_at: iso(d.days),
    updated_at: iso(d.days)
  }));
});

export const MOCK_USERS = [
  { id: 1, name: 'John Smith', email: 'admin@lottly.com', role: 'admin', tenant_id: undefined },
  { id: 2, name: 'Maria Lopez', email: 'manager@lottly.com', role: 'manager', tenant_id: undefined },
  { id: 3, name: 'Sam Carter', email: 'agent@lottly.com', role: 'agent', tenant_id: undefined }
];

export const MOCK_NOW = NOW;
export const MOCK_DAY = DAY;
