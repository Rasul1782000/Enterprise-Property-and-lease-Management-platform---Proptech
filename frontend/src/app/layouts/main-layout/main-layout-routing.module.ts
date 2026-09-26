import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { roleGuard } from '@core/guards/role.guard';
import { MainLayoutComponent } from './main-layout.component';

const routes: Routes = [
  {
    path: '',
    component: MainLayoutComponent,
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', loadChildren: () => import('@features/dashboard/dashboard.module').then(m => m.DashboardModule) },
      { path: 'properties', loadChildren: () => import('@features/properties/properties.module').then(m => m.PropertiesModule) },
      { path: 'buildings', loadChildren: () => import('@features/buildings/buildings.module').then(m => m.BuildingsModule) },
      { path: 'units', loadChildren: () => import('@features/units/units.module').then(m => m.UnitsModule) },
      { path: 'tenants', loadChildren: () => import('@features/tenants/tenants.module').then(m => m.TenantsModule) },
      { path: 'leases', loadChildren: () => import('@features/leases/leases.module').then(m => m.LeasesModule) },
      { path: 'invoices', loadChildren: () => import('@features/invoices/invoices.module').then(m => m.InvoicesModule) },
      { path: 'admin', canActivate: [roleGuard], data: { roles: ['admin', 'manager'] }, loadChildren: () => import('@features/dashboard/dashboard.module').then(m => m.DashboardModule) }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class MainLayoutRoutingModule {}
