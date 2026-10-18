import { NgModule } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { AuthRoutingModule } from './auth-routing.module';

@NgModule({
  declarations: [],
  imports: [
    SharedModule,
    AuthRoutingModule,
  ]
})
export class AuthModule {}
