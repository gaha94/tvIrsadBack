<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $data['name'] = trim($this->input('name'));
        }

        if ($this->has('slug') && is_string($this->input('slug'))) {
            $data['slug'] = trim($this->input('slug'));
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:brands,name'],
            'slug' => ['required', 'string', 'max:150', 'unique:brands,slug'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}