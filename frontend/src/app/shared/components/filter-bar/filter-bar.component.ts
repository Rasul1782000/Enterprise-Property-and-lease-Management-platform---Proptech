import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

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
  @Output() filterChange = new EventEmitter<FilterEvent>();

  form: FormGroup = this.fb.group({
    search: [''],
    status: ['']
  });

  constructor() {
    this.form.valueChanges.subscribe(value => {
      this.filterChange.emit({ search: value.search || '', status: value.status || '' });
    });
  }

  hasFilters(): boolean {
    return !!this.form.get('search')?.value || !!this.form.get('status')?.value;
  }

  clearFilters() {
    this.form.reset({ search: '', status: '' });
  }
}