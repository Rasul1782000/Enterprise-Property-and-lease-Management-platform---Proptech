import { NgModule, NO_ERRORS_SCHEMA } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { LeasesRoutingModule } from './leases-routing.module';
import { LeasesListComponent } from './pages/leases-list/leases-list.component';
import { LeaseDetailComponent } from './pages/lease-detail/lease-detail.component';
import { LeaseWizardComponent } from './pages/lease-wizard/lease-wizard.component';
import { LeaseFormComponent } from './components/lease-form/lease-form.component';

@NgModule({
  declarations: [
    LeasesListComponent,
    LeaseDetailComponent,
    LeaseWizardComponent,
    LeaseFormComponent
  ],
  imports: [
    SharedModule,
    LeasesRoutingModule
  ],
  schemas: [NO_ERRORS_SCHEMA]
})
export class LeasesModule {}