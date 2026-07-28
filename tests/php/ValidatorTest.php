<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testPostalCodesAreNormalized(): void
    {
        self::assertSame('M5V2T6', TradeFlow_Validator::normalize_postal_code('m5v 2t6'));
        self::assertSame('L6H', TradeFlow_Validator::normalize_postal_code('L6H!'));
    }

    public function testEligibilityUsesConfiguredPrefixes(): void
    {
        self::assertTrue(TradeFlow_Validator::postal_code_is_eligible('M5V 2T6', 'M4, M5, M6'));
        self::assertFalse(TradeFlow_Validator::postal_code_is_eligible('L6H 1A1', 'M4, M5, M6'));
        self::assertFalse(TradeFlow_Validator::postal_code_is_eligible('M', 'M4, M5, M6'));
    }

    public function testDedupeKeyNormalizesContactDetails(): void
    {
        $first = TradeFlow_Validator::dedupe_key([
            'email' => ' HomeOwner@Example.com ',
            'phone' => '(416) 555-0147',
            'service_id' => 10,
            'area_id' => 20,
        ], '2026-07-28');

        $second = TradeFlow_Validator::dedupe_key([
            'email' => 'homeowner@example.com',
            'phone' => '4165550147',
            'service_id' => 10,
            'area_id' => 20,
        ], '2026-07-28');

        self::assertSame($first, $second);
        self::assertNotSame(
            $first,
            TradeFlow_Validator::dedupe_key([
                'email' => 'homeowner@example.com',
                'phone' => '4165550147',
                'service_id' => 11,
                'area_id' => 20,
            ], '2026-07-28')
        );
    }

    public function testRequiredBookingFieldsAreValidated(): void
    {
        $errors = TradeFlow_Validator::validate([
            'customer_name' => 'A',
            'email' => 'not-an-email',
            'phone' => '123',
            'postal_code' => '',
            'details' => 'Too short',
            'service_id' => 0,
            'area_id' => 0,
            'slot_start' => '',
            'slot_end' => '',
        ]);

        self::assertArrayHasKey('customer_name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('phone', $errors);
        self::assertArrayHasKey('postal_code', $errors);
        self::assertArrayHasKey('details', $errors);
        self::assertArrayHasKey('service', $errors);
        self::assertArrayHasKey('slot', $errors);
    }
}

