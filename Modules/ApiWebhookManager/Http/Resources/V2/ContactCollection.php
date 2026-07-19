<?php

namespace Modules\ApiWebhookManager\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ContactCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection->toArray();
    }

    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'meta' => [
                'pagination' => [
                    'total' => $default['meta']['total'],
                    'count' => $default['meta']['to'] - $default['meta']['from'] + 1,
                    'per_page' => $default['meta']['per_page'],
                    'current_page' => $default['meta']['current_page'],
                    'total_pages' => $default['meta']['last_page'],
                    'has_more' => $default['meta']['current_page'] < $default['meta']['last_page'],
                ],
            ],
            'links' => [
                'self' => $default['links']['first'].'?page='.$default['meta']['current_page'],
                'first' => $default['links']['first'],
                'last' => $default['links']['last'],
                'prev' => $default['links']['prev'],
                'next' => $default['links']['next'],
            ],
        ];
    }
}
