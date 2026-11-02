<?php

namespace Tests\Feature;

use App\Models\ApplicationDocument;
use App\Models\Borrower;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientDocumentVaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_lists_and_downloads_only_the_signed_in_clients_documents(): void
    {
        Storage::fake('local');
        $client = $this->createClientAccount('vault-client@example.test');
        $ownApplication = LoanApplication::factory()->for($client->borrower)->create();
        $otherBorrower = Borrower::factory()->create();
        $otherApplication = LoanApplication::factory()->for($otherBorrower)->create();
        $ownDocument = $this->createDocument($ownApplication, 'my-income.pdf');
        $otherDocument = $this->createDocument($otherApplication, 'private-other-client.pdf');

        $this->actingAs($client)
            ->get(route('client.documents.index'))
            ->assertOk()
            ->assertSee('my-income.pdf')
            ->assertSee($ownApplication->reference)
            ->assertDontSee('private-other-client.pdf')
            ->assertDontSee($otherApplication->reference);

        $this->get(route('client.documents.show', $ownDocument))->assertOk();
        $this->get(route('client.documents.show', $otherDocument))->assertNotFound();
    }

    public function test_client_can_upload_to_the_vault_for_an_application_accepting_documents(): void
    {
        Storage::fake('local');
        $client = $this->createClientAccount('vault-client@example.test');
        $application = LoanApplication::factory()->for($client->borrower)->create([
            'status' => 'additional_information_required',
        ]);

        $this->actingAs($client)
            ->get(route('client.documents.index'))
            ->assertOk()
            ->assertSee('Upload a document')
            ->assertSee($application->reference);

        $this->post(route('client.documents.upload', $application), [
            'document_type' => 'income',
            'document' => UploadedFile::fake()->image('payslip.jpg'),
        ])
            ->assertRedirect(route('client.documents.index'))
            ->assertSessionHas('success', 'Your document was uploaded securely.');

        $document = ApplicationDocument::query()->firstOrFail();
        $this->assertSame($client->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->storage_path);
        $this->get(route('client.documents.show', $document))->assertOk();
    }

    public function test_staff_cannot_open_the_client_document_vault(): void
    {
        $staff = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($staff)
            ->get(route('client.documents.index'))
            ->assertNotFound();
    }

    private function createClientAccount(string $email): User
    {
        $user = User::factory()->create(['role' => 'client', 'email' => $email]);
        $borrower = Borrower::factory()->create(['email' => $email]);
        $borrower->user()->associate($user)->save();

        return $user;
    }

    private function createDocument(LoanApplication $application, string $fileName): ApplicationDocument
    {
        $path = 'loan-applications/'.$application->reference.'/'.$fileName;
        Storage::disk('local')->put($path, 'private document content');

        return ApplicationDocument::create([
            'loan_application_id' => $application->id,
            'document_type' => 'income',
            'original_name' => $fileName,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 24,
        ]);
    }
}
