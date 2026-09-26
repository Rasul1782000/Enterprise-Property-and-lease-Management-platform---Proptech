import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  { path: 'login', loadChildren: () => import('./features/auth/auth.module').then(m => m.AuthModule) },
  {
    path: '',
    canActivate: [authGuard],
    loadChildren: () => import('./layouts/main-layout/main-layout.module').then(m => m.MainLayoutModule)
  },
  { path: '**', redirectTo: '' }
];
