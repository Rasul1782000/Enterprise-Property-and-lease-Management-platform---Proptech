import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { UnitsListComponent } from './pages/units-list/units-list.component';
import { UnitDetailComponent } from './pages/unit-detail/unit-detail.component';

const routes: Routes = [
  { path: '', component: UnitsListComponent },
  { path: ':id', component: UnitDetailComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class UnitsRoutingModule {}