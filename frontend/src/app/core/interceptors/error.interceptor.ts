import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { MessageService } from 'primeng/api';
import { AuthService } from '../services/auth.service';
import { catchError } from 'rxjs/operators';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const messages = inject(MessageService);
  const router = inject(Router);
  const auth = inject(AuthService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        auth.clear();
        router.navigate(['/login']);
        messages.add({ severity: 'warn', summary: 'Session expired', detail: 'Please log in again.', life: 3000 });
      } else if (error.status === 403) {
        router.navigate(['/dashboard']);
        messages.add({ severity: 'warn', summary: 'Access denied', detail: 'You do not have permission to view this resource.', life: 3000 });
      } else if (error.status >= 500) {
        messages.add({ severity: 'error', summary: 'Server error', detail: 'Something went wrong. Please try again later.', life: 5000 });
      } else if (error.error?.message) {
        messages.add({ severity: 'error', summary: 'Request failed', detail: error.error.message, life: 4000 });
      }

      throw error;
    })
  );
};
