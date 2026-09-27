import { NgModule } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { AuthRoutingModule } from './auth-routing.module';
import { LoginPage } from './pages/login/login.component';
import { ForgotPassword } from './pages/forgot-password/forgot-password';
import { ResetPasswordPage } from './pages/reset-password/reset-password';
import { Errorpage } from './pages/errorpage/errorpage';

@NgModule({
  declarations: [LoginPage, ForgotPassword, ResetPasswordPage, Errorpage],
  imports: [
    SharedModule,
    AuthRoutingModule
  ]
})
export class AuthModule {}