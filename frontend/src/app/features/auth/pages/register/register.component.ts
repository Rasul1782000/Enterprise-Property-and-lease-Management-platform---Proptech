import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MessageService } from 'primeng/api';
import { AuthApiService } from '../../services/auth-api.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterPage {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private messages = inject(MessageService);
  private authApi = inject(AuthApiService);

  hidePassword = signal(true);
  loading = signal(false);
  error = signal('');

  registerForm: FormGroup = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]]
  });

  async onSubmit() {
    if (this.registerForm.invalid) {
      this.registerForm.markAllAsTouched();
      this.error.set('Please fix the highlighted fields and try again.');
      return;
    }
    this.error.set('');
    this.loading.set(true);
    try {
      await this.authApi.register(this.registerForm.value).toPromise();
      this.messages.add({ severity: 'success', summary: 'Account created', detail: 'Please sign in.' });
      this.router.navigate(['/login']);
    } catch (error: any) {
      const detail = error?.error?.message || 'Registration failed';
      this.error.set(detail);
      this.messages.add({ severity: 'error', summary: 'Registration failed', detail });
    } finally {
      this.loading.set(false);
    }
  }
}
