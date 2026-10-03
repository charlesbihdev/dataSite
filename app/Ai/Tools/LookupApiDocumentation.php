<?php

namespace App\Ai\Tools;

use App\Services\Storefront\StoreAssistant\ApiDocs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Gives the storefront assistant the developer API reference on demand. The model calls this itself when
 * it judges that the user is a developer asking how to use/integrate with the API — no keyword guessing.
 */
class LookupApiDocumentation implements Tool
{
    /** Snake-case tool name exposed to the model (defaults to the class basename otherwise). */
    public function name(): string
    {
        return 'lookup_api_documentation';
    }

    public function description(): Stringable|string
    {
        return 'Fetch the developer API reference for this data service: authentication (X-API-Key), the '
            .'order/status/packages endpoints, request and response fields, the bundle sizes each network '
            .'accepts, error codes, and an example call. Call this whenever the user is a developer asking '
            .'how to programmatically place orders, check status, list packages, integrate, or use the API.';
    }

    public function handle(Request $request): Stringable|string
    {
        return ApiDocs::reference();
    }

    /**
     * No input is required — the reference is returned whole.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
