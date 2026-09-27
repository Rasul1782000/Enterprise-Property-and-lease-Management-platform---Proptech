import '@angular/compiler';
import { HttpParams, HttpRequest } from '@angular/common/http';
import { MockDb } from '../src/app/core/mock/mock-db';

const db = new MockDb();
const base = 'http://localhost:8000/api';

let failures = 0;
function check(label: string, condition: boolean, detail = '') {
  if (condition) {
    console.log(`  PASS  ${label}`);
  } else {
    failures++;
    console.log(`  FAIL  ${label} ${detail}`);
  }
}

function call(method: string, path: string, body: any = null, params: Record<string, string> = {}) {
  const events: any[] = [];
  let error: any = null;
  const obs = db.handle(new HttpRequest(method as any, `${base}/${path}`, body, { params: new HttpParams({ fromObject: params }) } as any));
  if (!obs) return { missing: true };
  obs.subscribe({ next: e => events.push(e), error: e => (error = e) });
  return { body: events[0]?.body, error, missing: false };
}

console.log('\n== core entities ==');
{
  const r = call('GET', 'properties', null, { per_page: '5' });
  check('properties list returns a page', Array.isArray(r.body?.data) && r.body.data.length === 5, JSON.stringify(r.body)?.slice(0, 120));
  check('properties meta.total = 7', r.body?.meta?.total === 7, String(r.body?.meta?.total));
}
{
  const r = call('GET', 'dashboard/stats');
  check('dashboard stats has units block', typeof r.body?.units?.total === 'number' && r.body.units.total > 0, JSON.stringify(r.body?.units));
}

console.log('\n== module collections ==');
const collections = [
  ['vault-assets', 26],
  ['post-dated-cheques', 34],
  ['lease-agreements', 30],
  ['lease-renewals', 28],
  ['service-charges', null],
  ['escrow-accounts', 6],
  ['ejari-contracts', 30],
  ['fit-out-requests', 27],
  ['bounced-cheques', 24]
] as const;

for (const [endpoint, expected] of collections) {
  const r = call('GET', endpoint, null, { per_page: '500' });
  const rows = r.body?.data;
  check(`${endpoint} returns rows`, Array.isArray(rows) && rows.length > 0, `got ${JSON.stringify(rows)?.slice(0, 80)}`);
  if (expected !== null) {
    check(`${endpoint} seeded ${expected} rows`, rows?.length === expected, `got ${rows?.length}`);
  }
}

console.log('\n== module stats ==');
for (const [endpoint] of collections) {
  const r = call('GET', `${endpoint}/stats`);
  const s = r.body;
  const listCount = call('GET', endpoint, null, { per_page: '500' }).body?.data?.length;
  check(`${endpoint}/stats total matches list`, s?.total === listCount, `stats ${s?.total} vs list ${listCount}`);
  check(`${endpoint}/stats has by_status`, !!s?.by_status && Object.keys(s.by_status).length > 0);
  check(`${endpoint}/stats has attention`, typeof s?.attention === 'number');
}
{
  const s = call('GET', 'fit-out-requests/stats').body;
  check('fit-out stats rolls up noc_status', typeof s?.noc_issued === 'number' && !('noc_noc_issued' in s.by_status), JSON.stringify(s?.by_status));
  check('fit-out stats sums estimated_cost', s?.estimated_cost > 0, String(s?.estimated_cost));
  const b = call('GET', 'bounced-cheques/stats').body;
  check('bounced-cheques stats keys off stage', typeof b?.promise_to_pay === 'number', JSON.stringify(b?.by_status));
  check('bounced-cheques stats sums outstanding', b?.outstanding > 0, String(b?.outstanding));
}

