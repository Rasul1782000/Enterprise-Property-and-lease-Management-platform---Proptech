import { ComponentFixture, TestBed } from '@angular/core/testing';
import { EjariTawtheeqManagementConsole } from './ejari-tawtheeq-management-console';

describe('EjariTawtheeqManagementConsole', () => {
  let component: EjariTawtheeqManagementConsole;
  let fixture: ComponentFixture<EjariTawtheeqManagementConsole>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EjariTawtheeqManagementConsole]
    })
      .compileComponents();

    fixture = TestBed.createComponent(EjariTawtheeqManagementConsole);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
