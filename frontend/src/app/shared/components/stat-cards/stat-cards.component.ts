import { Component, Input } from '@angular/core';

export interface StatCard {
  label: string;
  value: string | number;
  icon: string;
  tone: 'blue' | 'emerald' | 'amber' | 'red' | 'purple' | 'slate' | 'indigo';
  hint?: string;
}

/**
 * BounceBox tones: coral for attention, teal for correct and supporting
 * navigation, sunshine yellow for rewards and gentle alerts.
 */
const TONES: Record<StatCard['tone'], { chip: string; icon: string }> = {
  blue: { chip: 'bg-teal-100', icon: 'text-teal-800' },
  emerald: { chip: 'bg-teal-500', icon: 'text-white' },
  amber: { chip: 'bg-sunny-300', icon: 'text-sunny-900' },
  red: { chip: 'bg-coral-500', icon: 'text-white' },
  purple: { chip: 'bg-coral-100', icon: 'text-coral-800' },
  slate: { chip: 'bg-zinc-100', icon: 'text-zinc-600' },
  indigo: { chip: 'bg-sunny-100', icon: 'text-sunny-900' }
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
