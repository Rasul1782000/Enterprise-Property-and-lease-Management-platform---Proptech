import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { WhatsAppChatComponent } from './whatsapp-chat.component';
import { ApiService } from '../../core/services/api.service';
import { WhatsAppSseService } from '../../core/services/whatsapp-sse.service';

describe('WhatsAppChatComponent', () => {
  let component: WhatsAppChatComponent;
  let fixture: ComponentFixture<WhatsAppChatComponent>;
  let apiServiceSpy: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
  let sseServiceSpy: {
    connect: ReturnType<typeof vi.fn>;
    disconnect: ReturnType<typeof vi.fn>;
    clearMessages: ReturnType<typeof vi.fn>;
    newMessages: ReturnType<typeof signal<unknown[]>>;
    sessionStatus: ReturnType<typeof signal<string>>;
  };

  beforeEach(async () => {
    apiServiceSpy = { get: vi.fn(), post: vi.fn() };
    sseServiceSpy = {
      connect: vi.fn(),
      disconnect: vi.fn(),
      clearMessages: vi.fn(),
      newMessages: signal<unknown[]>([]),
      sessionStatus: signal<string>('unknown'),
    };

    await TestBed.configureTestingModule({
      imports: [WhatsAppChatComponent],
      providers: [
        { provide: ApiService, useValue: apiServiceSpy },
        { provide: WhatsAppSseService, useValue: sseServiceSpy },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(WhatsAppChatComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize with empty state', () => {
    expect(component.conversations()).toEqual([]);
    expect(component.activeConversation()).toBeNull();
    expect(component.messages()).toEqual([]);
    expect(component.isConnected()).toBe(false);
  });

  it('should filter conversations by search query', () => {
    component.conversations.set([
      { id: '1', tenantName: 'Ahmed Al Rashid', phone: '+971501234567', lastMessage: 'Hello', lastMessageTime: new Date().toISOString(), unreadCount: 0 },
      { id: '2', tenantName: 'Sara Mohammed', phone: '+971509876543', lastMessage: 'Hi', lastMessageTime: new Date().toISOString(), unreadCount: 0 },
    ]);

    component.searchQuery.set('Ahmed');
    expect(component.filteredConversations().length).toBe(1);
    expect(component.filteredConversations()[0].tenantName).toBe('Ahmed Al Rashid');

    component.searchQuery.set('');
    expect(component.filteredConversations().length).toBe(2);
  });

  it('should format time correctly for today', () => {
    const today = new Date().toISOString();
    const formatted = component.formatTime(today);
    expect(formatted).toMatch(/\d{2}:\d{2}/);
  });

  it('should format time correctly for past dates', () => {
    const past = new Date(Date.now() - 86400000).toISOString();
    const formatted = component.formatTime(past);
    expect(formatted).toMatch(/\w{3}\s\d{1,2}/);
  });

  it('should return correct status icons', () => {
    expect(component.getStatusIcon('read')).toContain('text-blue-400');
    expect(component.getStatusIcon('delivered')).toContain('text-gray-400');
    expect(component.getStatusIcon('sent')).toContain('text-gray-400');
    expect(component.getStatusIcon()).toContain('pi-check');
  });

  it('should return correct connection severity', () => {
    component.isConnected.set(true);
    expect(component.getConnectionSeverity()).toBe('success');

    component.isConnected.set(false);
    expect(component.getConnectionSeverity()).toBe('danger');
  });

  it('should return correct connection label', () => {
    component.isConnected.set(true);
    expect(component.getConnectionLabel()).toBe('Connected');

    component.isConnected.set(false);
    expect(component.getConnectionLabel()).toBe('Disconnected');
  });

  it('should distinguish an unreachable gateway from an unlinked account', () => {
    component.isConnected.set(false);

    // "unknown" means the gateway never answered, which needs a different fix
    // than "no account paired yet".
    expect(component.getConnectionTooltip()).toContain('unreachable');

    component['sse'].sessionStatus.set('qr_ready');
    expect(component.getConnectionTooltip()).toContain('QR code');
  });

  it('should merge an inbound SSE message into the open conversation', () => {
    component.conversations.set([
      {
        id: '1',
        tenantName: 'Ahmed Al Rashid',
        phone: '+971501234567',
        lastMessage: 'Older text',
        lastMessageTime: new Date().toISOString(),
        unreadCount: 3,
      },
    ]);
    component.activeConversation.set(component.conversations()[0]);

    const inbound = [
      {
        from: '971501234567@c.us',
        body: 'Is my invoice ready?',
        timestamp: new Date().toISOString(),
        direction: 'inbound' as const,
      },
    ];

    // Exercises the same private merge path the SSE effect calls.
    (component as unknown as { mergeInbound: (m: unknown[]) => void }).mergeInbound(inbound);

    expect(component.messages().length).toBe(1);
    expect(component.messages()[0].body).toBe('Is my invoice ready?');
    expect(component.messages()[0].direction).toBe('inbound');
    expect(component.conversations()[0].lastMessage).toBe('Is my invoice ready?');
    expect(component.conversations()[0].unreadCount).toBe(0);
  });

  it('should ignore an inbound message from a different sender', () => {
    component.conversations.set([
      {
        id: '1',
        tenantName: 'Ahmed Al Rashid',
        phone: '+971501234567',
        lastMessage: 'Older text',
        lastMessageTime: new Date().toISOString(),
        unreadCount: 0,
      },
    ]);
    component.activeConversation.set(component.conversations()[0]);

    (component as unknown as { mergeInbound: (m: unknown[]) => void }).mergeInbound([
      {
        from: '971509999999@c.us',
        body: 'Wrong thread',
        timestamp: new Date().toISOString(),
        direction: 'inbound',
      },
    ]);

    expect(component.messages().length).toBe(0);
  });
});
