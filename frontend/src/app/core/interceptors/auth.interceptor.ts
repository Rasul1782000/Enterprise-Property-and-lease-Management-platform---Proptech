import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from '../services/auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const token = auth.token();
  // Attach Sanctum token + XSRF
  if (token && !req.url.includes('/sanctum/csrf-cookie')) {
    req = req.clone({
      setHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      withCredentials: true
    });
  } else {
    req = req.clone({ withCredentials: true });
  }
  return next(req);
};
