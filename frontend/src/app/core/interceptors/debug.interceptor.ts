import { HttpInterceptorFn, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { tap, catchError, finalize } from 'rxjs/operators';
import { throwError } from 'rxjs';
import { DebugService } from '../services/debug.service';

function sanitizeBody(body: unknown): unknown {
  if (typeof body !== 'object' || body === null) return body;
  // Substring matched against field names. Erring toward over-redaction: a hidden
  // non-secret field is harmless, a leaked credential in a shared log is not.
  const sensitiveFields = [
    'password', 'passwd', 'token', 'secret', 'authorization', 'credential',
    'key', 'card', 'cvv', 'cvc', 'pin', 'otp', 'passphrase', 'signature', 'jwt',
  ];
  const sanitized: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(body as Record<string, unknown>)) {
    const lowerKey = key.toLowerCase();
    if (sensitiveFields.some(f => lowerKey.includes(f))) {
      sanitized[key] = '***REDACTED***';
    } else if (typeof value === 'object' && value !== null) {
      sanitized[key] = sanitizeBody(value);
    } else if (typeof value === 'string' && value.length > 500) {
      sanitized[key] = value.substring(0, 500) + '...';
    } else {
      sanitized[key] = value;
    }
  }
  return sanitized;
}

export const debugInterceptor: HttpInterceptorFn = (req, next) => {
  const debug = inject(DebugService);

  if (!debug.enabled()) {
    return next(req);
  }

  const startTime = performance.now();
  const requestId = `req_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

  const sanitizedHeaders = req.headers.keys().reduce((acc, key) => {
    const value = req.headers.get(key);
    acc[key] = key.toLowerCase() === 'authorization' ? '***REDACTED***' : value;
    return acc;
  }, {} as Record<string, string | null>);

  const sanitizedParams = req.params.keys().reduce((acc, key) => {
    acc[key] = req.params.get(key);
    return acc;
  }, {} as Record<string, string | null>);

  // ── Log request ──────────────────────────────────────────────────────────
  debug.logHttpRequest({
    message: `→ ${req.method} ${req.url}`,
    data: {
      requestId,
      headers: sanitizedHeaders,
      body: req.body ? sanitizeBody(req.body) : null,
      params: sanitizedParams,
    },
    durationMs: 0,
  });

  return next(req).pipe(
    tap((event) => {
      if (event instanceof HttpResponse) {
        const duration = performance.now() - startTime;

        debug.logHttpRequest({
          message: `← ${req.method} ${req.url} [${event.status} ${event.statusText}]`,
          data: {
            requestId,
            status: event.status,
            statusText: event.statusText,
            responseSize: event.body ? JSON.stringify(event.body).length : 0,
            body: event.body,
          },
          durationMs: Math.round(duration * 100) / 100,
        });
      }
    }),
    catchError((error: HttpErrorResponse) => {
      const duration = performance.now() - startTime;

      debug.logHttpRequest({
        message: `✖ ${req.method} ${req.url} [${error.status} ${error.statusText}]`,
        data: {
          requestId,
          status: error.status,
          statusText: error.statusText,
          error: error.error,
          message: error.message,
        },
        durationMs: Math.round(duration * 100) / 100,
      });

      return throwError(() => error);
    }),
    finalize(() => {
      const duration = performance.now() - startTime;
      if (duration > 2000) {
        debug.warn(
          `Slow request: ${req.method} ${req.url} took ${duration.toFixed(0)}ms`,
          { requestId },
          'Performance'
        );
      }
    })
  );
};
