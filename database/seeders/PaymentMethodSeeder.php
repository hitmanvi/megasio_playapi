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
                'key' => 'credit_card_usd',
                'icon' => 'credit-card.svg',
                'name' => 'credit_card',
                'display_name' => 'Credit Card',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 10000.00,
                'min_amount' => 20.00,
                'enabled' => true,
                'sort_id' => 1,
                'notes' => 'Visa, MasterCard, Amex accepted',
            ],
            [
                'key' => 'debit_card_usd',
                'icon' => 'debit-card.svg',
                'name' => 'debit_card',
                'display_name' => 'Debit Card',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 2000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'sort_id' => 2,
                'notes' => 'Direct debit from checking or savings account',
            ],
            [
                'key' => 'paypal_usd',
                'icon' => 'paypal.svg',
                'name' => 'paypal',
                'display_name' => 'PayPal',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'sort_id' => 3,
                'notes' => 'PayPal digital wallet',
            ],
            [
                'key' => 'ach_usd_deposit',
                'icon' => 'ach.svg',
                'name' => 'ach',
                'display_name' => 'ACH Transfer',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'sort_id' => 4,
                'notes' => 'Automated Clearing House transfer',
            ],
            [
                'key' => 'bank_transfer_usd_deposit',
                'icon' => 'bank-transfer.svg',
                'name' => 'bank_transfer',
                'display_name' => 'Bank Transfer',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_DEPOSIT,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 10.00,
                'enabled' => true,
                'sort_id' => 5,
                'notes' => 'Wire transfer via banking institutions',
            ],
            
            // Withdraw methods
            [
                'key' => 'ach_usd_withdraw',
                'icon' => 'ach.svg',
                'name' => 'ach',
                'display_name' => 'ACH Transfer',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 50.00,
                'enabled' => true,
                'sort_id' => 1,
                'notes' => 'Direct deposit to your bank account',
            ],
            [
                'key' => 'bank_transfer_usd_withdraw',
                'icon' => 'bank-transfer.svg',
                'name' => 'bank_transfer',
                'display_name' => 'Bank Transfer',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 10000.00,
                'min_amount' => 100.00,
                'enabled' => true,
                'sort_id' => 2,
                'notes' => 'Wire transfer to your bank account',
            ],
            [
                'key' => 'crypto_usd_withdraw',
                'icon' => 'crypto.svg',
                'name' => 'crypto',
                'display_name' => 'Cryptocurrency',
                'currency' => 'USD',
                'currency_type' => 'BTC',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'is_fiat' => false,
                'amounts' => null,
                'max_amount' => 100000.00,
                'min_amount' => 50.00,
                'enabled' => true,
                'sort_id' => 3,
                'notes' => 'Bitcoin and other crypto currencies',
            ],
            [
                'key' => 'check_usd',
                'icon' => 'check.svg',
                'name' => 'check',
                'display_name' => 'Check',
                'currency' => 'USD',
                'currency_type' => 'USD',
                'type' => PaymentMethod::TYPE_WITHDRAW,
                'is_fiat' => true,
                'amounts' => null,
                'max_amount' => 5000.00,
                'min_amount' => 100.00,
                'enabled' => true,
                'sort_id' => 4,
                'notes' => 'Paper check via mail delivery',
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['key' => $method['key']],
                $method
            );
        }
    }
}
