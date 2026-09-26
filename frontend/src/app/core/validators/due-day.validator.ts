import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

export function dueDayValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const value = control.value;
    if (value == null || value === '') {
      return null;
    }
    const num = parseInt(value, 10);
    if (isNaN(num)) {
      return { dueDayInvalid: 'Must be a number' };
    }
    if (num < 1 || num > 28) {
      return { dueDayRange: 'Must be between 1 and 28' };
    }
    return null;
  };
}