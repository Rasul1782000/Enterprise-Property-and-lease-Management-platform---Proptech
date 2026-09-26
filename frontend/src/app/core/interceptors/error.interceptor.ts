import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError } from 'rxjs/operators';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const snackBar = inject(MatSnackBar);
  const router = inject(Router);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        // Unauthorized - redirect to login
        router.navigate(['/login']);
        snackBar.open('Session expired. Please log in again.', 'Close', { duration: 3000 });
      } else if (error.status === 403) {
        // Forbidden - redirect to dashboard with message
        router.navigate(['/dashboard']);
        snackBar.open('Access denied. You do not have permission to access this resource.', 'Close', { duration: 3000 });
      } else if (error.status >= 500) {
        // Server error - show generic error message
        snackBar.open('A server error occurred. Please try again later.', 'Close', { duration: 5000 });
      } else if (error.error?.message) {
        // Other errors with custom message
        snackBar.open(error.error.message, 'Close', { duration: 3000 });
      }

      throw error;
    })
  );
}