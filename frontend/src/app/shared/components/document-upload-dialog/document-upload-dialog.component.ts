import { Component, EventEmitter, Input, Output, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import {
  DOCUMENT_EXTENSIONS,
  DOCUMENT_MAX_KB,
  documentAcceptAttribute,
  documentExtension,
  documentIcon,
  formatFileSize
} from '../../../core/constants/document-upload';

export interface DocumentCategoryOption {
  label: string;
  value: string;
}

export interface PickedDocument {
  file: File;
  name: string;
  category?: string;
}

@Component({
  selector: 'app-document-upload-dialog',
  standalone: false,
  templateUrl: './document-upload-dialog.component.html',
  styleUrls: ['./document-upload-dialog.component.scss']
})
export class DocumentUploadDialogComponent {
  private fb = inject(FormBuilder);
  private messages = inject(MessageService);

  @Input() header = 'Upload Document';
  @Input() submitLabel = 'Upload';
  
  @Input() busy = false;
  
  @Input() categories: DocumentCategoryOption[] = [];
  @Input() allowedExtensions: string[] = DOCUMENT_EXTENSIONS;
  @Input() maxKb = DOCUMENT_MAX_KB;

  @Output() picked = new EventEmitter<PickedDocument>();
  @Output() cancelled = new EventEmitter<void>();

  readonly selection = signal<PickedDocument | null>(null);
  readonly dragging = signal(false);
  
  readonly rejection = signal<string | null>(null);

  form: FormGroup = this.fb.group({
    name: ['', [Validators.maxLength(255)]],
    category: ['other']
  });

  get acceptAttribute(): string {
    return documentAcceptAttribute(this.allowedExtensions);
  }

  get maxMb(): number {
    return Math.round((this.maxKb / 1024) * 10) / 10;
  }

  get selectedExtension(): string {
    return documentExtension(this.selection()?.file.name ?? '');
  }

  get selectedSize(): string {
    return formatFileSize(this.selection()?.file.size ?? 0);
  }

  onFilesSelected(files: FileList | File[] | null): void {
    if (!files) return;
    const file = Array.from(files)[0];
    if (file) this.stage(file);
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
    this.onFilesSelected(event.dataTransfer?.files ?? null);
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(true);
  }

  onDragLeave(): void {
    this.dragging.set(false);
  }

  onFileInputChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.onFilesSelected(input.files);
    input.value = '';
  }

  private stage(file: File): void {
    const extension = documentExtension(file.name);

    if (!this.allowedExtensions.includes(extension)) {
      this.reject(`"${file.name}" is not a supported file type.`);
      return;
    }
    if (file.size > this.maxKb * 1024) {
      this.reject(`"${file.name}" is ${formatFileSize(file.size)}; the limit is ${this.maxMb} MB.`);
      return;
    }

    this.rejection.set(null);
    this.selection.set({ file, name: file.name, category: this.form.value.category || undefined });
  }

  private reject(reason: string): void {
    this.selection.set(null);
    this.rejection.set(reason);
    this.messages.add({ severity: 'warn', summary: 'File not accepted', detail: reason, life: 4000 });
  }

  clear(): void {
    this.selection.set(null);
    this.rejection.set(null);
  }

  submit(): void {
    const selected = this.selection();
    if (!selected || this.form.invalid) return;

    this.picked.emit({
      file: selected.file,
      name: String(this.form.value.name ?? '').trim() || selected.file.name,
      category: this.categories.length ? String(this.form.value.category || 'other') : undefined
    });
  }

  iconFor(extension: string): string {
    return documentIcon(extension);
  }

  formatKb(bytes: number): string {
    return formatFileSize(bytes);
  }

  onCancel(): void {
    this.cancelled.emit();
  }
}
