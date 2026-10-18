import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MessageService } from 'primeng/api';
import { InputTextModule } from 'primeng/inputtext';
import { CheckboxModule } from 'primeng/checkbox';
import { ButtonModule } from 'primeng/button';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, InputTextModule, CheckboxModule, ButtonModule],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.scss']
})
export class RegisterPage {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private messages = inject(MessageService);
  private auth = inject(AuthService);

  hidePassword = signal(true);
  loading = signal(false);
  error = signal('');
  focusedField = signal<string | null>(null);
  passwordStrength = signal<{ score: number; checks: Record<string, boolean> }>({
    score: 0,
    checks: { length: false, uppercase: false, lowercase: false, number: false, symbol: false }
  });

  registerForm: FormGroup = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
    terms: [false, [Validators.requiredTrue]]
  }, { validators: this.passwordMatchValidator });

  private passwordMatchValidator(group: FormGroup) {
    const password = group.get('password')?.value;
    const confirm = group.get('password_confirmation')?.value;
    return password === confirm ? null : { passwordMismatch: true };
  }

  strengthItems = [
    { key: 'length', label: 'At least 8 characters' },
    { key: 'uppercase', label: 'One uppercase letter' },
    { key: 'lowercase', label: 'One lowercase letter' },
    { key: 'number', label: 'One number' },
    { key: 'symbol', label: 'One special character' }
  ];

  checkPasswordStrength(password: string) {
    const checks = {
      length: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /[0-9]/.test(password),
      symbol: /[^A-Za-z0-9]/.test(password)
    };
    const score = Object.values(checks).filter(Boolean).length;
    this.passwordStrength.set({ score, checks });
  }

  getStrengthLabel(): string {
    const s = this.passwordStrength().score;
    if (s <= 1) return 'Too weak';
    if (s <= 2) return 'Weak';
    if (s <= 3) return 'Fair';
    if (s <= 4) return 'Good';
    return 'Strong';
  }

  getStrengthColor(): string {
    const s = this.passwordStrength().score;
    if (s <= 1) return '#ff3b30';
    if (s <= 2) return '#ff9500';
    if (s <= 3) return '#ffcc00';
    if (s <= 4) return '#007aff';
    return '#34c759';
  }

  onFieldFocus(field: string) {
    this.focusedField.set(field);
  }

  onFieldBlur() {
    this.focusedField.set(null);
  }

  async onSubmit() {
    if (this.registerForm.invalid) {
      this.registerForm.markAllAsTouched();
      this.error.set('Please fix the highlighted fields and try again.');
      return;
    }
    this.error.set('');
    this.loading.set(true);
    try {
      const { name, email, password, password_confirmation } = this.registerForm.getRawValue();
      await this.auth.register({ name, email, password, password_confirmation }).toPromise();
      this.messages.add({ severity: 'success', summary: 'Account created', detail: 'Please sign in with your new credentials.' });
      this.router.navigate(['/login']);
    } catch (error: any) {
      const detail = error?.error?.message || error?.message || 'Registration failed. Please try again.';
      this.error.set(detail);
    } finally {
      this.loading.set(false);
    }
  }
}
