import { NgModule, NO_ERRORS_SCHEMA } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { BuildingsRoutingModule } from './buildings-routing.module';
import { BuildingsListComponent } from './pages/buildings-list/buildings-list.component';
import { BuildingDetailComponent } from './pages/building-detail/building-detail.component';
import { BuildingFormComponent } from './components/building-form/building-form.component';

@NgModule({
  declarations: [
    BuildingsListComponent,
    BuildingDetailComponent,
    BuildingFormComponent
  ],
  imports: [
    SharedModule,
    BuildingsRoutingModule
  ],
  schemas: [NO_ERRORS_SCHEMA]
})
export class BuildingsModule {}
