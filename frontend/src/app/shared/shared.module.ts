import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { TagModule } from 'primeng/tag';
import { ChipModule } from 'primeng/chip';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { InputNumberModule } from 'primeng/inputnumber';
import { PasswordModule } from 'primeng/password';
import { SelectModule } from 'primeng/select';
import { MultiSelectModule } from 'primeng/multiselect';
import { DatePickerModule } from 'primeng/datepicker';
import { CheckboxModule } from 'primeng/checkbox';
import { RadioButtonModule } from 'primeng/radiobutton';
import { ToggleSwitchModule } from 'primeng/toggleswitch';
import { SliderModule } from 'primeng/slider';
import { DialogModule } from 'primeng/dialog';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { ToastModule } from 'primeng/toast';
import { TooltipModule } from 'primeng/tooltip';
import { DividerModule } from 'primeng/divider';
import { AvatarModule } from 'primeng/avatar';
import { BadgeModule } from 'primeng/badge';
import { ProgressBarModule } from 'primeng/progressbar';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { SkeletonModule } from 'primeng/skeleton';
import { TabsModule } from 'primeng/tabs';
import { StepperModule } from 'primeng/stepper';
import { PaginatorModule } from 'primeng/paginator';
import { DrawerModule } from 'primeng/drawer';
import { MenuModule } from 'primeng/menu';
import { IconFieldModule } from 'primeng/iconfield';
import { InputIconModule } from 'primeng/inputicon';
import { InputGroupModule } from 'primeng/inputgroup';
import { FloatLabelModule } from 'primeng/floatlabel';
import { MessageModule } from 'primeng/message';
import { TimelineModule } from 'primeng/timeline';
import { ChartModule } from 'primeng/chart';
import { PanelModule } from 'primeng/panel';
import { ToolbarModule } from 'primeng/toolbar';
import { ListboxModule } from 'primeng/listbox';

import { FilterBarComponent } from './components/filter-bar/filter-bar.component';
import { ConfirmDialogComponent } from './components/confirm-dialog/confirm-dialog.component';
import { StatCardsComponent } from './components/stat-cards/stat-cards.component';

const PRIME_MODULES = [
  ButtonModule, CardModule, TableModule, TagModule, ChipModule, InputTextModule, TextareaModule,
  InputNumberModule, PasswordModule, SelectModule, MultiSelectModule, DatePickerModule, CheckboxModule,
  RadioButtonModule, ToggleSwitchModule, SliderModule, DialogModule, ConfirmDialogModule, ToastModule,
  TooltipModule, DividerModule, AvatarModule, BadgeModule, ProgressBarModule, ProgressSpinnerModule,
  SkeletonModule, TabsModule, StepperModule, PaginatorModule, DrawerModule, MenuModule, IconFieldModule,
  InputIconModule, InputGroupModule, FloatLabelModule, MessageModule, TimelineModule, ChartModule,
  PanelModule, ToolbarModule, ListboxModule
];

@NgModule({
  declarations: [
    FilterBarComponent,
    ConfirmDialogComponent,
    StatCardsComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    ...PRIME_MODULES
  ],
  exports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    ...PRIME_MODULES,
    FilterBarComponent,
    ConfirmDialogComponent,
    StatCardsComponent
  ]
})
export class SharedModule {}
