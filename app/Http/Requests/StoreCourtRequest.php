<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageFacility() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            // Optional online deposit; the balance is paid on-site. Must be less
            // than the price (0 or empty means full payment online).
            'deposit' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'opens_at' => ['required', 'date_format:H:i'],
            // A closing time at or before the opening time means the court runs
            // past midnight (e.g. 18:00 open, 04:00 close the next day).
            'closes_at' => ['required', 'date_format:H:i'],
            'slot_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'booking_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Court attributes with the display price converted to centavos.
     *
     * @return array<string, mixed>
     */
    public function courtAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'price_per_slot' => (int) round($validated['price'] * 100),
            'deposit_per_slot' => ! empty($validated['deposit'])
                ? (int) round($validated['deposit'] * 100)
                : null,
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'slot_minutes' => $validated['slot_minutes'],
            'buffer_minutes' => $validated['buffer_minutes'],
            'booking_window_days' => $validated['booking_window_days'],
            'capacity' => $validated['capacity'] ?? 4,
        ];
    }
}
