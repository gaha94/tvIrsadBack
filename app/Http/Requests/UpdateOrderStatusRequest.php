<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('status') && is_string($this->input('status'))) {
            $data['status'] = trim($this->input('status'));
        }

        if ($this->has('payment_status') && is_string($this->input('payment_status'))) {
            $data['payment_status'] = trim($this->input('payment_status'));
        }

        if ($this->has('note') && is_string($this->input('note'))) {
            $data['note'] = trim($this->input('note'));
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,paid,confirmed,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:pending,paid,failed,refunded'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado no es válido.',
            'payment_status.in' => 'El estado de pago no es válido.',
            'note.max' => 'La nota no puede superar los 255 caracteres.',
        ];
    }
}