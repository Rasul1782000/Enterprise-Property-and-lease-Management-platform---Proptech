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
      { path: 'lease-agreement', loadComponent: () => import('../../lease-agreement/lease-agreement').then(m => m.LeaseAgreement) },
      { path: 'vault-management', canActivate: [roleGuard], data: { roles: ['admin', 'manager'] }, loadComponent: () => import('../../vault-management/vault-management').then(m => m.VaultManagement) },
      { path: 'post-date-cheque-vault', canActivate: [roleGuard], data: { roles: ['admin', 'manager'] }, loadComponent: () => import('../../post-date-cheque-vault/post-date-cheque-vault').then(m => m.PostDateChequeVault) },
      { path: 'lease-renewal-engine', loadComponent: () => import('../../lease-renewal-engine/lease-renewal-engine').then(m => m.LeaseRenewalEngine) },
      { path: 'service-charge-and-escrow', canActivate: [roleGuard], data: { roles: ['admin', 'manager'] }, loadComponent: () => import('../../service-charge-and-escrow-management/service-charge-and-escrow-management').then(m => m.ServiceChargeAndEscrowManagement) },
      { path: 'ejari-tawtheeq-console', canActivate: [roleGuard], data: { roles: ['admin', 'manager'] }, loadComponent: () => import('../../ejari-tawtheeq-management-console/ejari-tawtheeq-management-console').then(m => m.EjariTawtheeqManagementConsole) },
      { path: 'fit-out-alterations-board', loadComponent: () => import('../../fit-out-alterations-board-queue/fit-out-alterations-board-queue').then(m => m.FitOutAlterationsBoardQueue) },
      { path: 'bounced-cheque-workflow', canActivate: [roleGuard], data: { roles: ['admin', 'manager'] }, loadComponent: () => import('../../bounced-cheque-workflow-engine/bounced-cheque-workflow-engine').then(m => m.BouncedChequeWorkflowEngine) }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class MainLayoutRoutingModule {}
