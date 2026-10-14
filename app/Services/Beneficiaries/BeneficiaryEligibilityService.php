<?php

namespace App\Services\Beneficiaries;

use App\Services\Databundleshub\UpstreamClient;
use App\Support\GhanaMobileNetwork;

/**
 * Service to verify recipient phone number eligibility against upstream (DBH) beneficiary rules.
 * MTN requires approval on the DBH portal; Telecel and AirtelTigo are unenforced.
 */
class BeneficiaryEligibilityService
{
    public function __construct(
        private readonly UpstreamClient $upstream,
    ) {}

    /**
     * Check phone eligibility against the supplier approved list.
     *
     * @return array{
     *     ok: bool,
     *     msisdn: string,
     *     network: ?string,
     *     network_label: ?string,
     *     approved: bool,
     *     status: string,
     *     enforced: bool,
     *     message: ?string
     * }
     */
    public function check(string $rawPhone): array
    {
        $normalized = GhanaMobileNetwork::normalize($rawPhone);
        $phoneToCheck = $normalized !== '' ? $normalized : (preg_replace('/\D+/', '', $rawPhone) ?? '');

        if ($phoneToCheck === '' || !preg_match('/^0\d{9}$/', $phoneToCheck)) {
            return [
                'ok' => false,
                'msisdn' => $rawPhone,
                'network' => null,
                'network_label' => null,
                'approved' => false,
                'status' => 'invalid_number',
                'enforced' => true,
                'message' => 'Ghana phone numbers must start with 0 and be exactly 10 digits.',
            ];
        }

        $networkCode = GhanaMobileNetwork::detect($phoneToCheck);
        $networkLabel = $networkCode ? GhanaMobileNetwork::label($networkCode) : null;

        // Pass all numbers directly to DBH so the supplier makes the authoritative decision.
        $upstreamResult = $this->upstream->checkBeneficiary($phoneToCheck);

        return [
            'ok' => $upstreamResult['ok'],
            'msisdn' => $upstreamResult['msisdn'],
            'network' => $networkCode,
            'network_label' => $networkLabel,
            'approved' => $upstreamResult['approved'],
            'status' => $upstreamResult['status'],
            'enforced' => $upstreamResult['enforced'],
            'message' => $upstreamResult['message'],
        ];
    }
}

