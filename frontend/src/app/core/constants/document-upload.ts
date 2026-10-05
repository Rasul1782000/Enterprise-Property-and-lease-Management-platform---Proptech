/**
 * File-upload constraints shared by the portal. They mirror the backend's
 * `mimes` and `max` rules on `POST /tenants/{tenant}/documents`, so a bad file
 * is rejected before a pointless round trip. The backend stays the authority.
 */

/** Extensions the backend accepts. */
export const DOCUMENT_EXTENSIONS = [
  'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'
];

/** Per-file size ceiling in KB. */
export const DOCUMENT_MAX_KB = 10240;

/** Extensions rendered as an `accept` attribute, e.g. `.pdf,.jpg`. */
export const documentAcceptAttribute = (extensions: string[] = DOCUMENT_EXTENSIONS): string =>
  extensions.map(ext => `.${ext}`).join(',');

/** Lower-cased extension without the dot, e.g. `pdf`. */
export const documentExtension = (filename: string): string =>
  filename.split('.').pop()?.toLowerCase() ?? '';

/** Human-readable file size. */
export const formatFileSize = (bytes: number): string =>
  bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/** PrimeIcon matching a file extension, so a staged file reads at a glance. */
export const documentIcon = (extension: string): string => {
  switch (extension) {
    case 'pdf': return 'pi pi-file-pdf';
    case 'jpg': case 'jpeg': case 'png': case 'webp': return 'pi pi-image';
    case 'xls': case 'xlsx': case 'csv': return 'pi pi-table';
    case 'doc': case 'docx': case 'txt': return 'pi pi-file';
    case 'zip': return 'pi pi-folder';
    default: return 'pi pi-paperclip';
  }
};