<?php

namespace App\Services\Beneficiaries;

use App\Models\UnapprovedBeneficiary;
use App\Services\Databundleshub\UpstreamClient;
use App\Support\GhanaMobileNetwork;

class UnapprovedBeneficiaryService
{
    /**
     * Record or update a beneficiary check result.
     *
     * @param  array{approved?: bool, status?: string, message?: ?string}  $upstreamResult
     */
    public function recordCheck(
        string $rawPhone,
        ?string $networkCode,
        array $upstreamResult,
        ?int $agentId = null,
        ?string $sourceLabel = null
    ): ?UnapprovedBeneficiary {
        $phone = GhanaMobileNetwork::normalize($rawPhone);
        if ($phone === '') {
            $phone = preg_replace('/\D+/', '', $rawPhone) ?? '';
        }

        if ($phone === '') {
            return null;
        }

        $network = $networkCode ?? GhanaMobileNetwork::detect($phone);
        $status = strtolower(trim((string) ($upstreamResult['status'] ?? '')));
        $isApproved = (bool) ($upstreamResult['approved'] ?? ($status === 'approved' || $status === 'not_enforced'));

        // If approved by supplier:
        if ($isApproved) {
            $existing = UnapprovedBeneficiary::where('phone', $phone)->first();
            if ($existing !== null) {
                $existing->status = UnapprovedBeneficiary::STATUS_APPROVED;
                $existing->approved_at = now();
                $existing->last_checked_at = now();
                $existing->check_count++;
                $existing->save();

                return $existing;
            }

            return null;
        }

        // Ignore client format errors or connection errors from polluting the unapproved list.
        if (in_array($status, ['invalid_number', 'connection_error', 'not_configured'], true)) {
            return null;
        }

        // Upsert unapproved record
        $existing = UnapprovedBeneficiary::where('phone', $phone)->first();

        if ($existing !== null) {
            $existing->check_count++;
            $existing->last_checked_at = now();
            if (! empty($upstreamResult['message'])) {
                $existing->failure_message = (string) $upstreamResult['message'];
            }
            if ($existing->agent_id === null && $agentId !== null) {
                $existing->agent_id = $agentId;
            }
            if ($existing->source_label === null && $sourceLabel !== null) {
                $existing->source_label = $sourceLabel;
            }
            if ($existing->network === null && $network !== null) {
                $existing->network = $network;
            }
            $existing->save();

            return $existing;
        }

        return UnapprovedBeneficiary::create([
            'phone' => $phone,
            'network' => $network,
            'agent_id' => $agentId,
            'source_label' => $sourceLabel,
            'status' => UnapprovedBeneficiary::STATUS_PENDING,
            'failure_message' => $upstreamResult['message'] ?? null,
            'check_count' => 1,
            'last_checked_at' => now(),
        ]);
    }

    /**
     * Record an order that failed dispatch upstream due to unapproved beneficiary.
     */
    public function recordOrderFailure(
        string $rawPhone,
        ?string $networkCode,
        ?int $agentId = null,
        ?string $sourceLabel = null,
        ?string $failureReason = null
    ): ?UnapprovedBeneficiary {
        return $this->recordCheck(
            $rawPhone,
            $networkCode,
            [
                'approved' => false,
                'status' => 'not_approved',
                'message' => $failureReason ?? 'It needs approval before ordering.',
            ],
            $agentId,
            $sourceLabel
        );
    }

    /**
     * Re-check an unapproved beneficiary against Databundleshub live API.
     */
    public function recheck(UnapprovedBeneficiary $beneficiary, UpstreamClient $upstream): bool
    {
        $res = $upstream->checkBeneficiary($beneficiary->phone);
        $status = strtolower(trim((string) ($res['status'] ?? '')));
        $isApproved = (bool) ($res['approved'] ?? ($status === 'approved' || $status === 'not_enforced'));

        $beneficiary->last_checked_at = now();

        if ($isApproved) {
            $beneficiary->status = UnapprovedBeneficiary::STATUS_APPROVED;
            $beneficiary->approved_at = now();
            $beneficiary->failure_message = null;
            $beneficiary->save();

            return true;
        }

        if (! empty($res['message'])) {
            $beneficiary->failure_message = (string) $res['message'];
        }
        $beneficiary->save();

        return false;
    }
}

