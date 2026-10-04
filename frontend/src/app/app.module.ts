import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { provideZoneChangeDetection } from '@angular/core';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { providePrimeNG } from 'primeng/config';
import { MessageService, ConfirmationService } from 'primeng/api';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';

import { routes } from './app.routes';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { errorInterceptor } from './core/interceptors/error.interceptor';
import { mockApiInterceptor } from './core/mock/mock-api.interceptor';
import { SharedModule } from './shared/shared.module';
import { AppComponent } from './app.component';

/**
 * BounceBox preset: coral drives primary action, teal supports and confirms,
 * sunshine yellow is reserved for rewards. Everything else is re-pointed in
 * styles.scss, which loads after PrimeNG injects its theme stylesheet.
 */
const PortalPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#fff5f5',
      100: '#ffe4e4',
      200: '#ffcccc',
      300: '#ffa8a8',
      400: '#ff8a8a',
      500: '#ff6b6b',
      600: '#e85d5d',
      700: '#d14a4a',
      800: '#a63a3a',
      900: '#7a2b2b',
      950: '#461616'
    }
  }
});

/*
 * The remaining severity scales (success / info / warn / danger / secondary) are
 * re-pointed in styles.scss, which loads after PrimeNG injects the theme stylesheet.
 */
@NgModule({
  declarations: [AppComponent],
  imports: [BrowserModule, SharedModule],
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes, withComponentInputBinding()),
    provideHttpClient(withInterceptors([authInterceptor, mockApiInterceptor, errorInterceptor])),
    provideAnimationsAsync(),
    providePrimeNG({
      ripple: true,
      inputVariant: 'outlined',
      theme: { preset: PortalPreset, options: { darkModeSelector: '.app-dark' } }
    }),
    MessageService,
    ConfirmationService
  ],
  bootstrap: [AppComponent]
})
export class AppModule {}
