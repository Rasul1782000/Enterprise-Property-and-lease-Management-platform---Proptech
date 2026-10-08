import { NgModule } from '@angular/core';
import { SharedModule } from '@shared/shared.module';
import { InvoicesRoutingModule } from './invoices-routing.module';
import { InvoicesListComponent } from './pages/invoices-list/invoices-list.component';
import { InvoiceDetailComponent } from './pages/invoice-detail/invoice-detail.component';
import { InvoiceFormComponent } from './components/invoice-form/invoice-form.component';

@NgModule({
  declarations: [
    InvoicesListComponent,
    InvoiceDetailComponent,
    InvoiceFormComponent
  ],
  imports: [
    SharedModule,
    InvoicesRoutingModule
  ]
})
export class InvoicesModule {}
