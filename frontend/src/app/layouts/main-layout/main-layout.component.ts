import { Component, DestroyRef, ElementRef, inject, viewChild } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService, MessageService } from 'primeng/api';
import { AuthService } from '@core/services/auth.service';
import { PropertyContextService } from '@core/services/property-context.service';

interface NavItem {
  label: string;
  icon: string;
  route: string;
  exact?: boolean;
  badge?: string;
  tag?: string;
}

interface NavSection {
  heading: string;
  items: NavItem[];
}

@Component({
  selector: 'app-main-layout',
  standalone: false,
  templateUrl: './main-layout.component.html',
  styleUrls: ['./main-layout.component.scss']
})
export class MainLayoutComponent {
  auth = inject(AuthService);
  ctx = inject(PropertyContextService);
  private router = inject(Router);
  private confirmation = inject(ConfirmationService);
  private messages = inject(MessageService);

  /** Primary links, grouped so the operational modules don't swamp the core lists. */
  navSections: NavSection[] = [
    {
      heading: 'Overview',
      items: [
        { label: 'Home', icon: 'pi pi-home', route: '/dashboard', exact: true },
        { label: 'Maintenance', icon: 'pi pi-wrench', route: '/buildings' }
      ]
    },
    {
      heading: 'Leasing',
      items: [
        { label: 'Rental Application', icon: 'pi pi-file-edit', route: '/leases' },
        { label: 'Lease Agreements', icon: 'pi pi-file', route: '/lease-agreement' },
        { label: 'Lease Renewal', icon: 'pi pi-sync', route: '/lease-renewal-engine', tag: 'New' }
      ]
    },
    {
      heading: 'Portfolio',
      items: [
        { label: 'Listing', icon: 'pi pi-building', route: '/properties', badge: '3' },
        { label: 'Contact', icon: 'pi pi-user', route: '/tenants' },
        { label: 'Units', icon: 'pi pi-th-large', route: '/units' }
      ]
    },
    {
      heading: 'Operations',
      items: [
        { label: 'Vault Management', icon: 'pi pi-box', route: '/vault-management' },
        { label: 'Cheque Vault', icon: 'pi pi-wallet', route: '/post-date-cheque-vault' },
        { label: 'Bounced Cheques', icon: 'pi pi-exclamation-circle', route: '/bounced-cheque-workflow', tag: 'New' },
        { label: 'Ejari Tawtheeq', icon: 'pi pi-verified', route: '/ejari-tawtheeq-console' },
        { label: 'Service & Escrow', icon: 'pi pi-building-columns', route: '/service-charge-and-escrow' },
        { label: 'Fit-out Board', icon: 'pi pi-hammer', route: '/fit-out-alterations-board' }
      ]
    }
  ];

  /** Flat view of every link, used by the "skip to" list and the active check. */
  nav: NavItem[] = this.navSections.flatMap(section => section.items);

  profileMenu = [
    { label: 'Profile', icon: 'pi pi-user', command: () => this.router.navigate(['/tenants']) },
    { label: 'Units', icon: 'pi pi-th-large', command: () => this.router.navigate(['/units']) },
    { label: 'Logout', icon: 'pi pi-sign-out', command: () => this.logout() }
  ];

  get initial(): string {
    return (this.auth.user()?.name || 'J').charAt(0).toUpperCase();
  }

  /** Drawer visibility on narrow screens only; the top bar is always pinned. */
  navOpen = false;

  /** Heading of the currently expanded top-bar group, or null when all are shut. */
  openGroup: string | null = null;

  private readonly navToggleRef = viewChild<ElementRef<HTMLButtonElement>>('navToggle');
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    document.addEventListener('keydown', this.onKeydown);
    // Clicking anywhere outside a group trigger dismisses the open panel; the
    // trigger's own handler stops propagation so it can toggle instead.
    document.addEventListener('click', this.onDocumentClick);
    this.destroyRef.onDestroy(() => {
      document.removeEventListener('keydown', this.onKeydown);
      document.removeEventListener('click', this.onDocumentClick);
      document.body.style.overflow = '';
    });
  }

  private readonly onDocumentClick = (): void => {
    this.openGroup = null;
  };

  private readonly onKeydown = (event: KeyboardEvent): void => {
    if (event.key !== 'Escape') return;
    if (this.openGroup) {
      this.closeGroup();
      return;
    }
    if (this.navOpen) this.closeNav(true);
  };

  toggleGroup(heading: string, event: Event): void {
    event.stopPropagation();
    this.openGroup = this.openGroup === heading ? null : heading;
  }

  closeGroup(): void {
    this.openGroup = null;
  }

  /** A group reads as current when any of its links matches the live URL. */
  isSectionActive(section: NavSection): boolean {
    return section.items.some(item => this.router.isActive(item.route, !!item.exact));
  }

  toggleNav(): void {
    this.navOpen = !this.navOpen;
    this.openGroup = null;
    document.body.style.overflow = this.navOpen ? 'hidden' : '';
  }

  /** `restoreFocus` is only for dismissals — following a link must keep focus put. */
  closeNav(restoreFocus = false): void {
    if (!this.navOpen) return;
    this.navOpen = false;
    this.openGroup = null;
    document.body.style.overflow = '';
    if (restoreFocus) this.navToggleRef()?.nativeElement.focus();
  }

  logout(): void {
    this.confirmation.confirm({
      header: 'Sign out',
      message: 'You will be returned to the login screen.',
      icon: 'pi pi-sign-out',
      acceptLabel: 'Sign out',
      rejectLabel: 'Stay',
      accept: () => {
        this.auth.logout().subscribe({
          next: () => this.router.navigate(['/login']),
          error: () => { this.auth.clear(); this.router.navigate(['/login']); }
        });
        this.messages.add({ severity: 'info', summary: 'Signed out', detail: 'See you soon.', life: 2000 });
      }
    });
  }
}
