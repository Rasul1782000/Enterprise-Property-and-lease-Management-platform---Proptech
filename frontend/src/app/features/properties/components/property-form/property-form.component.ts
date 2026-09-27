import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import { PropertiesApiService, Property, CreatePropertyDto, UpdatePropertyDto } from '../../services/properties-api.service';

export interface PropertyFormDialogData {
  mode: 'create' | 'edit';
  property?: Property;
}

@Component({
  selector: 'app-property-form',
  standalone: false,
  templateUrl: './property-form.component.html',
  styleUrls: ['./property-form.component.scss']
})
export class PropertyFormComponent implements OnInit, OnChanges {
  private fb = inject(FormBuilder);
  private api = inject(PropertiesApiService);
  private messages = inject(MessageService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Input() data: PropertyFormDialogData = { mode: 'create' };
  @Output() closed = new EventEmitter<boolean>();

  loading = signal(false);

  typeOptions = [
    { label: 'Commercial', value: 'commercial' },
    { label: 'Residential', value: 'residential' },
    { label: 'Mixed Use', value: 'mixed' },
    { label: 'Industrial', value: 'industrial' },
    { label: 'Retail', value: 'retail' },
    { label: 'Office', value: 'office' }
  ];

  form: FormGroup = this.fb.group({
    code: ['', [Validators.required, Validators.maxLength(20)]],
    name: ['', [Validators.required, Validators.maxLength(100)]],
    type: ['commercial', Validators.required],
    address: ['', [Validators.required, Validators.maxLength(255)]],
    city: ['', [Validators.required, Validators.maxLength(100)]],
    state: ['', [Validators.required, Validators.maxLength(50)]],
    zip: ['', [Validators.required, Validators.maxLength(20)]],
    country: ['USA']
  });

  get title(): string {
    return this.data.mode === 'create' ? 'Add Property' : 'Edit Property';
  }

  get submitLabel(): string {
    return this.data.mode === 'create' ? 'Create' : 'Update';
  }

  ngOnInit() {
    this.patch();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['data'] && !changes['data'].firstChange) {
      this.form.reset({ type: 'commercial', country: 'USA' });
      this.patch();
    }
  }

  private patch() {
    if (this.data.mode === 'edit' && this.data.property) {
      this.form.patchValue(this.data.property);
    }
  }

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);

    const dto = this.form.value;
    const request = this.data.mode === 'create'
      ? this.api.create(dto as CreatePropertyDto)
      : this.api.update(this.data.property!.id, dto as UpdatePropertyDto);

    request.subscribe({
      next: (saved) => {
        this.loading.set(false);
        this.messages.add({
          severity: 'success',
          summary: 'Saved',
          detail: `${saved.name} was ${this.data.mode === 'create' ? 'created' : 'updated'}.`
        });
        this.close(true);
      },
      error: () => this.loading.set(false)
    });
  }

  onCancel() {
    this.close(false);
  }

  private close(result: boolean) {
    this.closed.emit(result);
    this.visibleChange.emit(false);
  }
}
