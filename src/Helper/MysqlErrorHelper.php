<?php

declare(strict_types=1);

namespace Szemul\Database\Helper;

use Szemul\Database\Exception\EntityDuplicateException;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Exception\ServerHasGoneAwayException;
use Szemul\Database\Exception\UserDefinedException;

class MysqlErrorHelper
{
    private const CODE_GENERAL                          = 0;
    private const CODE_DUPLICATE_ENTRY                  = 1062;
    private const CODE_UNHANDLED_USER_DEFINED_EXCEPTION = 45000;

    /**
     * @throws EntityDuplicateException
     * @throws ServerHasGoneAwayException
     * @throws QueryException
     * @throws UserDefinedException
     */
    public function processException(QueryException|UserDefinedException $exception, ?string $entityName = null): void
    {
        switch ($exception->getCode()) {
            case self::CODE_GENERAL:
                $this->handleGeneralErrors($exception);
                // no break
            case self::CODE_DUPLICATE_ENTRY:
                throw new EntityDuplicateException($entityName, $exception);
            case self::CODE_UNHANDLED_USER_DEFINED_EXCEPTION:
                $this->handleUserDefinedException($exception);
                // no break
            default:
                throw $exception;
        }
    }

    /**
     * @throws UserDefinedException
     */
    private function handleUserDefinedException(QueryException $exception): void
    {
        $matches             = [];
        $regexCodeAndMessage = '#\[(?P<code>\d+)\]\s-\s(?P<message>.+)$#';
        $isMatched           = preg_match($regexCodeAndMessage, $exception->getMessage(), $matches);

        if (!$isMatched) {
            throw new UserDefinedException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $code    = (int)$matches['code'];
        $message = $matches['message'];

        if ($code === self::CODE_UNHANDLED_USER_DEFINED_EXCEPTION) {
            throw new UserDefinedException($exception->getMessage(), $exception->getCode(), $exception);
        } else {
            $this->processException(new UserDefinedException('', $code, $exception), $message);
        }
    }

    /**
     * @throws ServerHasGoneAwayException
     * @throws QueryException
     */
    private function handleGeneralErrors(QueryException $exception): void
    {
        if ($exception->getMessage() === 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away') {
            throw new ServerHasGoneAwayException();
        } else {
            throw $exception;
        }
    }
}
