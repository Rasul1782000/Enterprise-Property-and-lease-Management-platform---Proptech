import { NgModule } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { TenantsRoutingModule } from './tenants-routing.module';
import { TenantsListComponent } from './pages/tenants-list/tenants-list.component';
import { TenantDetailComponent } from './pages/tenant-detail/tenant-detail.component';
import { TenantFormComponent } from './components/tenant-form/tenant-form.component';

@NgModule({
  declarations: [
    TenantsListComponent,
    TenantDetailComponent,
    TenantFormComponent
  ],
  imports: [
    SharedModule,
    TenantsRoutingModule
  ]
})
export class TenantsModule {}
