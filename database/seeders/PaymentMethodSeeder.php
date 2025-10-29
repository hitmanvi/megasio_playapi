<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            // Deposit methods
            [
                'icon' => 'bank-transfer.svg',
                'name' => 'bank_transfer',
                'display_name' => 'Bank Transfer',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'notes' => 'Wire transfer via banking institutions',
            ],
            [
                'icon' => 'credit-card.svg',
                'name' => 'credit_card',
                'display_name' => 'Credit Card',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'amounts' => null,
                'max_amount' => 10000.00,
                'min_amount' => 20.00,
                'enabled' => true,
                'notes' => 'Visa, MasterCard, Amex accepted',
            ],
            [
                'icon' => 'ach.svg',
                'name' => 'ach',
                'display_name' => 'ACH Transfer',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'notes' => 'Automated Clearing House transfer',
            ],
            [
                'icon' => 'paypal.svg',
                'name' => 'paypal',
                'display_name' => 'PayPal',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'notes' => 'PayPal digital wallet',
            ],
            [
                'icon' => 'debit-card.svg',
                'name' => 'debit_card',
                'display_name' => 'Debit Card',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'amounts' => null,
                'max_amount' => 2000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'notes' => 'Direct debit from checking or savings account',
            ],
            
            // Withdraw methods
            [
                'icon' => 'bank-transfer.svg',
                'name' => 'bank_transfer',
                'display_name' => 'Bank Transfer',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'amounts' => null,
                'max_amount' => 10000.00,
                'min_amount' => 100.00,
                'enabled' => true,
                'notes' => 'Wire transfer to your bank account',
            ],
            [
                'icon' => 'ach.svg',
                'name' => 'ach',
                'display_name' => 'ACH Transfer',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 50.00,
                'enabled' => true,
                'notes' => 'Direct deposit to your bank account',
            ],
            [
                'icon' => 'check.svg',
                'name' => 'check',
                'display_name' => 'Check',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 100.00,
                'enabled' => true,
                'notes' => 'Paper check via mail delivery',
            ],
            [
                'icon' => 'crypto.svg',
                'name' => 'crypto',
                'display_name' => 'Cryptocurrency',
                'currency' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'amounts' => null,
                'max_amount' => 100000.00,
                'min_amount' => 50.00,
                'enabled' => true,
                'notes' => 'Bitcoin and other crypto currencies',
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::create($method);
        }
    }
}
