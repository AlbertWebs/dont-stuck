<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_application_creates_borrower_guarantors_documents_and_audit_record(): void
    {
        Storage::fake('local');
        $product = LoanProduct::factory()->create(['name' => 'Employed loan']);

        $response = $this->post(route('apply.store'), $this->applicationData($product));

        $application = LoanApplication::query()->firstOrFail();
        $response->assertRedirect(route('apply.received', ['reference' => $application->reference]));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loan_applications', ['reference' => $application->reference, 'status' => 'submitted']);
        $this->assertDatabaseCount('guarantors', 2);
        $this->assertDatabaseHas('application_documents', ['loan_application_id' => $application->id, 'document_type' => 'national_id']);
        $this->assertDatabaseHas('application_status_histories', ['loan_application_id' => $application->id, 'to_status' => 'submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.submitted']);
        Storage::disk('local')->assertExists($application->documents()->firstOrFail()->storage_path);

        $this->get(route('apply.received', ['reference' => $application->reference]))
            ->assertOk()
            ->assertSee($application->reference);
    }

    public function test_missing_required_application_fields_are_rejected_without_creating_records(): void
    {
        $response = $this->from(route('apply'))->post(route('apply.store'), []);

        $response->assertRedirect(route('apply'));
        $response->assertSessionHasErrors(['loan_product_id', 'full_name', 'national_id', 'guarantors', 'documents.national_id', 'privacy_consent']);
        $this->assertDatabaseCount('loan_applications', 0);
        $this->assertDatabaseCount('borrowers', 0);
    }

    public function test_inactive_product_cannot_receive_public_applications(): void
    {
        $product = LoanProduct::factory()->create(['is_active' => false]);
        $data = $this->applicationData($product);

        $response = $this->from(route('apply'))->post(route('apply.store'), $data);

        $response->assertRedirect(route('apply'));
        $response->assertSessionHasErrors('loan_product_id');
        $this->assertDatabaseCount('loan_applications', 0);
    }

    /** @return array<string, mixed> */
    private function applicationData(LoanProduct $product): array
    {
        return [
            'loan_product_id' => $product->id,
            'full_name' => 'Amina Wanjiku',
            'national_id' => '12345678',
            'phone' => '+254712345678',
            'email' => 'amina@example.test',
            'residence' => 'Westlands, Nairobi',
            'employer' => 'Example Company',
            'employer_location' => 'Nairobi',
            'job_title' => 'Accountant',
            'net_salary' => '60000.00',
            'next_of_kin_name' => 'Peter Wanjiku',
            'next_of_kin_id' => '87654321',
            'next_of_kin_relation' => 'Brother',
            'next_of_kin_phone' => '+254798765432',
            'requested_amount' => '25000.00',
            'requested_months' => '6',
            'purpose' => 'Pay for professional training',
            'guarantors' => [
                ['full_name' => 'Brian Otieno', 'national_id' => '11112222', 'phone' => '+254711111111', 'relationship' => 'Colleague', 'is_colleague' => '1'],
                ['full_name' => 'Grace Njeri', 'national_id' => '22223333', 'phone' => '+254722222222', 'relationship' => 'Friend'],
            ],
            'documents' => ['national_id' => UploadedFile::fake()->image('national-id.jpg')],
            'privacy_consent' => '1',
        ];
    }
}
