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

/** Orange-on-neutral preset: the brand accent is orange, everything else is neutral. */
const PortalPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#fff8ed',
      100: '#ffefd4',
      200: '#fedcab',
      300: '#fdc179',
      400: '#fba338',
      500: '#f88b0c',
      600: '#ea8a0c',
      700: '#c26a06',
      800: '#9a510d',
      900: '#7c410f',
      950: '#431f05'
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
      inputVariant: 'filled',
      theme: { preset: PortalPreset, options: { darkModeSelector: '.app-dark' } }
    }),
    MessageService,
    ConfirmationService
  ],
  bootstrap: [AppComponent]
})
export class AppModule {}
