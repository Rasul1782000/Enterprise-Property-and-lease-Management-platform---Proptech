import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PostDateChequeVault } from './post-date-cheque-vault';

describe('PostDateChequeVault', () => {
  let component: PostDateChequeVault;
  let fixture: ComponentFixture<PostDateChequeVault>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PostDateChequeVault]
    })
      .compileComponents();

    fixture = TestBed.createComponent(PostDateChequeVault);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
