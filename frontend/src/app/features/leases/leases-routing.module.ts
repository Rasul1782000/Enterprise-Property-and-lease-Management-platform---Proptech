import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LeasesListComponent } from './pages/leases-list/leases-list.component';
import { LeaseDetailComponent } from './pages/lease-detail/lease-detail.component';
import { LeaseWizardComponent } from './pages/lease-wizard/lease-wizard.component';

const routes: Routes = [
  { path: '', component: LeasesListComponent },
  { path: 'create', component: LeaseWizardComponent },
  { path: ':id', component: LeaseDetailComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class LeasesRoutingModule {}