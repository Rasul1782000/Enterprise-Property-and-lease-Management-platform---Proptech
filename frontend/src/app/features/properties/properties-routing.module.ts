import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PropertiesListComponent } from './pages/properties-list/properties-list.component';
import { PropertyDetailComponent } from './pages/property-detail/property-detail.component';

const routes: Routes = [
  { path: '', component: PropertiesListComponent },
  { path: ':id', component: PropertyDetailComponent }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PropertiesRoutingModule {}
