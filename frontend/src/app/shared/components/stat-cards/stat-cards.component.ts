import { Component, Input } from '@angular/core';

export interface StatCard {
  label: string;
  value: string | number;
  icon: string;
  tone: 'blue' | 'emerald' | 'amber' | 'red' | 'purple' | 'slate' | 'indigo';
  hint?: string;
}

const TONES: Record<StatCard['tone'], { chip: string; icon: string }> = {
  blue: { chip: 'bg-primary-100', icon: 'text-primary-600' },
  emerald: { chip: 'bg-zinc-100', icon: 'text-zinc-600' },
  amber: { chip: 'bg-primary-100', icon: 'text-primary-700' },
  red: { chip: 'bg-primary-600', icon: 'text-white' },
  purple: { chip: 'bg-primary-50', icon: 'text-primary-700' },
  slate: { chip: 'bg-zinc-100', icon: 'text-zinc-600' },
  indigo: { chip: 'bg-primary-100', icon: 'text-primary-700' }
};

@Component({
  selector: 'app-stat-cards',
  standalone: false,
  templateUrl: './stat-cards.component.html',
  styleUrls: ['./stat-cards.component.scss']
})
export class StatCardsComponent {
  @Input({ required: true }) cards: StatCard[] = [];

  tone(tone: StatCard['tone']): { chip: string; icon: string } {
    return TONES[tone] ?? TONES.slate;
  }
}
