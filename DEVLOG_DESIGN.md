---
version: alpha
name: DevLog Website Design System
description: "Dark, monospaced, terminal-inspired design system for developer blogs and technical writing platforms: phosphor-orange headings, code-first layouts and soft glowing accents on a navy-black base."
colors:
  primary: "#FB923C"
  on-primary: "#0F172A"
  secondary: "#FBBF24"
  on-secondary: "#0F172A"
  tertiary: "#22D3EE"
  tertiary-hover: "#0891B2"
  on-tertiary: "#0F172A"
  neutral: "#64748B"
  soft: "#1E293B"
  on-soft: "#F1F5F9"
  background: "#0F172A"
  on-background: "#F1F5F9"
  surface: "#1E293B"
  surface-alt: "#334155"
  on-surface: "#F1F5F9"
  on-surface-muted: "#94A3B8"
  outline: "#334155"
  success: "#FB923C"
  on-success: "#0F172A"
  error: "#F87171"
  on-error: "#0F172A"
typography:
  display:
    fontFamily: JetBrains Mono
    fontSize: 36px
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: JetBrains Mono
    fontSize: 28px
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: -0.01em
  headline-md:
    fontFamily: JetBrains Mono
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.4
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: 400
    lineHeight: 1.7
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.7
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.6
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1.2
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: 600
    lineHeight: 1.2
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 11px
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: 0.04em
  caption:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: 500
    lineHeight: 1.5
    letterSpacing: 0.01em
  overline:
    fontFamily: JetBrains Mono
    fontSize: 11px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0.1em
  code:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.65
rounded:
  none: 0px
  sm: 4px
  md: 6px
  lg: 8px
  xl: 12px
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 64px
  3xl: 96px
  gutter: 24px
  max-width: 1200px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label-lg}"
    rounded: "{rounded.sm}"
    padding: 8px 20px
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    typography: "{typography.label-lg}"
    rounded: "{rounded.sm}"
    padding: 8px 20px
  button-soft:
    backgroundColor: "{colors.soft}"
    textColor: "{colors.on-soft}"
    typography: "{typography.label-lg}"
    rounded: "{rounded.sm}"
    padding: 8px 20px
  button-link:
    backgroundColor: "transparent"
    textColor: "{colors.on-surface-muted}"
    typography: "{typography.label-lg}"
  input:
    backgroundColor: "{colors.background}"
    textColor: "{colors.on-background}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.sm}"
    padding: 8px 14px
    height: 40px
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.sm}"
    padding: 24px
  card-alt:
    backgroundColor: "{colors.soft}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.sm}"
    padding: 24px
  card-elevated:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.sm}"
    padding: 24px
  eyebrow:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    typography: "{typography.overline}"
  chip:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface-muted}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.sm}"
    padding: 4px 12px
  chip-accent:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.sm}"
    padding: 4px 12px
  stat:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    typography: "{typography.headline-md}"
  caption:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.neutral}"
    typography: "{typography.caption}"
  nav:
    backgroundColor: "{colors.background}"
    textColor: "{colors.on-background}"
    typography: "{typography.label-lg}"
    height: 64px
  footer:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    typography: "{typography.body-sm}"
    padding: 64px 24px
  badge-success:
    backgroundColor: "rgba(251, 146, 60, 0.12)"
    textColor: "{colors.primary}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.sm}"
    padding: 4px 10px
  alert-error:
    backgroundColor: "rgba(248, 113, 113, 0.12)"
    textColor: "{colors.error}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.md}"
    padding: 16px
  divider:
    backgroundColor: "{colors.outline}"
    height: 1px
  code-block:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    typography: "{typography.code}"
    rounded: "{rounded.md}"
    padding: 24px
  icon-muted:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface-muted}"
    size: 24px
---

# DevLog Website Design System

## Overview

This system is for developer blogs and technical writing platforms: long-form posts, code walkthroughs, changelogs and API references. It channels the aesthetic of a well-configured terminal — phosphor orange on dark, monospaced headings, and soft glowing accents that feel like ember on glass. Code blocks are first-class citizens, and every typographic choice serves readability under sustained technical reading. The density is standard, giving code snippets and prose equal breathing room.

## Colors

A disciplined dark palette: one ember tone for structure and action, one amber for annotation, one cyan for reference.

