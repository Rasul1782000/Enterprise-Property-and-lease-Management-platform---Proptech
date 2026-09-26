export interface ApiParams {
  page?: number;
  per_page?: number;
  sort?: string;
  direction?: 'asc' | 'desc';
  search?: string;
  [key: string]: any;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}