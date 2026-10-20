import { TestBed } from '@angular/core/testing';
import { DebugService } from './debug.service';
import { environment } from '../../../environments/environment';

describe('DebugService', () => {
  let service: DebugService;
  let originalDebug: boolean;

  beforeEach(() => {
    originalDebug = environment.debug;

    TestBed.configureTestingModule({ providers: [DebugService] });
    service = TestBed.inject(DebugService);
  });

  afterEach(() => {
    environment.debug = originalDebug;
    vi.restoreAllMocks();
  });

  // ─── Enabled / disabled gating ──────────────────────────────────────────

  it('records entries when debug mode is enabled', () => {
    service.enabled.set(true);

    service.log('hello');

    expect(service.entries().length).toBe(1);
    expect(service.entries()[0].message).toBe('hello');
  });

  it('records nothing when debug mode is disabled', () => {
    service.enabled.set(false);

    service.log('should not appear');
    service.info('nor this');
    service.warn('nor this');
    service.error('nor this');
    service.debug('nor this');

    expect(service.entries().length).toBe(0);
  });

  it('can be toggled at runtime', () => {
    service.enabled.set(false);
    expect(service.enabled()).toBe(false);

    service.toggle();
    expect(service.enabled()).toBe(true);

    service.toggle();
    expect(service.enabled()).toBe(false);
  });

  // ─── Severity levels ────────────────────────────────────────────────────

  it('stores the level for each severity helper', () => {
    service.enabled.set(true);

    service.log('a');
    service.info('b');
    service.warn('c');
    service.error('d');
    service.debug('e');

    const levels = service.entries().map(e => e.level);

    expect(levels).toEqual(['debug', 'error', 'warn', 'info', 'log']);
  });

  it('keeps newest entries first', () => {
    service.enabled.set(true);

    service.log('first');
    service.log('second');

    expect(service.entries()[0].message).toBe('second');
  });

  it('assigns a unique incrementing id to each entry', () => {
    service.enabled.set(true);

    service.log('one');
    service.log('two');

    // Entries are stored newest-first.
    const [newest, oldest] = service.entries();
    expect(newest.id).toBeGreaterThan(oldest.id);
  });

  // ─── Context handling ───────────────────────────────────────────────────

  it('attaches the supplied context data', () => {
    service.enabled.set(true);

    service.info('user signed in', { user_id: 7 });

    expect(service.entries()[0].data).toEqual({ user_id: 7 });
  });

  it('defaults the source to App when none is given', () => {
    service.enabled.set(true);

    service.log('no source');

    expect(service.entries()[0].source).toBe('App');
  });

  it('records an explicit source when provided', () => {
    service.enabled.set(true);

    service.log('with source', undefined, 'ApiClient');
    expect(service.entries()[0].source).toBe('ApiClient');
  });

  // ─── HTTP logging ───────────────────────────────────────────────────────

  it('appends http calls to the httpCalls log', () => {
    service.enabled.set(true);

    service.logHttpRequest({ message: '→ GET /api/properties', durationMs: 0 });
    service.logHttpRequest({ message: '← GET /api/properties [200]', durationMs: 12 });

    expect(service.httpCalls().length).toBe(2);
  });

  it('never records http calls while debug is disabled', () => {
    service.enabled.set(false);

    service.logHttpRequest({ message: '→ GET /api/properties', durationMs: 0 });

    expect(service.httpCalls().length).toBe(0);
  });

  it('does not mix http calls into the general entry log', () => {
    service.enabled.set(true);

    service.logHttpRequest({ message: '→ GET /api/properties', durationMs: 0 });
    service.log('a regular log line');

    expect(service.httpCalls().length).toBe(1);
    expect(service.entries().length).toBe(1);
    expect(service.entries()[0].message).toBe('a regular log line');
  });

  it('tags http entries with the HTTP source and info level', () => {
    service.enabled.set(true);

    service.logHttpRequest({ message: '→ GET /api/properties', durationMs: 0 });

    const entry = service.httpCalls()[0];
    expect(entry.source).toBe('HTTP');
    expect(entry.level).toBe('info');
  });

  // ─── Retention cap ──────────────────────────────────────────────────────

  it('caps the general log at 500 entries', () => {
    service.enabled.set(true);

    for (let i = 0; i < 520; i++) {
      service.log(`entry ${i}`);
    }

    expect(service.entries().length).toBe(500);
  });

  it('caps the http log at 100 entries', () => {
    service.enabled.set(true);

    for (let i = 0; i < 120; i++) {
      service.logHttpRequest({ message: `call ${i}`, durationMs: 0 });
    }

    expect(service.httpCalls().length).toBe(100);
  });

  // ─── Timing helpers ─────────────────────────────────────────────────────

  it('time() returns the callback result', () => {
    service.enabled.set(true);

    const result = service.time('compute', () => 'value');

    expect(result).toBe('value');
  });

  it('time() records the duration', () => {
    service.enabled.set(true);

    service.time('timed-op', () => 1);

    expect(service.entries()[0].durationMs).toBeGreaterThanOrEqual(0);
  });

  it('time() still executes the callback while disabled', () => {
    service.enabled.set(false);
    const spy = vi.fn(() => 'ran');

    expect(service.time('x', spy)).toBe('ran');
    expect(spy).toHaveBeenCalledOnce();
    expect(service.entries().length).toBe(0);
  });

  it('async timeAsync() awaits and returns the callback result', async () => {
    service.enabled.set(true);

    const result = await service.timeAsync('async-op', async () => 'async-value');

    expect(result).toBe('async-value');
  });

  it('async timeAsync() records the duration', async () => {
    service.enabled.set(true);

    await service.timeAsync('async-op', async () => 1);

    expect(service.entries()[0].durationMs).toBeGreaterThanOrEqual(0);
  });

  // ─── State / network helpers ────────────────────────────────────────────

  it('dumpState records the state when enabled', () => {
    service.enabled.set(true);
    const spy = vi.spyOn(console, 'groupCollapsed').mockImplementation(() => undefined);

    service.dumpState('MyComponent', { count: 3 });

    expect(spy).toHaveBeenCalled();
    expect(service.entries().length).toBe(1);
  });

  it('dumpState is silent while disabled', () => {
    service.enabled.set(false);
    const spy = vi.spyOn(console, 'groupCollapsed').mockImplementation(() => undefined);

    service.dumpState('MyComponent', { count: 3 });

    expect(spy).not.toHaveBeenCalled();
    expect(service.entries().length).toBe(0);
  });

  it('logNetworkInfo captures the navigator user agent', () => {
    service.enabled.set(true);

    service.logNetworkInfo();

    const entry = service.entries()[0];
    expect(entry.data).toBeDefined();
    expect((entry.data as Record<string, unknown>)['userAgent']).toBe(navigator.userAgent);
  });

  it('logComponentLifecycle records the hook name', () => {
    service.enabled.set(true);

    service.logComponentLifecycle('PropertyList', 'ngOnInit');

    expect(service.entries()[0].message).toContain('PropertyList.ngOnInit');
  });

  it('logNavigation records the target url', () => {
    service.enabled.set(true);

    service.logNavigation({ url: '/properties' });

    expect(service.entries()[0].message).toContain('/properties');
  });

  it('logStorageOperation records the key', () => {
    service.enabled.set(true);

    service.logStorageOperation('set', 'auth_token');

    expect(service.entries()[0].message).toContain('auth_token');
  });

  // ─── Clearing and export ────────────────────────────────────────────────

  it('clear() empties both logs', () => {
    service.enabled.set(true);

    service.log('something');
    service.logHttpRequest({ message: '→ GET /x', durationMs: 0 });
    service.clear();

    // clear() logs its own confirmation entry after wiping.
    expect(service.entries().every(e => e.message.includes('cleared'))).toBe(true);
    expect(service.httpCalls().length).toBe(0);
  });

  it('export() produces parseable json', () => {
    service.enabled.set(true);

    service.log('export me', { a: 1 });
    const parsed = JSON.parse(service.export());

    expect(Array.isArray(parsed.entries)).toBe(true);
    expect(parsed.entries.length).toBeGreaterThan(0);
  });

  // ─── Security: the service must never become a secret leak ──────────────

  it('does not throw on circular or exotic context values', () => {
    service.enabled.set(true);
    vi.spyOn(console, 'log').mockImplementation(() => undefined);

    const circular: Record<string, unknown> = { name: 'loop' };
    circular['self'] = circular;

    expect(() => service.log('circular', circular)).not.toThrow();
    expect(() => service.log('symbol', Symbol('s'))).not.toThrow();
    expect(() => service.log('bigint', BigInt(10))).not.toThrow();
    expect(() => service.log('fn', () => undefined)).not.toThrow();
  });
});