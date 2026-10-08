import { HttpInterceptorFn } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { MockDb } from './mock-db';

let db: MockDb | null = null;

export const mockApiInterceptor: HttpInterceptorFn = (req, next): Observable<any> => {
  if (!environment.useMockData || !req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  db ??= new MockDb();
  const handled = db.handle(req as any);
  if (handled) return handled;

  return next(req);
};
