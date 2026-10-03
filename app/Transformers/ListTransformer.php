<?php

namespace App\Transformers;

abstract class ListTransformer
{
    /**
     * @return array{action: string, value: string, placeholder: string}
     */
    protected function search(string $action, string $value, string $placeholder): array
    {
        return [
            'action' => $action,
            'value' => $value,
            'placeholder' => $placeholder,
        ];
    }
}
