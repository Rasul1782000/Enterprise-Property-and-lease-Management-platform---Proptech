import { Injectable, signal } from '@angular/core';
import { environment } from '../../../environments/environment';

export type DebugLevel = 'log' | 'info' | 'warn' | 'error' | 'debug';

export interface DebugEntry {
  id: number;
  timestamp: Date;
  level: DebugLevel;
  message: string;
  data?: unknown;
  source: string;
  durationMs?: number;
}

@Injectable({ providedIn: 'root' })
export class DebugService {
  private nextId = 1;
  private readonly maxEntries = 500;

  readonly enabled = signal(environment.debug ?? false);
  readonly entries = signal<DebugEntry[]>([]);
  readonly httpCalls = signal<DebugEntry[]>([]);

  private get isDebug(): boolean {
    return this.enabled();
  }

  // ─── Core Logging ───────────────────────────────────────────────────────

  log(message: string, data?: unknown, source?: string): void {
    this.addEntry('log', message, data, source);
  }

  info(message: string, data?: unknown, source?: string): void {
    this.addEntry('info', message, data, source);
  }

  warn(message: string, data?: unknown, source?: string): void {
    this.addEntry('warn', message, data, source);
  }

  error(message: string, data?: unknown, source?: string): void {
    this.addEntry('error', message, data, source);
  }

  debug(message: string, data?: unknown, source?: string): void {
    this.addEntry('debug', message, data, source);
  }

  // ─── HTTP Logging ───────────────────────────────────────────────────────

  logHttpRequest(entry: Omit<DebugEntry, 'id' | 'timestamp' | 'level' | 'source'>): void {
    if (!this.isDebug) return;

    const fullEntry: DebugEntry = {
      ...entry,
      id: this.nextId++,
      timestamp: new Date(),
      level: 'info',
      source: 'HTTP',
    };

    const calls = [fullEntry, ...this.httpCalls()].slice(0, 100);
    this.httpCalls.set(calls);

    const color = this.getColorForStatus(fullEntry.message);
    console.groupCollapsed(
      `%c[HTTP] ${fullEntry.message}`,
      `color: ${color}; font-weight: bold;`
    );
    if (fullEntry.data) {
      console.log('Data:', fullEntry.data);
    }
    if (fullEntry.durationMs !== undefined) {
      console.log(`Duration: ${fullEntry.durationMs}ms`);
    }
    console.log(`Time: ${fullEntry.timestamp.toISOString()}`);
    console.groupEnd();
  }

  // ─── Performance ────────────────────────────────────────────────────────

  time<T>(label: string, fn: () => T): T {
    if (!this.isDebug) return fn();

    const start = performance.now();
    const result = fn();
    const duration = performance.now() - start;

    this.addEntry('debug', `⏱ ${label}`, undefined, 'Performance', duration);

    if (duration > 1000) {
      console.warn(`[Performance] ${label} took ${duration.toFixed(2)}ms`);
    }

    return result;
  }

  async timeAsync<T>(label: string, fn: () => Promise<T>): Promise<T> {
    if (!this.isDebug) return fn();

    const start = performance.now();
    const result = await fn();
    const duration = performance.now() - start;

    this.addEntry('debug', `⏱ ${label}`, undefined, 'Performance', duration);

    if (duration > 1000) {
      console.warn(`[Performance] ${label} took ${duration.toFixed(2)}ms`);
    }

    return result;
  }

  // ─── State Inspection ────────────────────────────────────────────────────

  dumpState(label: string, state: unknown): void {
    if (!this.isDebug) return;

    console.groupCollapsed(`[State] ${label}`);
    console.log('Value:', state);
    console.log('Type:', typeof state);
    console.log('Timestamp:', new Date().toISOString());
    console.trace('Stack trace');
    console.groupEnd();

    this.addEntry('debug', `State dump: ${label}`, state, 'State');
  }

  // ─── Network Info ────────────────────────────────────────────────────────

