import { ComponentFixture, TestBed } from '@angular/core/testing';
import { LeaseRenewalEngine } from './lease-renewal-engine';

describe('LeaseRenewalEngine', () => {
  let component: LeaseRenewalEngine;
  let fixture: ComponentFixture<LeaseRenewalEngine>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LeaseRenewalEngine]
    })
      .compileComponents();

    fixture = TestBed.createComponent(LeaseRenewalEngine);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
