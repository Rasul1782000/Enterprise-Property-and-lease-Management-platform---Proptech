declare module 'primeng/ts-helpers' {
  export type Nullable<T = any> = T | null | undefined;
  export type VoidListener = (...args: any[]) => void;
}
