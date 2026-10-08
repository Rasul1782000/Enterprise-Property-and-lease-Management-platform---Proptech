import { Injectable, signal } from '@angular/core';

export interface InboundMessage {
  from: string;
  body: string;
  timestamp: string;
  direction: 'inbound' | 'outbound';
}

export interface HeartbeatData {
  status: string;
  timestamp: string;
}

@Injectable({ providedIn: 'root' })
export class WhatsAppSseService {
  private eventSource: EventSource | null = null;

  newMessages = signal<InboundMessage[]>([]);
  
  connectionStatus = signal<'connected' | 'disconnected' | 'connecting'>('disconnected');

  sessionStatus = signal<string>('unknown');

  connect(): void {
    if (this.eventSource) {
      return; // Already connected
    }

    this.connectionStatus.set('connecting');

    const token = this.readToken();
    const url = token
      ? `/api/whatsapp/stream?token=${encodeURIComponent(token)}`
      : '/api/whatsapp/stream';

    this.eventSource = new EventSource(url);

    this.eventSource.addEventListener('message', (event: MessageEvent) => {
      try {
        const messages: InboundMessage[] = JSON.parse(event.data);
        this.newMessages.update((msgs) => [...msgs, ...messages]);
      } catch {
      }
    });

    this.eventSource.addEventListener('heartbeat', (event: MessageEvent) => {
      try {
        const data: HeartbeatData = JSON.parse(event.data);
        this.sessionStatus.set(data.status);
      } catch {
      }
    });

    this.eventSource.onerror = () => {
      this.connectionStatus.set('disconnected');
    };

    this.eventSource.onopen = () => {
      this.connectionStatus.set('connected');
    };
  }

  disconnect(): void {
    this.eventSource?.close();
    this.eventSource = null;
    this.connectionStatus.set('disconnected');
    this.sessionStatus.set('unknown');
  }

  clearMessages(): void {
    this.newMessages.set([]);
  }

  private readToken(): string | null {
    return (
      localStorage.getItem('token') || sessionStorage.getItem('token') || null
    );
  }
}
