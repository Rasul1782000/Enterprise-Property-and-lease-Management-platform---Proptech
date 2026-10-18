import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MessageService } from 'primeng/api';
import { InputTextModule } from 'primeng/inputtext';
import { CheckboxModule } from 'primeng/checkbox';
import { ButtonModule } from 'primeng/button';
import { IconFieldModule } from 'primeng/iconfield';
import { InputIconModule } from 'primeng/inputicon';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, InputTextModule, CheckboxModule, ButtonModule, IconFieldModule, InputIconModule],
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
  focusedField = signal<string | null>(null);

  loginForm: FormGroup = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(6)]],
    remember: [false]
  });

  demoAccounts = [
    { role: 'Admin', email: 'admin@lottly.com', password: 'DemoPassword123!' },
    { role: 'Manager', email: 'manager@lottly.com', password: 'DemoPassword123!' },
    { role: 'Agent', email: 'agent@lottly.com', password: 'DemoPassword123!' }
  ];

  fillDemo(email: string, password: string) {
    this.loginForm.patchValue({ email, password });
    this.error.set('');
  }

  onFieldFocus(field: string) {
    this.focusedField.set(field);
  }

  onFieldBlur() {
    this.focusedField.set(null);
  }

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
      this.messages.add({ severity: 'success', summary: 'Welcome back', detail: 'You have been signed in successfully.' });
      this.router.navigate(['/dashboard']);
    } catch (error: any) {
      const detail = error?.status === 429
        ? 'Too many sign-in attempts. Please try again later.'
        : 'Invalid email or password. Please try again.';
      this.error.set(detail);
    } finally {
      this.loading.set(false);
    }
  }
}
