import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MessageService } from 'primeng/api';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../../environments/environment';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, InputTextModule, ButtonModule],
  templateUrl: './forgot-password.html',
  styleUrl: './forgot-password.scss'
})
export class ForgotPassword {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private messages = inject(MessageService);
  private http = inject(HttpClient);

  loading = signal(false);
  error = signal('');
  success = signal(false);
  focusedField = signal<string | null>(null);

  forgotForm: FormGroup = this.fb.group({
    email: ['', [Validators.required, Validators.email]]
  });

  onFieldFocus(field: string) {
    this.focusedField.set(field);
  }

  onFieldBlur() {
    this.focusedField.set(null);
  }

  async onSubmit() {
    if (this.forgotForm.invalid) {
      this.forgotForm.markAllAsTouched();
      this.error.set('Please enter a valid email address.');
      return;
    }
    this.error.set('');
    this.loading.set(true);
    try {
      await this.http.post(`${environment.apiUrl}/auth/forgot-password`, {
        email: this.forgotForm.get('email')?.value
      }).toPromise();
      this.success.set(true);
      this.messages.add({ severity: 'success', summary: 'Email sent', detail: 'Check your inbox for password reset instructions.' });
    } catch (error: any) {
      const detail = error?.error?.message || 'Something went wrong. Please try again.';
      this.error.set(detail);
    } finally {
      this.loading.set(false);
    }
  }

  resend() {
    this.success.set(false);
  }
}
