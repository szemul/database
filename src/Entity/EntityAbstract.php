<?php
declare(strict_types=1);

namespace Szemul\Database\Entity;

use ArrayAccess;
use ArrayIterator;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use IteratorAggregate;
use Szemul\NotSetValue\NotSetValue;

/**
 * @implements IteratorAggregate<string,mixed>
 * @implements ArrayAccess<string,mixed>
 */
abstract class EntityAbstract implements IteratorAggregate, ArrayAccess
{
    /** @param array<string,mixed> $entityData */
    public function __construct(array $entityData = [])
    {
        $this->populateFromArray($entityData);

        if (!empty($this->id) && is_numeric($this->id)) {
            $this->id = (int)$this->id;
        }
    }

    /** @param array<string,mixed> $entityData */
    protected function populateFromArray(array $entityData): void
    {
        if (empty($entityData)) {
            return;
        }

        foreach (get_object_vars($this) as $attribute => $value) {
            $this->$attribute = $this->getFromArray($entityData, $attribute);
        }
    }

    /** @param array<string,mixed> $array */
    protected function getFromArray(array $array, string $key): mixed
    {
        if (!array_key_exists($key, $array)) {
            throw new InvalidArgumentException('Key "' . $key . '" does not exist in given array!');
        }

        return $array[$key];
    }

    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->$offset);
    }

    /**
     * @return ArrayIterator<string,string>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    public function replaceNotSetValues(mixed $newValue = null): static
    {
        $newEntity = clone $this;

        foreach (get_object_vars($newEntity) as $attribute => $value) {
            if ($value instanceof NotSetValue) {
                $newEntity->$attribute = $newValue;
            }
        }

        return $newEntity;
    }

    protected function getCarbonFromDateTimeString(string $dateString): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $dateString, new \DateTimeZone('UTC'));
    }

    protected function getCarbonFromNullableDateTimeString(?string $dateString): ?CarbonImmutable
    {
        return $dateString ? $this->getCarbonFromDateTimeString($dateString) : null;
    }

    protected function getCarbonFromDateString(string $dateString): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $dateString, new \DateTimeZone('UTC'));
    }

    protected function getCarbonFromNullableDateString(?string $dateString): ?CarbonImmutable
    {
        return $dateString ? $this->getCarbonFromDateString($dateString) : null;
    }

    protected function getDateTimeFromDateTimeString(string $dateString): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateString, new \DateTimeZone('UTC'));
    }

    protected function getDateTimeFromNullableDateTimeString(?string $dateString): ?DateTimeImmutable
    {
        return $dateString ? $this->getDateTimeFromDateTimeString($dateString) : null;
    }

    protected function getDateTimeFromDateString(string $dateString): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d', $dateString, new \DateTimeZone('UTC'));
    }

    protected function getDateTimeFromNullableDateString(?string $dateString): ?DateTimeImmutable
    {
        return $dateString ? $this->getDateTimeFromDateString($dateString) : null;
    }

    protected function getDateTimeStringFromDateTime(DateTime | DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    protected function getDateTimeStringFromNullableDateTime(null | DateTime | DateTimeImmutable $dateTime): ?string
    {
        return null === $dateTime ? null : $this->getDateTimeStringFromDateTime($dateTime);
    }

    protected function getDateStringFromDateTime(DateTime | DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
    }

    protected function getDateStringFromNullableDateTime(null | DateTime | DateTimeImmutable $carbon): ?string
    {
        return null === $carbon ? null : $this->getDateStringFromDateTime($carbon);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $result = [];

        foreach (get_object_vars($this) as $attribute => $value) {
            $result[$attribute] = $value;
        }

        return $result;
    }
}
