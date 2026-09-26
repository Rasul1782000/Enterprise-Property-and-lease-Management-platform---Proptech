import { NgModule } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { UnitsRoutingModule } from './units-routing.module';
import { UnitsListComponent } from './pages/units-list/units-list.component';
import { UnitDetailComponent } from './pages/unit-detail/unit-detail.component';
import { UnitFormComponent } from './components/unit-form/unit-form.component';

@NgModule({
  declarations: [
    UnitsListComponent,
    UnitDetailComponent,
    UnitFormComponent
  ],
  imports: [
    SharedModule,
    UnitsRoutingModule
  ]
})
export class UnitsModule {}