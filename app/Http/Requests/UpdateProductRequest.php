<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'sku', 'category_id', 'price', 'stock'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        $rules['sku'] = [
            'sometimes', 'required', 'string', 'max:100',
            Rule::unique('products', 'sku')->ignore($this->route('product')),
        ];

        return $rules;
    }
}
