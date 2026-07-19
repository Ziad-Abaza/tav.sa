<?php

namespace App\Http\Controllers\Admin\Api\Table;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableSettingsController extends Controller
{
    /**
     * Maps URL resource slugs to their BaseTable class.
     * Add new resources here — no other file needs to change.
     *
     * @var array<string, class-string>
     */
    private array $registry = [
    ];

    public function __invoke(Request $request, string $subdomain, string $resource): JsonResponse
    {
        if (! isset($this->registry[$resource])) {
            return response()->json(['message' => 'Unknown resource'], 404);
        }

        $table = new $this->registry[$resource];

        return response()->json($table->settings());
    }
}
