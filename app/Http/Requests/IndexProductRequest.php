<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')],
            'min_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99',
                Rule::when($this->has('min_price'), 'gte:min_price')],
            'min_stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'max_stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295',
                Rule::when($this->has('min_stock'), 'gte:min_stock')],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
