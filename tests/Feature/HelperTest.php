<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Odeal\Helpers\Helper;
use Omnipay\Odeal\Tests\TestCase;

class HelperTest extends TestCase
{
    public function test_normalize_phone_accepts_the_usual_shapes()
    {
        $this->assertSame('5551234567', Helper::normalizePhone('5551234567'));
        $this->assertSame('5551234567', Helper::normalizePhone('05551234567'));
        $this->assertSame('5551234567', Helper::normalizePhone('+90 555 123 45 67'));
        $this->assertSame('5551234567', Helper::normalizePhone('0090 (555) 123 45 67'));
        $this->assertSame('5551234567', Helper::normalizePhone('555 123 45 67'));
    }

    public function test_normalize_phone_rejects_invalid_values()
    {
        $this->assertNull(Helper::normalizePhone(null));
        $this->assertNull(Helper::normalizePhone(''));
        $this->assertNull(Helper::normalizePhone('12345'));
        $this->assertNull(Helper::normalizePhone('02121234567'));
        $this->assertNull(Helper::normalizePhone('+1 555 123 4567'));
    }
}
