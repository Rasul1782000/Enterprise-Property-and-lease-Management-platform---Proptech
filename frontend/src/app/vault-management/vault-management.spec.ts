import { ComponentFixture, TestBed } from '@angular/core/testing';
import { VaultManagement } from './vault-management';

describe('VaultManagement', () => {
  let component: VaultManagement;
  let fixture: ComponentFixture<VaultManagement>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [VaultManagement]
    })
      .compileComponents();

    fixture = TestBed.createComponent(VaultManagement);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
