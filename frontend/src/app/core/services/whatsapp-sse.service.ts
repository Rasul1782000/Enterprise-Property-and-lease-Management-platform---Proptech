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
  /**
   * Transport-level state of the EventSource, which is NOT the same thing as
   * whether the WhatsApp session is linked. Callers should read
   * `sessionStatus` for the latter.
   */
  connectionStatus = signal<'connected' | 'disconnected' | 'connecting'>('disconnected');

  /** Session lifecycle reported by the backend heartbeat ('ready', 'created', ...). */
  sessionStatus = signal<string>('unknown');

  connect(): void {
    if (this.eventSource) {
      return; // Already connected
    }

    this.connectionStatus.set('connecting');

    // EventSource cannot set an Authorization header, so the backend expects
    // the token as a query parameter and authenticates the request itself.
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
        // A malformed frame must not kill the stream.
      }
    });

    this.eventSource.addEventListener('heartbeat', (event: MessageEvent) => {
      try {
        const data: HeartbeatData = JSON.parse(event.data);
        this.sessionStatus.set(data.status);
      } catch {
        // Ignore an unreadable heartbeat.
      }
    });

    this.eventSource.onerror = () => {
      this.connectionStatus.set('disconnected');
      // EventSource auto-reconnects by default
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

  /**
   * Mirrors AuthService's token lookup. Kept local rather than injecting
   * AuthService to avoid a circular dependency (AuthService is not otherwise
   * needed here).
   */
  private readToken(): string | null {
    return (
      localStorage.getItem('token') || sessionStorage.getItem('token') || null
    );
  }
}
