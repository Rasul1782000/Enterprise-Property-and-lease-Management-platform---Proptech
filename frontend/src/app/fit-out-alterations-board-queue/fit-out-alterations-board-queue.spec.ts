import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FitOutAlterationsBoardQueue } from './fit-out-alterations-board-queue';

describe('FitOutAlterationsBoardQueue', () => {
  let component: FitOutAlterationsBoardQueue;
  let fixture: ComponentFixture<FitOutAlterationsBoardQueue>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FitOutAlterationsBoardQueue]
    })
      .compileComponents();

    fixture = TestBed.createComponent(FitOutAlterationsBoardQueue);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
