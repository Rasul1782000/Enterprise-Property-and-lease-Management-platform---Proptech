import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BouncedChequeWorkflowEngine } from './bounced-cheque-workflow-engine';

describe('BouncedChequeWorkflowEngine', () => {
  let component: BouncedChequeWorkflowEngine;
  let fixture: ComponentFixture<BouncedChequeWorkflowEngine>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BouncedChequeWorkflowEngine]
    })
      .compileComponents();

    fixture = TestBed.createComponent(BouncedChequeWorkflowEngine);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
