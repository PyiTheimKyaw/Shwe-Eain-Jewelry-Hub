<?php

namespace Database\Factories;

use App\Enums\PaymentChannel;
use App\Models\PaymentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProfile>
 */
class PaymentProfileFactory extends Factory
{
    protected $model = PaymentProfile::class;

    public function definition(): array
    {
        return [
            'name' => 'Test '.$this->faker->randomElement(['KPay', 'KBZ', 'CB']).' '.$this->faker->unique()->numerify('###'),
            'channel' => PaymentChannel::KPay,
            'institution_name' => null,
            'account_name' => $this->faker->name(),
            'account_number' => $this->faker->numerify('09#########'),
            'wallet_id' => null,
            'qr_image_path' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
