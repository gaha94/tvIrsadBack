<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminBrandRequest extends FormRequest
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
        $id = (int) $this->route('id');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('brands', 'name')->ignore($id)],
            'slug' => ['required', 'string', 'max:150', Rule::unique('brands', 'slug')->ignore($id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}