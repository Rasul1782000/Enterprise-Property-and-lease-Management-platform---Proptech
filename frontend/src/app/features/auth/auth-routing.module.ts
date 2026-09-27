import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LoginPage } from './pages/login/login.component';
import { ForgotPassword } from './pages/forgot-password/forgot-password';
import { ResetPasswordPage } from './pages/reset-password/reset-password';
import { Errorpage } from './pages/errorpage/errorpage';

const routes: Routes = [
  { path: '', component: LoginPage },
  { path: 'forgot-password', component: ForgotPassword },
  { path: 'reset-password', component: ResetPasswordPage },
  { path: 'register', loadComponent: () => import('./pages/register/register.component').then(m => m.RegisterPage) },
  { path: 'error', component: Errorpage }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AuthRoutingModule {}