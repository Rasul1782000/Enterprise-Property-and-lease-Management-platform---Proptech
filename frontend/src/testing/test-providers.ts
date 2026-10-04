import { MessageService, ConfirmationService } from 'primeng/api';

/**
 * Global providers for the Angular unit-test builder (vitest/jsdom).
 * Components inherit ModulePage, which injects PrimeNG's MessageService
 * and ConfirmationService; they are normally registered in AppModule.
 */
export default [MessageService, ConfirmationService];
