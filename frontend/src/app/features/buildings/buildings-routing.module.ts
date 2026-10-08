import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BuildingsListComponent } from './pages/buildings-list/buildings-list.component';
import { BuildingDetailComponent } from './pages/building-detail/building-detail.component';

const routes: Routes = [
  { path: '', component: BuildingsListComponent },
  { path: ':id', component: BuildingDetailComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class BuildingsRoutingModule {}
