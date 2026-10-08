import {
  Component,
  signal,
  computed,
  effect,
  inject,
  OnInit,
  OnDestroy,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AvatarModule } from 'primeng/avatar';
import { BadgeModule } from 'primeng/badge';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { TagModule } from 'primeng/tag';
import { SkeletonModule } from 'primeng/skeleton';
import { TooltipModule } from 'primeng/tooltip';
import { firstValueFrom } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { InboundMessage, WhatsAppSseService } from '../../core/services/whatsapp-sse.service';

export interface ChatMessage {
  id: string;
  from: string;
  body: string;
  timestamp: string;
  direction: 'inbound' | 'outbound';
  status?: 'sent' | 'delivered' | 'read';
}

export interface Conversation {
  id: string;
  tenantName: string;
  phone: string;
  lastMessage: string;
  lastMessageTime: string;
  unreadCount: number;
  avatar?: string;
}

@Component({
  selector: 'app-whatsapp-chat',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AvatarModule,
    BadgeModule,
    InputTextModule,
    ButtonModule,
    TagModule,
    SkeletonModule,
    TooltipModule,
  ],
  templateUrl: './whatsapp-chat.component.html',
  styleUrl: './whatsapp-chat.component.scss',
})
export class WhatsAppChatComponent implements OnInit, OnDestroy {
  private api = inject(ApiService);
  private sse = inject(WhatsAppSseService);

  conversations = signal<Conversation[]>([]);
  activeConversation = signal<Conversation | null>(null);
  messages = signal<ChatMessage[]>([]);
  newMessage = signal('');
  isConnected = signal(false);
  isLoading = signal(false);
  searchQuery = signal('');
  loadError = signal<string | null>(null);

  private pendingInbound = signal<InboundMessage[]>([]);

  filteredConversations = computed(() => {
    const query = this.searchQuery().toLowerCase();
    if (!query) return this.conversations();
    return this.conversations().filter(
      (c) =>
        c.tenantName.toLowerCase().includes(query) ||
        c.phone.includes(query)
    );
  });

  ngOnInit(): void {
    this.checkConnection();
    this.loadConversations();
    this.sse.connect();

    effect(() => {
      const incoming = this.sse.newMessages();
      if (incoming.length === 0) {
        return;
      }
      this.sse.clearMessages();
      this.mergeInbound(incoming);
    });
  }

  ngOnDestroy(): void {
    this.sse.disconnect();
  }

  private normalizePhone(value: string): string {
    const local = value.split('@')[0] ?? '';
    return local.replace(/[^0-9]/g, '');
  }

  private mergeInbound(incoming: InboundMessage[]): void {
    const active = this.activeConversation();
    if (!active) {
      return;
    }

    const activeDigits = this.normalizePhone(active.phone);

    const relevant = incoming.filter(
      (m) => this.normalizePhone(m.from) === activeDigits
    );

    if (relevant.length === 0) {
      return;
    }

    const mapped: ChatMessage[] = relevant.map((m, index) => ({
      id: `inbound-${m.timestamp}-${index}`,
      from: m.from,
      body: m.body,
      timestamp: m.timestamp,
      direction: 'inbound',
    }));

    this.messages.update((msgs) => [...msgs, ...mapped]);

    const latest = mapped[mapped.length - 1];
    this.conversations.update((convs) =>
      convs.map((c) =>
        c.id === active.id
          ? {
              ...c,
              lastMessage: latest.body,
              lastMessageTime: latest.timestamp,
              unreadCount: 0,
            }
          : c
      )
    );
  }

  async checkConnection(): Promise<void> {
    try {
      const res = await firstValueFrom(
        this.api.get<{ connected: boolean }>('whatsapp/status')
      );
      this.isConnected.set(res.connected);
    } catch {
      this.isConnected.set(false);
    }
  }

  async loadConversations(): Promise<void> {
    this.conversations.set([
      {
        id: '1',
        tenantName: 'Ahmed Al Rashid',
        phone: '+971501234567',
        lastMessage: 'When is the maintenance scheduled?',
        lastMessageTime: new Date().toISOString(),
        unreadCount: 2,
      },
      {
        id: '2',
        tenantName: 'Sara Mohammed',
        phone: '+971509876543',
        lastMessage: 'Thank you for the quick response!',
        lastMessageTime: new Date(Date.now() - 3600000).toISOString(),
        unreadCount: 0,
      },
      {
        id: '3',
        tenantName: 'Omar Hassan',
        phone: '+971505551234',
        lastMessage: 'I have a question about the parking',
        lastMessageTime: new Date(Date.now() - 86400000).toISOString(),
        unreadCount: 1,
      },
    ]);
  }

  async loadMessages(conversation: Conversation): Promise<void> {
    this.isLoading.set(true);
    this.activeConversation.set(conversation);
    this.loadError.set(null);

    try {
      const res = await firstValueFrom(
        this.api.get<{ messages: ChatMessage[] }>('whatsapp/history', {
          phone: conversation.phone,
          limit: 50,
        })
      );
      this.messages.set(
        (res.messages ?? []).slice().sort(
          (a, b) => Date.parse(a.timestamp) - Date.parse(b.timestamp)
        )
      );
    } catch {
      this.messages.set([]);
      this.loadError.set('Could not load message history.');
    } finally {
      this.isLoading.set(false);
    }
  }

  async sendMessage(): Promise<void> {
    const text = this.newMessage().trim();
    const conversation = this.activeConversation();

    if (!text || !conversation) return;

    const tempMessage: ChatMessage = {
      id: `temp-${Date.now()}`,
      from: 'me',
      body: text,
      timestamp: new Date().toISOString(),
      direction: 'outbound',
      status: 'sent',
    };
    this.messages.update((msgs) => [...msgs, tempMessage]);
    this.newMessage.set('');

    try {
      await firstValueFrom(
        this.api.post<{ success: boolean }>('whatsapp/send', {
          phone: conversation.phone,
          message: text,
        })
      );
    } catch {
      this.messages.update((msgs) =>
        msgs.filter((m) => m.id !== tempMessage.id)
      );
      this.newMessage.set(text);
    }
  }

  formatTime(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const isToday = date.toDateString() === now.toDateString();

    if (isToday) {
      return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
      });
    }

    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  }

  getStatusIcon(status?: string): string {
    switch (status) {
      case 'read':
        return 'pi pi-check-double text-blue-400';
      case 'delivered':
        return 'pi pi-check-double text-gray-400';
      case 'sent':
        return 'pi pi-check text-gray-400';
      default:
        return 'pi pi-check';
    }
  }

  getConnectionSeverity(): 'success' | 'danger' {
    return this.isConnected() ? 'success' : 'danger';
  }

  getConnectionLabel(): string {
    return this.isConnected() ? 'Connected' : 'Disconnected';
  }

  getConnectionTooltip(): string {
    if (this.isConnected()) {
      return 'WhatsApp session is linked and ready to send.';
    }

    const session = this.sse.sessionStatus();
    if (session === 'unknown') {
      return 'WhatsApp gateway is unreachable. Check the OpenWA container on port 2785.';
    }
    if (session === 'created' || session === 'initializing' || session === 'qr_ready') {
      return 'No WhatsApp account linked yet. Scan the QR code at http://localhost:2785';
    }
    return `WhatsApp session status: ${session}`;
  }
}
