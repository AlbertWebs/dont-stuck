<?php

namespace Tests\Feature;

use App\Mail\ClientLoginCodeMail;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\Borrower;
use App\Models\ClientLoginCode;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\ClientLoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_code_email_includes_branding_security_details_and_invalid_preview_notice(): void
    {
        $mail = new ClientLoginCodeMail('654321', true);
        $html = $mail->render();
        $text = view('mail.client-login-code-text', $mail->buildViewData())->render();

        $this->assertSame('[VISUAL TEST] Your Don\'t Stuck Solutions sign-in code', $mail->envelope()->subject);
        $this->assertStringContainsString('ENRICHING LIVES', $html);
        $this->assertStringContainsString('DON\'T STUCK SOLUTIONS', $html);
        $this->assertStringContainsString('654321', $html);
        $this->assertStringContainsString('This code is invalid and cannot be used to sign in.', $html);
        $this->assertStringContainsString('654321', $text);
        $this->assertStringContainsString('VISUAL TEST ONLY', $text);
    }

    public function test_client_can_register_and_receive_a_one_time_sign_in_code(): void
    {
        Mail::fake();

        $this->get(route('client.login'))
            ->assertOk()
            ->assertSee(route('client.register'));

        $this->get(route('client.register'))
            ->assertOk()
            ->assertSee('Create your account');

        $this->post(route('client.register.store'), [
            'full_name' => 'New Client',
            'national_id' => 'ID-REG-101',
            'phone' => '+254700000101',
            'email' => 'new-client@example.test',
        ])
            ->assertRedirect(route('client.login'))
            ->assertSessionHas('client_login_email', 'new-client@example.test')
            ->assertSessionHas('status');

        $borrower = Borrower::query()->where('national_id', 'ID-REG-101')->firstOrFail();
        $user = User::query()->where('email', 'new-client@example.test')->firstOrFail();

        $this->assertSame('New Client', $borrower->full_name);
        $this->assertSame('client', $user->role);
        $this->assertSame($user->id, $borrower->user_id);
        Mail::assertSent(ClientLoginCodeMail::class);
    }

    public function test_existing_borrower_can_register_when_their_identity_details_match(): void
    {
        Mail::fake();
        $borrower = Borrower::factory()->create([
            'full_name' => 'Existing Borrower',
            'national_id' => 'ID-EXISTING-102',
            'email' => 'existing@example.test',
        ]);

        $this->post(route('client.register.store'), [
            'full_name' => 'Existing Borrower',
            'national_id' => 'ID-EXISTING-102',
            'phone' => $borrower->phone,
            'email' => 'EXISTING@example.test',
        ])
            ->assertRedirect(route('client.login'))
            ->assertSessionHas('client_login_email', 'existing@example.test');

        $this->assertSame(1, Borrower::query()->where('national_id', 'ID-EXISTING-102')->count());
        $this->assertNotNull($borrower->fresh()->user_id);
        Mail::assertSent(ClientLoginCodeMail::class);
    }

    public function test_registration_rejects_a_national_id_already_registered_to_another_email(): void
    {
        Mail::fake();
        Borrower::factory()->create([
            'national_id' => 'ID-TAKEN-103',
            'email' => 'someone-else@example.test',
        ]);

        $this->from(route('client.register'))
            ->post(route('client.register.store'), [
                'full_name' => 'Impersonating Client',
                'national_id' => 'ID-TAKEN-103',
                'phone' => '+254700000103',
                'email' => 'unrelated@example.test',
            ])
            ->assertRedirect(route('client.register'))
            ->assertSessionHasErrors('national_id');

        $this->assertSame(1, Borrower::query()->where('national_id', 'ID-TAKEN-103')->count());
        $this->assertDatabaseMissing('users', ['email' => 'unrelated@example.test']);
        Mail::assertNothingOutgoing();
    }

    public function test_borrower_can_receive_a_one_time_code_and_view_only_their_applications(): void
    {
        Mail::fake();
        $borrower = Borrower::factory()->create(['email' => 'client@example.test']);
        $ownApplication = LoanApplication::factory()->for($borrower)->create();
        $otherBorrower = Borrower::factory()->create();
        $otherApplication = LoanApplication::factory()->for($otherBorrower)->create();

        $this->post(route('client.login.request-code'), ['email' => 'CLIENT@example.test'])
            ->assertRedirect(route('client.login'))
            ->assertSessionHas('client_login_email', 'client@example.test');

        $user = User::query()->where('email', 'client@example.test')->firstOrFail();
        $this->assertSame('client', $user->role);
        $this->assertSame($user->id, $borrower->fresh()->user_id);

        $code = null;
        Mail::assertSent(ClientLoginCodeMail::class, function (ClientLoginCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue(Hash::check($code, ClientLoginCode::query()->where('user_id', $user->id)->value('code_hash')));

        $this->post(route('client.login.verify'), ['code' => $code])->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->get(route('client.dashboard'))->assertOk()->assertSee($borrower->full_name);
        $this->get(route('client.loans.index'))->assertOk();
        $this->get(route('client.profile.show'))->assertOk()->assertSee($borrower->email);
        $this->assertNull(app(ClientLoginCodeService::class)->verifyCode($user->email, $code));

        $this->get(route('client.applications.index'))
            ->assertOk()
            ->assertSee($ownApplication->reference)
            ->assertDontSee($otherApplication->reference);
    }

    public function test_unknown_email_receives_the_same_confirmation_without_creating_an_account(): void
    {
        Mail::fake();

        $this->post(route('client.login.request-code'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('client.login'))
            ->assertSessionHas('status', 'If this email is linked to a client account, a sign-in code has been sent.');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('client_login_codes', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_expired_or_incorrect_codes_cannot_authenticate_a_client(): void
    {
        $user = $this->createClientAccount('borrower@example.test');
        ClientLoginCode::factory()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withSession(['client_login_email' => $user->email])
            ->post(route('client.login.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        ClientLoginCode::query()->where('user_id', $user->id)->update(['expires_at' => now()->addMinutes(10)]);
        $this->withSession(['client_login_email' => $user->email])
            ->post(route('client.login.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, ClientLoginCode::query()->where('user_id', $user->id)->value('attempts'));
        $this->assertGuest();
    }

    public function test_client_cannot_view_another_borrowers_application_or_document(): void
    {
        Storage::fake('local');
        $client = $this->createClientAccount('client@example.test');
        $ownApplication = LoanApplication::factory()->for($client->borrower)->create();
        $ownLoan = $this->createLoanAccount($ownApplication, $client->borrower, 'DSS-LOAN-OWN');
        RepaymentSchedule::create([
            'loan_account_id' => $ownLoan->id,
            'installment_number' => 1,
            'due_date' => today()->addDays(7),
            'principal_due' => '2500.00',
            'interest_due' => '250.00',
            'fees_due' => '0.00',
            'status' => 'pending',
        ]);
        $otherBorrower = Borrower::factory()->create();
        $otherApplication = LoanApplication::factory()->for($otherBorrower)->create();
        $otherLoan = $this->createLoanAccount($otherApplication, $otherBorrower, 'DSS-LOAN-OTHER');
        $path = 'loan-applications/'.$otherApplication->reference.'/private.pdf';
        Storage::disk('local')->put($path, 'private document');
        $document = ApplicationDocument::create([
            'loan_application_id' => $otherApplication->id,
            'document_type' => 'income',
            'original_name' => 'income.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 16,
        ]);

        $this->actingAs($client)
            ->get(route('client.applications.index'))
            ->assertOk()
            ->assertDontSee($otherApplication->reference);

        $this->get(route('client.loans.index'))
            ->assertOk()
            ->assertSee($ownLoan->reference)
            ->assertDontSee($otherLoan->reference);
        $this->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('KSh 2,750.00')
            ->assertSee('Due '.today()->addDays(7)->format('d M Y'));

        $this->get(route('client.documents.show', $document))->assertNotFound();
    }

    public function test_client_can_upload_a_private_document_to_their_own_application(): void
    {
        Storage::fake('local');
        $client = $this->createClientAccount('client@example.test');
        $application = LoanApplication::factory()->for($client->borrower)->create(['status' => 'additional_information_required']);

        $this->actingAs($client)
            ->get(route('client.applications.index'))
            ->assertOk()
            ->assertSee('Documents required');

        $this->post(route('client.applications.documents.store', $application), [
            'document_type' => 'income',
            'document' => UploadedFile::fake()->image('income.jpg'),
        ])
            ->assertRedirect(route('client.applications.index'))
            ->assertSessionHas('success');

        $document = ApplicationDocument::query()->firstOrFail();
        $this->assertSame($client->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->storage_path);
        $this->get(route('client.documents.show', $document))->assertOk();
    }

    public function test_staff_accounts_cannot_open_the_client_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($staff)
            ->get(route('client.dashboard'))
            ->assertNotFound();
    }

    public function test_sidebar_lists_only_the_signed_in_clients_open_loans(): void
    {
        $client = $this->createClientAccount('sidebar-client@example.test');
        $activeApplication = LoanApplication::factory()->for($client->borrower)->create();
        $activeLoan = $this->createLoanAccount($activeApplication, $client->borrower, 'DSS-LOAN-SIDEBAR-OPEN');
        $closedApplication = LoanApplication::factory()->for($client->borrower)->create();
        $closedLoan = $this->createLoanAccount($closedApplication, $client->borrower, 'DSS-LOAN-SIDEBAR-CLOSED');
        $closedLoan->update(['status' => 'fully_repaid']);
        $otherBorrower = Borrower::factory()->create();
        $otherApplication = LoanApplication::factory()->for($otherBorrower)->create();
        $otherLoan = $this->createLoanAccount($otherApplication, $otherBorrower, 'DSS-LOAN-SIDEBAR-OTHER');

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee(route('client.loans.show', $activeLoan))
            ->assertSee($activeLoan->reference)
            ->assertDontSee($closedLoan->reference)
            ->assertDontSee($otherLoan->reference);
    }

    public function test_client_loan_detail_route_is_scoped_to_the_signed_in_borrower(): void
    {
        $client = $this->createClientAccount('loan-detail-client@example.test');
        $ownApplication = LoanApplication::factory()->for($client->borrower)->create();
        $ownLoan = $this->createLoanAccount($ownApplication, $client->borrower, 'DSS-LOAN-DETAIL-OWN');
        $otherBorrower = Borrower::factory()->create();
        $otherApplication = LoanApplication::factory()->for($otherBorrower)->create();
        $otherLoan = $this->createLoanAccount($otherApplication, $otherBorrower, 'DSS-LOAN-DETAIL-OTHER');

        $this->actingAs($client)
            ->get(route('client.loans.show', $ownLoan))
            ->assertOk()
            ->assertSee($ownLoan->reference);

        $this->get(route('client.loans.show', $otherLoan))->assertNotFound();
    }

    public function test_client_can_edit_personal_profile_without_changing_login_identity_or_role(): void
    {
        $client = $this->createClientAccount('profile-client@example.test');
        $borrower = $client->borrower;

        $this->actingAs($client)
            ->put(route('client.profile.update'), [
                'full_name' => 'Updated Client Name',
                'phone' => '+254700555123',
                'residence' => 'Kilimani, Nairobi',
                'employer' => 'Example Ltd',
                'employer_location' => 'Westlands',
                'job_title' => 'Accountant',
                'net_salary' => '52345.50',
                'next_of_kin_name' => 'Jane Client',
                'next_of_kin_id' => 'KIN-2201',
                'next_of_kin_relation' => 'Sister',
                'next_of_kin_phone' => '+254700555124',
                'email' => 'changed@example.test',
                'national_id' => 'CHANGED-ID',
                'role' => 'administrator',
            ])
            ->assertRedirect(route('client.profile.show'))
            ->assertSessionHas('success', 'Your profile has been updated.');

        $this->assertDatabaseHas('borrowers', [
            'id' => $borrower->id,
            'full_name' => 'Updated Client Name',
            'phone' => '+254700555123',
            'residence' => 'Kilimani, Nairobi',
            'employer' => 'Example Ltd',
            'employer_location' => 'Westlands',
            'job_title' => 'Accountant',
            'next_of_kin_name' => 'Jane Client',
            'next_of_kin_id' => 'KIN-2201',
            'next_of_kin_relation' => 'Sister',
            'next_of_kin_phone' => '+254700555124',
            'email' => 'profile-client@example.test',
            'national_id' => $borrower->national_id,
        ]);
        $this->assertSame('Updated Client Name', $client->fresh()->name);
        $this->assertSame('client', $client->fresh()->role);

        $auditLog = AuditLog::query()->where('action', 'client.profile_updated')->firstOrFail();
        $this->assertSame('full_name,phone,residence,employer,employer_location,job_title,net_salary,next_of_kin_name,next_of_kin_id,next_of_kin_relation,next_of_kin_phone', $auditLog->metadata['changed_fields']);

        $this->get(route('client.profile.show'))
            ->assertOk()
            ->assertSee('Updated Client Name')
            ->assertSee('Kilimani, Nairobi')
            ->assertSee('Account identity');
    }

    public function test_client_profile_rejects_negative_monthly_salary_without_saving_changes(): void
    {
        $client = $this->createClientAccount('profile-client@example.test');
        $borrower = $client->borrower;

        $this->actingAs($client)
            ->put(route('client.profile.update'), [
                'full_name' => 'Changed Name',
                'phone' => '+254700555123',
                'net_salary' => '-1',
            ])
            ->assertSessionHasErrors('net_salary');

        $this->assertSame($borrower->full_name, $borrower->fresh()->full_name);
        $this->assertSame('client', $client->fresh()->role);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'client.profile_updated']);
    }

    public function test_staff_cannot_update_a_client_profile(): void
    {
        $staff = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($staff)
            ->put(route('client.profile.update'), [
                'full_name' => 'Changed Name',
                'phone' => '+254700555123',
            ])
            ->assertNotFound();
    }

    public function test_administrators_cannot_convert_a_client_account_into_a_staff_account(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $client = $this->createClientAccount('client@example.test');

        $this->actingAs($administrator)
            ->put(route('admin.users.update', $client), [
                'role' => 'administrator',
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertSame('client', $client->fresh()->role);
    }

    private function createClientAccount(string $email): User
    {
        $user = User::factory()->create(['role' => 'client', 'email' => $email]);
        $borrower = Borrower::factory()->create(['email' => $email]);
        $borrower->user()->associate($user)->save();

        return $user;
    }

    private function createLoanAccount(LoanApplication $application, Borrower $borrower, string $reference): LoanAccount
    {
        return LoanAccount::create([
            'reference' => $reference,
            'loan_application_id' => $application->id,
            'borrower_id' => $borrower->id,
            'principal_amount' => '25000.00',
            'disbursed_amount' => '25000.00',
            'interest_rate' => '10.0000',
            'interest_basis' => 'monthly',
            'term_months' => 6,
            'disbursed_at' => today(),
            'maturity_date' => today()->addMonths(6),
            'status' => 'active',
        ]);
    }
}
