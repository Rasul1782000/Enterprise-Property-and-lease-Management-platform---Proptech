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

/** A category a document can be filed under. */
export interface DocumentCategoryOption {
  label: string;
  value: string;
}

/** A file the user has picked but not yet handed to the uploader. */
export interface PickedDocument {
  file: File;
  name: string;
  category?: string;
}

/**
 * Collects one file plus an optional display name and category. Deliberately
 * knows nothing about where the file is sent: it emits the picked document and
 * lets the host own the API call, so it is reusable beyond tenants.
 *
 * Validation mirrors the backend's upload rule so obvious mistakes are caught
 * before a round trip; the backend remains the authority.
 */
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
  /** True while the host is sending the file; locks the dialog against dismissal. */
  @Input() busy = false;
  /** Empty hides the category field, and the emitted document carries no category. */
  @Input() categories: DocumentCategoryOption[] = [];
  @Input() allowedExtensions: string[] = DOCUMENT_EXTENSIONS;
  @Input() maxKb = DOCUMENT_MAX_KB;

  @Output() picked = new EventEmitter<PickedDocument>();
  @Output() cancelled = new EventEmitter<void>();

  /** Set once a valid file is chosen; drives the disabled state of the submit button. */
  readonly selection = signal<PickedDocument | null>(null);
  readonly dragging = signal(false);
  /** Message describing why the last chosen file was rejected, or null when it was accepted. */
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
    // Reset so re-picking the same file still fires a change event.
    input.value = '';
  }

  /** Validates a file client-side and, when it passes, stages it for upload. */
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

    // The name and category chosen after picking the file win over the
    // defaults captured at selection time.
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