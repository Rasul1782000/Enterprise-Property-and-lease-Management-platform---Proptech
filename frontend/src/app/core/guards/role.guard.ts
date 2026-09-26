import { CanActivateFn, ActivatedRouteSnapshot, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthService } from '../services/auth.service';

export const roleGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const roles: string[] = route.data['roles'] ?? [];
  const userRole = auth.user()?.role ?? '';
  if (!roles.length || roles.includes(userRole)) return true;
  router.navigateByUrl('/dashboard');
  return false;
};
