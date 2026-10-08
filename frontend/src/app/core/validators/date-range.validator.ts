import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

export function dateRangeValidator(minDays = 1): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const value = control.parent?.value;
    if (!value || !value.start_date || !value.end_date) {
      return null;
    }

    const startDate = new Date(value.start_date);
    const endDate = new Date(value.end_date);
    const diffTime = Math.abs(endDate.getTime() - startDate.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < minDays) {
      return { dateRange: { required: minDays, actual: diffDays } };
    }
    return null;
  };
}
