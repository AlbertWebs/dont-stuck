<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_loan_officer_can_move_a_submitted_application_into_review(): void
    {
        $officer = User::factory()->create(['role' => 'loan_officer']);
        $application = LoanApplication::factory()->create(['assigned_user_id' => $officer->id]);

        $response = $this->actingAs($officer)->post(route('admin.applications.transition', $application), [
            'status' => 'under_review',
            'remarks' => 'Initial review started.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'under_review']);
        $this->assertDatabaseHas('application_status_histories', [
            'loan_application_id' => $application->id,
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'actor_id' => $officer->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.status_changed', 'actor_id' => $officer->id]);
    }

    public function test_loan_officer_cannot_move_an_application_assigned_to_another_officer(): void
    {
        $officer = User::factory()->create(['role' => 'loan_officer']);
        $assignedOfficer = User::factory()->create(['role' => 'loan_officer']);
        $application = LoanApplication::factory()->create(['assigned_user_id' => $assignedOfficer->id]);

        $response = $this->actingAs($officer)->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.transition', $application), ['status' => 'under_review']);

        $response->assertForbidden();
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'submitted']);
        $this->assertDatabaseCount('application_status_histories', 0);
    }

    public function test_loan_officer_cannot_approve_an_application(): void
    {
        $officer = User::factory()->create(['role' => 'loan_officer']);
        $application = LoanApplication::factory()->create(['status' => 'pending_approval', 'assigned_user_id' => $officer->id]);

        $response = $this->actingAs($officer)->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.transition', $application), ['status' => 'approved']);

        $response->assertSessionHasErrors(['approved_amount', 'approved_months']);
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'pending_approval']);
    }

    public function test_management_can_approve_an_application_waiting_for_decision(): void
    {
        $manager = User::factory()->create(['role' => 'management']);
        $application = LoanApplication::factory()->create(['status' => 'pending_approval']);

        $response = $this->actingAs($manager)->post(route('admin.applications.transition', $application), [
            'status' => 'approved',
            'approved_amount' => '20000.00',
            'approved_months' => 6,
            'remarks' => 'Assessment reviewed and approved.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'approved', 'approved_amount' => '20000.00', 'approved_months' => 6, 'decision_notes' => 'Assessment reviewed and approved.']);
    }

    public function test_confirmed_disbursement_cannot_be_marked_without_the_finance_record(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $application = LoanApplication::factory()->create(['status' => 'pending_disbursement']);

        $response = $this->actingAs($administrator)->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.transition', $application), ['status' => 'disbursed']);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'pending_disbursement']);
        $this->assertDatabaseCount('disbursements', 0);
    }

    public function test_staff_without_a_role_cannot_open_the_admin_workspace(): void
    {
        $user = User::factory()->create(['role' => 'guest']);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }
}