- **Phosphor Orange (#FB923C):** primary. Links, active states, primary actions, success states and glows.
- **Amber (#FBBF24):** secondary. Warnings, highlights and annotations.
- **Cyan (#22D3EE):** tertiary. Info accents and secondary links.
- **Slate Surface (#1E293B):** soft surface. Cards, code blocks, panels and secondary buttons.
- **Navy-Black (#0F172A):** page background; never inverted to light.

## Typography

One monospaced family for headings and code, **JetBrains Mono**: Bold for displays, SemiBold for subheads. One neutral family for reading, **Inter**: Regular for body, Medium for captions, SemiBold for labels and buttons. Headlines stay monospaced everywhere — the terminal character carries the identity.

## Layout

Post page: a big navy-black canvas, a monospaced hero title, a reading column with Inter body, fenced code blocks as full-width panels, a sidebar of metadata (timestamps, file paths, category chips) and a slate footer. Desktop uses 1200px max width with 24px gutters and 96px section gaps; tablet scales the gap to 64px, mobile to 48px.

## Elevation & Depth

Instead of traditional drop shadows, DevLog uses a dark-mode glow system — accents emit soft orange light against the navy-black base, evoking phosphor on glass. Cards rest flat at `0 0 8px rgba(251, 146, 60, 0.08)` on hover, active cards lift to `0 0 16px rgba(251, 146, 60, 0.12)`, modals glow at `0 0 32px rgba(251, 146, 60, 0.16)`. Dropdowns and overlays use `0 0 0 1px rgba(241, 245, 249, 0.06), 0 24px 48px rgba(0, 0, 0, 0.5)`. Info elements carry a cyan halo (`0 0 20px rgba(34, 211, 238, 0.15)`), warnings an amber halo (`0 0 16px rgba(251, 191, 36, 0.12)`).

## Shapes

Nearly sharp, with a 4px default that keeps the terminal feel while softening raw rectangles. Buttons, inputs, chips and cards use 4px; code blocks and panels 6px; modals 8px; large containers 12px; status dots and avatars fully round. Dividers stay at 0px. Nothing rounds beyond 12px, and decorative elements (rules, arcs, oversized blobs) are not used.

## Components

- **Primary button:** phosphor-orange pill-ish rectangle with navy text and a matching border, `8px 20px` padding; hover deepens to `#F97316` with an orange glow; disabled fades to 0.35 opacity.
- **Secondary button:** slate surface pill with light text and a `#334155` border; hover lifts to `#334155`.
- **Ghost button:** transparent, muted text; hover reveals a slate wash and light text.
- **Destructive button:** `#F87171` background, navy text, red glow on hover.
- **Text input:** 40px tall, navy background, `#334155` border, 4px radius, orange border + glow on focus, red border + glow on error, dimmed slate on disabled.
- **Card:** slate surface, `1px solid #334155` border, 4px radius, 24px padding; hover glows softly orange. Elevated variant carries a stronger border and a medium glow.
- **Filter chip:** slate pill with mono 12px muted text; selected fills phosphor orange with navy text.
- **Status chip:** uppercase JetBrains Mono 11px on a 12% alpha tinted background with a 25% alpha border — green replaced by phosphor orange for success.
- **Default list item:** 12px 16px padding, hairline `#1E293B` bottom border, leading element icon tinted `#FB923C`, secondary metadata in `#64748B` 12px.
- **Checkbox:** 16px square, 3px radius, navy fill, `#475569` border; checked fills phosphor orange with a navy checkmark; hover glows orange; focus ring `0 0 0 2px #0F172A, 0 0 0 4px #FB923C`.
- **Radio button:** 16px circle, same border treatment, inner 6px phosphor-orange dot when selected.
- **Tooltip:** inverted — light `#F1F5F9` background, navy text, 4px radius, 200ms enter delay, subtle orange glow.
- **Code block:** slate panel, 6px radius, 24px padding, JetBrains Mono 14px with green replaced by orange in the syntax palette.

## Do's and Don'ts

1. **Do** use JetBrains Mono for all headings and code — consistency with the terminal aesthetic is key.
2. **Do** give code blocks generous padding (24px) and distinguish them clearly with `#1E293B` backgrounds.
3. **Do** use the orange glow sparingly — reserve it for hover, focus, and active states only.
4. **Don't** use light backgrounds; the dark navy-black `#0F172A` is the foundation. Never invert it.
5. **Don't** mix Amber and Cyan accents in the same component — one accent per element.
6. **Do** ensure all text meets WCAG AA contrast against `#0F172A` (minimum 4.5:1 for body).
7. **Don't** use heavy box shadows — glow effects should be subtle and atmospheric, not prominent.
8. **Do** support syntax highlighting themes that respect the orange/amber/cyan palette.
9. **Don't** round corners beyond 4px for standard elements — keep the terminal sharpness intact.
10. **Do** provide a visible focus ring on all interactive elements using the orange glow for keyboard navigation.
