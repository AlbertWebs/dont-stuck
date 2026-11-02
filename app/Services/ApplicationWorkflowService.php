<?php

namespace App\Services;

use App\Models\ApplicationStatusHistory;
use App\Models\LoanApplication;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationWorkflowService
{
    /** @var array<string, array<string, array<int, string>>> */
    private const TRANSITIONS = [
        'submitted' => ['under_review' => ['administrator', 'loan_officer']],
        'under_review' => [
            'verification_assessment' => ['administrator', 'loan_officer'],
            'additional_information_required' => ['administrator', 'loan_officer'],
            'on_hold' => ['administrator', 'loan_officer', 'management'],
            'cancelled' => ['administrator'],
        ],
        'additional_information_required' => ['under_review' => ['administrator', 'loan_officer']],
        'verification_assessment' => [
            'pending_approval' => ['administrator', 'loan_officer'],
            'additional_information_required' => ['administrator', 'loan_officer'],
            'on_hold' => ['administrator', 'loan_officer', 'management'],
        ],
        'on_hold' => ['under_review' => ['administrator', 'loan_officer', 'management']],
        'pending_approval' => [
            'approved' => ['administrator', 'management'],
            'rejected' => ['administrator', 'management'],
        ],
        'approved' => ['finance_processing' => ['administrator', 'finance_officer']],
        'finance_processing' => ['pending_disbursement' => ['administrator', 'finance_officer']],
        'pending_disbursement' => ['disbursed' => ['administrator', 'finance_officer']],
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function transition(LoanApplication $application, User $actor, string $toStatus, ?string $remarks, ?string $approvedAmount = null, ?int $approvedMonths = null): LoanApplication
    {
        return DB::transaction(function () use ($application, $actor, $toStatus, $remarks, $approvedAmount, $approvedMonths): LoanApplication {
            $locked = LoanApplication::query()->lockForUpdate()->findOrFail($application->id);
            $allowedRoles = self::TRANSITIONS[$locked->status][$toStatus] ?? [];

            if (! in_array($actor->role, $allowedRoles, true)) {
                throw ValidationException::withMessages(['status' => 'This status change is not available for your role at this stage.']);
            }

            if ($actor->role === 'loan_officer' && $locked->assigned_user_id !== $actor->id) {
                throw ValidationException::withMessages(['status' => 'Only the assigned loan officer can move this application.']);
            }

            if ($toStatus === 'disbursed') {
                throw ValidationException::withMessages(['status' => 'Record a confirmed disbursement with the finance workflow after product terms and the repayment schedule are configured.']);
            }

            if ($toStatus === 'approved') {
                if ($approvedAmount === null || $approvedMonths === null) {
                    throw ValidationException::withMessages(['approved_amount' => 'Record the approved amount and repayment period before approving this application.']);
                }

                if (Money::toCents($approvedAmount) > Money::toCents((string) $locked->requested_amount)) {
                    throw ValidationException::withMessages(['approved_amount' => 'The approved amount cannot exceed the requested amount.']);
                }

                $product = $locked->product()->firstOrFail();

                if ($product->minimum_amount !== null && Money::toCents($approvedAmount) < Money::toCents((string) $product->minimum_amount)) {
                    throw ValidationException::withMessages(['approved_amount' => 'The approved amount is below the product minimum.']);
                }

                if ($product->maximum_amount !== null && Money::toCents($approvedAmount) > Money::toCents((string) $product->maximum_amount)) {
                    throw ValidationException::withMessages(['approved_amount' => 'The approved amount is above the product maximum.']);
                }

                if ($product->repayment_months !== null && $approvedMonths !== $product->repayment_months) {
                    throw ValidationException::withMessages(['approved_months' => 'The approved term must match the configured product term.']);
                }
            }

            $fromStatus = $locked->status;
            $locked->update(['status' => $toStatus]);

            if (in_array($toStatus, ['approved', 'rejected'], true)) {
                $locked->update(['decision_notes' => $remarks]);
            }

            if ($toStatus === 'approved') {
                $locked->update(['approved_amount' => $approvedAmount, 'approved_months' => $approvedMonths]);
            }

            ApplicationStatusHistory::create([
                'loan_application_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => $actor->id,
                'remarks' => $remarks,
            ]);

            $this->auditLogger->record($actor, 'application.status_changed', $locked, [
                'from' => $fromStatus,
                'to' => $toStatus,
            ]);

            return $locked->fresh();
        });
    }
}
