import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ServiceChargeAndEscrowManagement } from './service-charge-and-escrow-management';

describe('ServiceChargeAndEscrowManagement', () => {
  let component: ServiceChargeAndEscrowManagement;
  let fixture: ComponentFixture<ServiceChargeAndEscrowManagement>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ServiceChargeAndEscrowManagement]
    })
      .compileComponents();

    fixture = TestBed.createComponent(ServiceChargeAndEscrowManagement);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