  logNetworkInfo(): void {
    if (!this.isDebug) return;

    const nav = navigator as Navigator & {
      connection?: { effectiveType?: string; downlink?: number; rtt?: number };
    };

    const info = {
      online: navigator.onLine,
      userAgent: navigator.userAgent,
      language: navigator.language,
      platform: navigator.platform,
      screenResolution: `${screen.width}x${screen.height}`,
      viewport: `${window.innerWidth}x${window.innerHeight}`,
      connection: nav.connection ? {
        effectiveType: nav.connection.effectiveType,
        downlink: nav.connection.downlink,
        rtt: nav.connection.rtt,
      } : 'N/A',
      memory: (performance as Performance & { memory?: { usedJSHeapSize: number; totalJSHeapSize: number } }).memory ? {
        usedJSHeapSize: `${(((performance as any).memory.usedJSHeapSize) / 1048576).toFixed(2)} MB`,
        totalJSHeapSize: `${(((performance as any).memory.totalJSHeapSize) / 1048576).toFixed(2)} MB`,
      } : 'N/A',
    };

    this.info('Network & Device Info', info, 'Network');
    console.table(info);
  }

  // ─── Lifecycle ──────────────────────────────────────────────────────────

  logComponentLifecycle(componentName: string, hook: string, data?: unknown): void {
    if (!this.isDebug) return;

    const emoji: Record<string, string> = {
      ngOnInit: '🟢',
      ngOnChanges: '🟡',
      ngOnDestroy: '🔴',
      ngAfterViewInit: '🔵',
      ngDoCheck: '🟣',
    };

    const icon = emoji[hook] || '⚪';
    this.addEntry('debug', `${icon} ${componentName}.${hook}`, data, 'Lifecycle');
  }

  // ─── Router ─────────────────────────────────────────────────────────────

  logNavigation(event: { url: string; urlAfterRedirects?: string }): void {
    if (!this.isDebug) return;

    this.addEntry('info', `→ Navigation: ${event.url}`, event, 'Router');
  }

  // ─── Storage ────────────────────────────────────────────────────────────

  logStorageOperation(operation: 'get' | 'set' | 'remove', key: string, value?: unknown): void {
    if (!this.isDebug) return;

    const emoji = operation === 'get' ? '📖' : operation === 'set' ? '💾' : '🗑';
    this.addEntry('debug', `${emoji} Storage ${operation}: ${key}`, value, 'Storage');
  }

  // ─── Clear & Export ──────────────────────────────────────────────────────

  clear(): void {
    this.entries.set([]);
    this.httpCalls.set([]);
    console.clear();
    this.info('Debug log cleared', undefined, 'DebugService');
  }

  export(): string {
    return JSON.stringify({
      timestamp: new Date().toISOString(),
      environment: environment,
      entries: this.entries(),
      httpCalls: this.httpCalls(),
    }, null, 2);
  }

  download(): void {
    const data = this.export();
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `debug-log-${new Date().toISOString().slice(0, 19)}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ─── Toggle ─────────────────────────────────────────────────────────────

  toggle(): void {
    this.enabled.update(v => !v);
    this.info(`Debug mode ${this.enabled() ? 'ENABLED' : 'DISABLED'}`, undefined, 'DebugService');
  }

  // ─── Private ────────────────────────────────────────────────────────────

  private addEntry(
    level: DebugLevel,
    message: string,
    data: unknown,
    source?: string,
    durationMs?: number
  ): void {
    if (!this.isDebug) return;

    const entry: DebugEntry = {
      id: this.nextId++,
      timestamp: new Date(),
      level,
      message,
      data,
      source: source ?? 'App',
      durationMs,
    };

    const entries = [entry, ...this.entries()].slice(0, this.maxEntries);
    this.entries.set(entries);

    // Console output with styling
    const styles: Record<DebugLevel, string> = {
      log: 'color: #9CA3AF',
      info: 'color: #3B82F6; font-weight: bold',
      warn: 'color: #F59E0B; font-weight: bold',
      error: 'color: #EF4444; font-weight: bold',
      debug: 'color: #8B5CF6',
    };

    const style = styles[level];
    const timeStr = entry.timestamp.toLocaleTimeString();
    const sourceStr = `[${source}]`;

    if (data !== undefined) {
      console.log(
        `%c[${timeStr}] ${sourceStr} ${message}`,
        style,
        data
      );
    } else {
      console.log(
        `%c[${timeStr}] ${sourceStr} ${message}`,
        style
      );
    }
  }

  private getColorForStatus(message: string): string {
    if (message.includes('2')) return '#10B981'; // green
    if (message.includes('4')) return '#F59E0B'; // amber
    if (message.includes('5')) return '#EF4444'; // red
    return '#6B7280'; // gray
  }
}
