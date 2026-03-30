<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer', []);
        $shipping = $this->input('shipping', []);

        if (isset($customer['email'])) {
            $customer['email'] = strtolower(trim($customer['email']));
        }

        if (isset($customer['first_name'])) {
            $customer['first_name'] = trim($customer['first_name']);
        }

        if (isset($customer['last_name'])) {
            $customer['last_name'] = trim($customer['last_name']);
        }

        if (isset($customer['phone'])) {
            $customer['phone'] = trim($customer['phone']);
        }

        if (isset($shipping['department'])) {
            $shipping['department'] = trim($shipping['department']);
        }

        if (isset($shipping['province'])) {
            $shipping['province'] = trim($shipping['province']);
        }

        if (isset($shipping['district'])) {
            $shipping['district'] = trim($shipping['district']);
        }

        if (isset($shipping['address_line1'])) {
            $shipping['address_line1'] = trim($shipping['address_line1']);
        }

        if (isset($shipping['address_line2'])) {
            $shipping['address_line2'] = trim($shipping['address_line2']);
        }

        if (isset($shipping['reference_text'])) {
            $shipping['reference_text'] = trim($shipping['reference_text']);
        }

        if ($this->has('notes') && is_string($this->input('notes'))) {
            $this->merge([
                'notes' => trim($this->input('notes')),
            ]);
        }

        $this->merge([
            'customer' => $customer,
            'shipping' => $shipping,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer.first_name' => ['required', 'string', 'max:120'],
            'customer.last_name' => ['nullable', 'string', 'max:120'],
            'customer.email' => ['required', 'email:rfc,dns', 'max:190'],
            'customer.phone' => ['required', 'string', 'max:30'],

            'shipping.department' => ['required', 'string', 'max:100'],
            'shipping.province' => ['required', 'string', 'max:100'],
            'shipping.district' => ['required', 'string', 'max:100'],
            'shipping.address_line1' => ['required', 'string', 'max:255'],
            'shipping.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping.reference_text' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],

            'payment_method' => ['required', 'string', 'in:cash_on_delivery,bank_transfer,card'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer.first_name.required' => 'El nombre del cliente es obligatorio.',
            'customer.email.required' => 'El correo del cliente es obligatorio.',
            'customer.email.email' => 'El correo del cliente no es válido.',
            'customer.phone.required' => 'El teléfono del cliente es obligatorio.',

            'shipping.department.required' => 'El departamento es obligatorio.',
            'shipping.province.required' => 'La provincia es obligatoria.',
            'shipping.district.required' => 'El distrito es obligatorio.',
            'shipping.address_line1.required' => 'La dirección principal es obligatoria.',

            'items.required' => 'Debes enviar al menos un producto.',
            'items.array' => 'Los items deben enviarse como lista.',
            'items.min' => 'Debes enviar al menos un producto.',
            'items.*.product_id.required' => 'Cada item debe tener un product_id.',
            'items.*.product_id.exists' => 'Uno de los productos no existe.',
            'items.*.quantity.required' => 'Cada item debe tener cantidad.',
            'items.*.quantity.min' => 'La cantidad mínima por producto es 1.',
            'items.*.quantity.max' => 'La cantidad máxima por producto es 999.',

            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago no es válido.',
        ];
    }
}