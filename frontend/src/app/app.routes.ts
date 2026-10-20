import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    loadChildren: () => import('./layouts/main-layout/main-layout.module').then(m => m.MainLayoutModule)
  },
  { path: '**', redirectTo: '' }
];
