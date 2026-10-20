import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MessageService } from 'primeng/api';
import { catchError } from 'rxjs/operators';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const messages = inject(MessageService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 0) {
        messages.add({ severity: 'error', summary: 'Connection failed', detail: 'The API could not be reached. Check that the API server is running.', life: 5000 });
      } else if (error.status >= 500) {
        messages.add({ severity: 'error', summary: 'Server error', detail: 'Something went wrong. Please try again later.', life: 5000 });
      } else if (error.error?.message) {
        messages.add({ severity: 'error', summary: 'Request failed', detail: error.error.message, life: 4000 });
      }

      throw error;
    })
  );
};
