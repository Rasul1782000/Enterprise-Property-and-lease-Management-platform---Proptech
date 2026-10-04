/**
 * Global setup for the Angular unit-test builder (vitest + jsdom).
 * jsdom does not implement ResizeObserver, but PrimeNG's TabList
 * (and some other widgets) rely on it.
 */
if (typeof globalThis.ResizeObserver === 'undefined') {
  const noop = (): void => undefined;
  globalThis.ResizeObserver = class {
    observe = noop;
    unobserve = noop;
    disconnect = noop;
  } as unknown as typeof ResizeObserver;
}
