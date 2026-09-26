import { NgModule } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { MainLayoutComponent } from './main-layout.component';
import { MainLayoutRoutingModule } from './main-layout-routing.module';

@NgModule({
  declarations: [MainLayoutComponent],
  imports: [
    SharedModule,
    MainLayoutRoutingModule
  ],
  exports: [MainLayoutComponent]
})
export class MainLayoutModule {}