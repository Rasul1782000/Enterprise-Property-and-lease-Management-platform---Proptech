import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged } from 'rxjs';

export interface FilterOption {
  label: string;
  value: string;
}

export interface FilterEvent {
  search: string;
  status: string;
}

@Component({
  selector: 'app-filter-bar',
  standalone: false,
  templateUrl: './filter-bar.component.html',
  styleUrls: ['./filter-bar.component.scss']
})
export class FilterBarComponent {
  private fb = inject(FormBuilder);

  @Input() statusOptions: FilterOption[] = [];
  @Input() searchPlaceholder = 'Search by name, code, address...';
  @Output() filterChange = new EventEmitter<FilterEvent>();

  form: FormGroup = this.fb.group({
    search: [''],
    status: ['']
  });

  constructor() {
    this.form.valueChanges
      .pipe(debounceTime(250), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe(value => this.filterChange.emit({ search: value.search || '', status: value.status || '' }));
  }

  hasFilters(): boolean {
    return !!this.form.get('search')?.value || !!this.form.get('status')?.value;
  }

  clearFilters(): void {
    this.form.setValue({ search: '', status: '' });
  }
}
