import { NgModule, NO_ERRORS_SCHEMA } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { PropertiesRoutingModule } from './properties-routing.module';
import { PropertiesListComponent } from './pages/properties-list/properties-list.component';
import { PropertyDetailComponent } from './pages/property-detail/property-detail.component';
import { PropertyFormComponent } from './components/property-form/property-form.component';

@NgModule({
  declarations: [
    PropertiesListComponent,
    PropertyDetailComponent,
    PropertyFormComponent
  ],
  imports: [
    SharedModule,
    PropertiesRoutingModule
  ],
  schemas: [NO_ERRORS_SCHEMA]
})
export class PropertiesModule {}
