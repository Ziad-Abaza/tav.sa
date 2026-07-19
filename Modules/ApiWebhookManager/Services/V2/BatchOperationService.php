<?php

namespace Modules\ApiWebhookManager\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BatchOperationService
{
    protected string $modelClass;

    protected int $tenantId;

    protected string $subdomain;

    public function __construct(string $modelClass, int $tenantId, string $subdomain)
    {
        $this->modelClass = $modelClass;
        $this->tenantId = $tenantId;
        $this->subdomain = $subdomain;
    }

    public static function for(string $modelClass, int $tenantId, string $subdomain): self
    {
        return new self($modelClass, $tenantId, $subdomain);
    }

    public function batchCreate(
        array $items,
        array $validationRules,
        bool $continueOnError = true,
        bool $skipDuplicates = false,
        ?string $uniqueField = null
    ): array {
        $created = [];
        $skipped = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($items as $index => $itemData) {
                $itemData['tenant_id'] = $this->tenantId;

                $validator = Validator::make($itemData, $validationRules);

                if ($validator->fails()) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $itemData,
                        'errors' => $validator->errors()->toArray(),
                    ];

                    if (! $continueOnError) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'summary' => [
                                'total' => count($items),
                                'created' => count($created),
                                'skipped' => count($skipped),
                                'failed' => count($errors),
                            ],
                            'created' => $created,
                            'skipped' => $skipped,
                            'errors' => $errors,
                        ];
                    }

                    continue;
                }

                if ($skipDuplicates && $uniqueField && isset($itemData[$uniqueField])) {
                    $exists = $this->modelClass::fromTenant($this->subdomain)
                        ->where($uniqueField, $itemData[$uniqueField])
                        ->exists();

                    if ($exists) {
                        $skipped[] = [
                            'index' => $index,
                            'reason' => "Duplicate {$uniqueField}",
                            'data' => $itemData,
                        ];

                        continue;
                    }
                }

                try {
                    $model = $this->modelClass::fromTenant($this->subdomain)->create($itemData);
                    $created[] = $model;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $itemData,
                        'errors' => ['database' => [$e->getMessage()]],
                    ];

                    if (! $continueOnError) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'summary' => [
                                'total' => count($items),
                                'created' => count($created),
                                'skipped' => count($skipped),
                                'failed' => count($errors),
                            ],
                            'created' => $created,
                            'skipped' => $skipped,
                            'errors' => $errors,
                        ];
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'summary' => [
                    'total' => count($items),
                    'created' => count($created),
                    'skipped' => count($skipped),
                    'failed' => count($errors),
                ],
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'summary' => [
                    'total' => count($items),
                    'created' => 0,
                    'skipped' => 0,
                    'failed' => count($items),
                ],
                'created' => [],
                'skipped' => [],
                'errors' => [
                    [
                        'index' => 0,
                        'errors' => ['database' => [$e->getMessage()]],
                    ],
                ],
            ];
        }
    }

    public function batchUpdate(array $updates): array
    {
        $updated = [];
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($updates as $index => $updateData) {
                if (! isset($updateData['id'])) {
                    $failed[] = [
                        'index' => $index,
                        'errors' => ['id' => ['The id field is required']],
                    ];

                    continue;
                }

                $model = $this->modelClass::fromTenant($this->subdomain)
                    ->where('tenant_id', $this->tenantId)
                    ->find($updateData['id']);

                if (! $model) {
                    $failed[] = [
                        'index' => $index,
                        'id' => $updateData['id'],
                        'errors' => ['id' => ['Record not found']],
                    ];

                    continue;
                }

                try {
                    $model->update(collect($updateData)->except(['id', 'tenant_id'])->toArray());
                    $updated[] = $model->fresh();
                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'id' => $updateData['id'],
                        'errors' => ['database' => [$e->getMessage()]],
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'summary' => [
                    'total' => count($updates),
                    'updated' => count($updated),
                    'failed' => count($failed),
                ],
                'updated' => $updated,
                'errors' => $failed,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'summary' => [
                    'total' => count($updates),
                    'updated' => 0,
                    'failed' => count($updates),
                ],
                'updated' => [],
                'errors' => [
                    [
                        'errors' => ['database' => [$e->getMessage()]],
                    ],
                ],
            ];
        }
    }

    public function batchDelete(array $ids): array
    {
        $deleted = 0;
        $failed = 0;

        DB::beginTransaction();

        try {
            $deleted = $this->modelClass::fromTenant($this->subdomain)
                ->where('tenant_id', $this->tenantId)
                ->whereIn('id', $ids)
                ->delete();

            $failed = count($ids) - $deleted;

            DB::commit();

            return [
                'success' => true,
                'summary' => [
                    'total' => count($ids),
                    'deleted' => $deleted,
                    'failed' => $failed,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'summary' => [
                    'total' => count($ids),
                    'deleted' => 0,
                    'failed' => count($ids),
                ],
                'errors' => [
                    ['database' => [$e->getMessage()]],
                ],
            ];
        }
    }
}
