export const DOCUMENT_EXTENSIONS = [
  'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'
];

export const DOCUMENT_MAX_KB = 10240;

export const documentAcceptAttribute = (extensions: string[] = DOCUMENT_EXTENSIONS): string =>
  extensions.map(ext => `.${ext}`).join(',');

export const documentExtension = (filename: string): string =>
  filename.split('.').pop()?.toLowerCase() ?? '';

export const formatFileSize = (bytes: number): string =>
  bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;

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
