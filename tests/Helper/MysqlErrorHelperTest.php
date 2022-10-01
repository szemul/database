<?php

declare(strict_types=1);

namespace Szemul\Database\Test\Helper;

use PHPUnit\Framework\TestCase;
use Szemul\Database\Exception\EntityDuplicateException;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Exception\ServerHasGoneAwayException;
use Szemul\Database\Helper\MysqlErrorHelper;

class MysqlErrorHelperTest extends TestCase
{
    public function testProcessExceptionWhenServerHasGoneAway_shouldTranslateToProperException(): void
    {
        $message   = 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away';
        $code      = 0;
        $exception = new QueryException($message, $code);

        $this->expectException(ServerHasGoneAwayException::class);
        $this->getSut()->processException($exception);
    }

    public function testProcessExceptionWhenUnknownGeneralErrorGiven_shouldThrowGiven(): void
    {
        $message   = 'Unknown';
        $code      = 0;
        $exception = new QueryException($message, $code);

        $this->expectExceptionObject($exception);
        $this->getSut()->processException($exception);
    }

    public function testProcessExceptionWhenDuplicateKeyGiven_shouldTranslateToProperException(): void
    {
        $message   = '';
        $code      = 1062;
        $exception = new QueryException($message, $code);

        try {
            $this->getSut()->processException($exception);

            $this->fail('Exception should have been thrown');
        } catch (EntityDuplicateException $duplicateException) {
            $this->assertSame(null, $duplicateException->getEntityName());
            $this->assertSame($code, $duplicateException->getCode());
            $this->assertSame($exception, $duplicateException->getPrevious());
        }
    }

    public function testProcessExceptionWhenDuplicateKeyAndEntityNameGiven_shouldSetEntityName(): void
    {
        $entityName = 'Customer';
        $exception  = new QueryException('', 1062);

        try {
            $this->getSut()->processException($exception, $entityName);

            $this->fail('Exception should have been thrown');
        } catch (EntityDuplicateException $duplicateException) {
            $this->assertSame($entityName, $duplicateException->getEntityName());
        }
    }

    public function testProcessExceptionWhenUnhandledCodeGiven_shouldThrowGiven(): void
    {
        $message   = '';
        $code      = 1000;
        $exception = new QueryException($message, $code);

        $this->expectExceptionObject($exception);
        $this->getSut()->processException($exception);
    }

    public function testProcessExceptionWhenUserDefinedWithDuplicateErrorGiven_shouldTranslateToProperException(): void
    {
        $message   = 'Error [1062] - Customer';
        $code      = 45000;
        $exception = new QueryException($message, $code);

        try {
            $this->getSut()->processException($exception);

            $this->fail('Exception should have been thrown');
        } catch (EntityDuplicateException $duplicateException) {
            $this->assertSame('Customer already exists', $duplicateException->getMessage());
            $this->assertSame('Customer', $duplicateException->getEntityName());
            $this->assertSame(1062, $duplicateException->getCode());
        }
    }

    public function testProcessExceptionWhenUnporcessableUserDefinedErrorGiven_shouldThrowGiven(): void
    {
        $message   = 'Unprocessable';
        $code      = 45000;
        $exception = new QueryException($message, $code);

        try {
            $this->getSut()->processException($exception);

            $this->fail('Exception should have been thrown');
        } catch (QueryException $queryException) {
            $this->assertSame($exception, $queryException);
        }
    }

    public function testProcessExceptionWhenUserDefinedErrorGivenWhatContainsUserDefinedError_shouldThrowGiven(): void
    {
        $message   = 'Error [45000] - Entity';
        $code      = 45000;
        $exception = new QueryException($message, $code);

        try {
            $this->getSut()->processException($exception);

            $this->fail('Exception should have been thrown');
        } catch (QueryException $queryException) {
            $this->assertSame($exception, $queryException);
        }
    }

    private function getSut(): MysqlErrorHelper
    {
        return new MysqlErrorHelper();
    }
}
