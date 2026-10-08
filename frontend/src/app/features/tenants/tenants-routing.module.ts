import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TenantsListComponent } from './pages/tenants-list/tenants-list.component';
import { TenantDetailComponent } from './pages/tenant-detail/tenant-detail.component';

const routes: Routes = [
  { path: '', component: TenantsListComponent },
  { path: ':id', component: TenantDetailComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class TenantsRoutingModule {}
