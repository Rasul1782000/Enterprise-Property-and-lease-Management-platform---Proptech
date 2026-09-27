import { HttpInterceptorFn } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { MockDb } from './mock-db';

/** Session-scoped so create/update/delete performed in the UI persist across navigation. */
let db: MockDb | null = null;

/**
 * Demo data source. When `environment.useMockData` is on, every request aimed at the
 * API base URL is answered from the in-memory database instead of the network, so all
 * pages have content to render out of the box. Set it to `false` to talk to the real API.
 */
export const mockApiInterceptor: HttpInterceptorFn = (req, next): Observable<any> => {
  if (!environment.useMockData || !req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  db ??= new MockDb();
  const handled = db.handle(req as any);
  if (handled) return handled;

  // Unmocked endpoint -> let the real backend try.
  return next(req);
};
