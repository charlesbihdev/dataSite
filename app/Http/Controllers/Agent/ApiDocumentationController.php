<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Pricing\PriceQuote;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the dedicated developer API documentation page for agents, detailing
 * order creation, status checks, live package pricing, and allowed capacity sizes.
 */
class ApiDocumentationController extends Controller
{
    public function index(Request $request, PriceQuote $quote): Response
    {
        $agent = $request->user();

        $catalog = array_map(function (array $net) use ($agent, $quote): array {
            $sampleSizes = array_slice($net['sizes'], 0, 8);
            $samples = [];
            foreach ($sampleSizes as $size) {
                $price = $quote->for($agent, $net['code'], $size);
                if ($price !== null) {
                    $samples[] = [
                        'capacity' => $size,
                        'price' => number_format($price['amount'], 2, '.', ''),
                        'pricePerGB' => number_format($price['pricePerGb'], 2, '.', ''),
                    ];
                }
            }

            return [
                'code' => $net['code'],
                'label' => $net['label'],
                'prefixes' => $net['prefixes'],
                'sizes' => $net['sizes'],
                'minGb' => $net['min_gb'],
                'maxGb' => $net['max_gb'],
                'samples' => $samples,
            ];
        }, GhanaMobileNetwork::meta());

        return Inertia::render('agent/api-documentation', [
            'baseUrl' => url('/api'),
            'catalog' => $catalog,
            'role' => 'agent',
        ]);
    }
}
