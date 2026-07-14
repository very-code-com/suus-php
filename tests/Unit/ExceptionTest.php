<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VeryCodeCom\Suus\Exception\SuusApiException;
use VeryCodeCom\Suus\Exception\SuusDuplicateReferenceException;
use VeryCodeCom\Suus\Exception\SuusException;
use VeryCodeCom\Suus\Exception\SuusValidationException;

final class ExceptionTest extends TestCase
{
    // -- Raw response + debug report ----------------------------------

    public function testRawResponseIsNullByDefault(): void
    {
        $ex = new SuusApiException('msg', 'ERR001');
        $this->assertNull($ex->getRawResponse());
    }

    public function testWithRawResponseIsFluentAndStoresBody(): void
    {
        $ex     = new SuusApiException('msg', 'BTN0001');
        $return = $ex->withRawResponse('<xml>raw</xml>');

        $this->assertSame($ex, $return);
        $this->assertSame('<xml>raw</xml>', $ex->getRawResponse());
    }

    public function testGetDebugReportContainsMessageRawResponseAndTrace(): void
    {
        $ex = (new SuusApiException('SUUS addOrder failed [BTN0001]:', 'BTN0001'))
            ->withRawResponse('<result><returnCode>BTN0001</returnCode></result>');

        $report = $ex->getDebugReport();

        $this->assertStringContainsString('SuusApiException', $report);
        $this->assertStringContainsString('BTN0001', $report);
        $this->assertStringContainsString('--- Raw SUUS response ---', $report);
        $this->assertStringContainsString('<returnCode>BTN0001</returnCode>', $report);
        $this->assertStringContainsString('--- Stack trace ---', $report);
    }

    public function testGetDebugReportOmitsRawSectionWhenNotcaptured(): void
    {
        $report = (new SuusValidationException(['bad date']))->getDebugReport();

        $this->assertStringContainsString('SuusValidationException', $report);
        $this->assertStringNotContainsString('--- Raw SUUS response ---', $report);
        $this->assertStringContainsString('--- Stack trace ---', $report);
    }

    public function testGetDebugReportIncludesPreviousException(): void
    {
        $previous = new \RuntimeException('root cause');
        $ex       = new SuusException('wrapper', 0, $previous);

        $report = $ex->getDebugReport();

        $this->assertStringContainsString('--- Caused by ---', $report);
        $this->assertStringContainsString('root cause', $report);
    }

    // -- SuusApiException ---------------------------------------------

    public function testGetFormattedErrorsReturnsConcatenatedStrings(): void
    {
        $ex = new SuusApiException('msg', 'ERR001', [
            ['code' => 'PRJ00310', 'message' => 'Duplicate reference'],
            ['code' => 'DRG00002', 'message' => 'Invalid date'],
        ]);

        $this->assertSame([
            'PRJ00310: Duplicate reference',
            'DRG00002: Invalid date',
        ], $ex->getFormattedErrors());
    }

    public function testGetFormattedErrorsReturnsEmptyArrayWhenNoErrors(): void
    {
        $ex = new SuusApiException('msg', 'ERR001');
        $this->assertSame([], $ex->getFormattedErrors());
    }

    public function testHasCodeReturnsTrueWhenCodePresent(): void
    {
        $ex = new SuusApiException('msg', 'ERR001', [
            ['code' => 'PRJ00310', 'message' => 'Duplicate reference'],
        ]);

        $this->assertTrue($ex->hasCode('PRJ00310'));
    }

    public function testHasCodeReturnsFalseWhenCodeAbsent(): void
    {
        $ex = new SuusApiException('msg', 'ERR001', [
            ['code' => 'PRJ00310', 'message' => 'Duplicate reference'],
        ]);

        $this->assertFalse($ex->hasCode('DRG00001'));
    }

    public function testHasCodeReturnsFalseForEmptyErrorList(): void
    {
        $ex = new SuusApiException('msg', 'ERR001');
        $this->assertFalse($ex->hasCode('PRJ00310'));
    }

    public function testReturnCodeIsAccessible(): void
    {
        $ex = new SuusApiException('msg', 'CWS9999', [['code' => 'X', 'message' => 'Y']]);
        $this->assertSame('CWS9999', $ex->returnCode);
    }

    // -- SuusDuplicateReferenceException ------------------------------

    public function testDuplicateReferenceExceptionMessage(): void
    {
        $ex = new SuusDuplicateReferenceException('ORDER-2025-001');
        $this->assertStringContainsString('ORDER-2025-001', $ex->getMessage());
    }

    // -- SuusValidationException ---------------------------------------

    public function testValidationExceptionExposesErrors(): void
    {
        $errors = ['loadingDate too early', 'incoterms required'];
        $ex     = new SuusValidationException($errors);

        $this->assertSame($errors, $ex->getErrors());
        $this->assertStringContainsString('loadingDate too early', $ex->getMessage());
    }
}
