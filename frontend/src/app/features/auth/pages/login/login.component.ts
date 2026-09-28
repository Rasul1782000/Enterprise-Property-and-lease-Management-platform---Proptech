import { Component, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { MessageService } from 'primeng/api';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: false,
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginPage {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private messages = inject(MessageService);
  private auth = inject(AuthService);

  hidePassword = signal(true);
  loading = signal(false);
  error = signal('');

  loginForm: FormGroup = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(6)]],
    remember: [false]
  });

  async onSubmit() {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      this.error.set('Please fix the highlighted fields and try again.');
      return;
    }
    this.error.set('');
    this.loading.set(true);
    try {
      const { email, password, remember } = this.loginForm.getRawValue();
      await this.auth.login(email, password, remember).toPromise();
      this.messages.add({ severity: 'success', summary: 'Welcome', detail: 'You are signed in.' });
      this.router.navigate(['/dashboard']);
    } catch (error: any) {
      const detail = error?.status === 429
        ? 'Too many sign-in attempts. Please wait a moment and try again.'
        : 'We could not verify those credentials. Check your email and password and try again.';
      this.error.set(detail);
      this.messages.add({ severity: 'error', summary: 'Sign in failed', detail });
    } finally {
      this.loading.set(false);
    }
  }
}
