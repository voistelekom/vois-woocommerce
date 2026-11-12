<?php
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    public static function valid(): array
    {
        return [['05321234567'], ['5321234567'], ['905321234567'], ['+90 532 123 45 67'], ['0 (532) 123-45-67'], ['0090 532 123 4567']];
    }

    /** @dataProvider valid */
    #[\PHPUnit\Framework\Attributes\DataProvider('valid')]
    public function test_turkish_mobile_formats_normalize($in): void
    {
        $this->assertSame('905321234567', Vois_WC_Phone::normalize($in));
    }

    public function test_non_mobile_or_foreign_rejected(): void
    {
        foreach (['', '02121234567', '4441234', '+49 151 12345678', '053212345', '0532123456789', 'abc'] as $n) {
            $this->assertNull(Vois_WC_Phone::normalize($n), $n);
        }
    }

    public function test_parse_list_drops_invalid_and_duplicates(): void
    {
        $this->assertSame(['905321234567', '905441112233'], Vois_WC_Phone::parse_list("0532 123 45 67, 905321234567\n05441112233; 0212 111 22 33"));
    }
}
