<?php

declare(strict_types=1);

namespace Xgc\Exception;

class MissingFieldRequiredToCreateClassException extends BaseException
{
    public function __construct(string $resource, string $field)
    {
        parent::__construct(
            message: "Field '{$field}' is mandatory when creating a '{$resource}'.",
            status: 400,
            extras: [
                'field' => $field,
                'type' => 'MISSING_ARGUMENT',
                'resource' => $resource,
            ],
        );
    }
}