console.log('\n== detail + filters + search ==');
{
  const r = call('GET', 'bounced-cheques/1');
  check('detail by id returns the record', r.body?.id === 1, JSON.stringify(r.body)?.slice(0, 80));
  const r404 = call('GET', 'bounced-cheques/9999');
  check('unknown id yields a 404', r404.error?.status === 404);
}
{
  const r = call('GET', 'lease-renewals', null, { 'filter[max_days_to_expiry]': '30' });
  check('max_days_to_expiry filter applies', r.body.data.every((x: any) => x.days_to_expiry <= 30), JSON.stringify(r.body.data.map((x: any) => x.days_to_expiry)));
  check('max_days_to_expiry returns some rows', r.body.data.length > 0);
}
{
  const r = call('GET', 'service-charges', null, { 'filter[has_outstanding]': '1' });
  check('has_outstanding filter applies', r.body.data.every((x: any) => x.outstanding > 0));
  check('has_outstanding returns some rows', r.body.data.length > 0);
}
{
  const all = call('GET', 'bounced-cheques', null, { per_page: '500' }).body.data;
  const term = all[3].tenant_name.split(' ')[1];
  const r = call('GET', 'bounced-cheques', null, { search: term, per_page: '500' });
  check('search narrows results', r.body.data.length > 0 && r.body.data.length < all.length, `${r.body.data.length} of ${all.length}`);
}
{
  const r = call('GET', 'ejari-contracts', null, { 'filter[ejari_type]': 'renewal' });
  check('enum filter applies', r.body.data.every((x: any) => x.ejari_type === 'renewal') && r.body.data.length > 0);
}

console.log('\n== workflow actions ==');
{
  const before = call('GET', 'bounced-cheques/1').body;
  const r = call('POST', `bounced-cheques/${before.id}/escalate`);
  check('escalate transitions the stage', r.body?.stage === 'escalated', JSON.stringify(r.body)?.slice(0, 80));
  check('escalate bumps actions_taken', r.body?.actions_taken === Number(before.actions_taken) + 1, `${before.actions_taken} -> ${r.body?.actions_taken}`);
}
{
  const before = call('GET', 'bounced-cheques/1').body;
  const r = call('POST', `bounced-cheques/${before.id}/recover`, { amount: before.outstanding });
  check('recover marks the case recovered', r.body?.stage === 'recovered' && r.body.outstanding === 0, JSON.stringify(r.body)?.slice(0, 120));
}
{
  const r = call('POST', 'bounced-cheques/1/not-a-real-action');
  check('unknown action is rejected with 422', r.error?.status === 422, JSON.stringify(r.error));
}
{
  const before = call('GET', 'vault-assets/1').body;
  const r = call('POST', `vault-assets/${before.id}/release`);
  check('vault release works', r.body?.status === 'released', JSON.stringify(r.body)?.slice(0, 80));
  const s = call('GET', 'vault-assets/stats').body;
  check('stats reflect the release', s.released >= 1, String(s.released));
}
{
  const r = call('POST', `ejari-contracts/3/register`);
  check('ejari register issues a number', /^EJ-\d+$/.test(String(r.body?.ejari_number)), String(r.body?.ejari_number));
  check('ejari register stamps the date', !!r.body?.registration_date, String(r.body?.registration_date));
}
{
  const r = call('POST', 'fit-out-requests/2/issue-noc');
  check('fit-out NOC sets progress to 100', r.body?.progress_percent === 100, JSON.stringify(r.body)?.slice(0, 120));
  check('fit-out NOC refunds the deposit', r.body?.deposit_status === 'refunded', String(r.body?.deposit_status));
}
{
  const before = call('GET', 'service-charges/1').body;
  const r = call('POST', `service-charges/${before.id}/collect`, { amount: before.actual_amount });
  check('collect closes the charge line', r.body?.status === 'paid' && r.body.outstanding === 0, JSON.stringify(r.body)?.slice(0, 120));
}
{
  const r = call('POST', 'escrow-accounts/1/freeze');
  check('escrow freeze works', r.body?.status === 'frozen', JSON.stringify(r.body)?.slice(0, 80));
}

console.log('\n== create / update / delete ==');
{
  const r = call('POST', 'lease-renewals', { lease_code: 'LS-TEST', tenant_name: 'Test Tenant', current_rent: 1000, proposed_rent: 1050 });
  const id = r.body?.id;
  check('create assigns a new id', Number.isInteger(id) && id > 0, String(id));
  const u = call('PATCH', `lease-renewals/${id}`, { status: 'accepted' });
  check('patch updates the record', u.body?.status === 'accepted' && u.body.id === id, JSON.stringify(u.body)?.slice(0, 80));
  const d = call('DELETE', `lease-renewals/${id}`);
  check('delete removes the record', call('GET', `lease-renewals/${id}`).error?.status === 404);
  const after = call('GET', 'lease-renewals', null, { per_page: '500' }).body.data.length;
  check('list is back to the seeded count', after === 28, String(after));
}

console.log(`\n${failures === 0 ? 'ALL CHECKS PASSED' : `${failures} CHECK(S) FAILED`}\n`);
process.exit(failures === 0 ? 0 : 1);
