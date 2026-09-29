<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'supplier_ids' => ['sometimes', 'array', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'distinct', Rule::exists('suppliers', 'id')],
        ];
    }
}
