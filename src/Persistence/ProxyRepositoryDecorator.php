<?php

/*
 * This file is part of the zenstruck/foundry package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Foundry\Persistence;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata as ODMClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\MappingException as ORMMappingException;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Proxy as ProxyObject;

/**
 * @mixin EntityRepository<TProxiedObject>
 * @extends RepositoryDecorator<TProxiedObject>
 * @template TProxiedObject of object
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @final
 */
class ProxyRepositoryDecorator extends RepositoryDecorator
{
    /**
     * @return list<Proxy<TProxiedObject>>|Proxy<TProxiedObject>
     */
    public function __call(string $method, array $arguments)
    {
        return $this->proxyResult($this->inner()->{$method}(...$arguments));
    }

    public function getIterator(): \Traversable
    {
        // TODO: $this->inner() is set to ObjectRepository, which is not
        //       iterable. Can this every be another RepositoryDecorator?
        if (\is_iterable($this->inner())) {
            return yield from $this->inner();
        }

        yield from $this->findAll();
    }

    /**
     * @deprecated use RepositoryDecorator::count()
     */
    public function getCount(): int
    {
        trigger_deprecation('zenstruck\foundry', '1.5.0', 'Using RepositoryDecorator::getCount() is deprecated, use RepositoryDecorator::count() (it is now Countable).');

        return $this->count();
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->empty()
     */
    public function assertEmpty(string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertEmpty() is deprecated, use RepositoryDecorator::assert()->empty().');

        $this->assert()->empty($message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->count()
     */
    public function assertCount(int $expectedCount, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertCount() is deprecated, use RepositoryDecorator::assert()->count().');

        $this->assert()->count($expectedCount, $message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->countGreaterThan()
     */
    public function assertCountGreaterThan(int $expected, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertCountGreaterThan() is deprecated, use RepositoryDecorator::assert()->countGreaterThan().');

        $this->assert()->countGreaterThan($expected, $message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->countGreaterThanOrEqual()
     */
    public function assertCountGreaterThanOrEqual(int $expected, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertCountGreaterThanOrEqual() is deprecated, use RepositoryDecorator::assert()->countGreaterThanOrEqual().');

        $this->assert()->countGreaterThanOrEqual($expected, $message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->countLessThan()
     */
    public function assertCountLessThan(int $expected, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertCountLessThan() is deprecated, use RepositoryDecorator::assert()->countLessThan().');

        $this->assert()->countLessThan($expected, $message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->countLessThanOrEqual()
     */
    public function assertCountLessThanOrEqual(int $expected, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertCountLessThanOrEqual() is deprecated, use RepositoryDecorator::assert()->countLessThanOrEqual().');

        $this->assert()->countLessThanOrEqual($expected, $message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->exists()
     * @phpstan-param Proxy<TProxiedObject>|array|mixed $criteria
     */
    public function assertExists($criteria, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertExists() is deprecated, use RepositoryDecorator::assert()->exists().');

        $this->assert()->exists($criteria, $message);

        return $this;
    }

    /**
     * @deprecated use RepositoryDecorator::assert()->notExists()
     * @phpstan-param Proxy<TProxiedObject>|array|mixed $criteria
     */
    public function assertNotExists($criteria, string $message = ''): self
    {
        trigger_deprecation('zenstruck\foundry', '1.8.0', 'Using RepositoryDecorator::assertNotExists() is deprecated, use RepositoryDecorator::assert()->notExists().');

        $this->assert()->notExists($criteria, $message);

        return $this;
    }

    /**
     * @return (Proxy&TProxiedObject)|null
     *
     * @phpstan-return Proxy<TProxiedObject>|null
     */
    public function first(string $sortedField = 'id'): ?Proxy
    {
        return $this->findBy([], [$sortedField => 'ASC'], 1)[0] ?? null;
    }

    /**
     * @return (Proxy&TProxiedObject)|null
     *
     * @phpstan-return Proxy<TProxiedObject>|null
     */
    public function last(string $sortedField = 'id'): ?Proxy
    {
        return $this->findBy([], [$sortedField => 'DESC'], 1)[0] ?? null;
    }

    /**
     * Remove all rows.
     */
    public function truncate(): void
    {
        $om = $this->getObjectManager();

        if ($om instanceof EntityManagerInterface) {
            $om->createQuery("DELETE {$this->getClassName()} e")->execute();

            return;
        }

        if ($om instanceof DocumentManager) {
            $om->getDocumentCollection($this->getClassName())->deleteMany([]);
        }
    }

    /**
     * Fetch one random object.
     *
     * @param array $attributes The findBy criteria
     *
     * @return Proxy&TProxiedObject
     *
     * @throws \RuntimeException if no objects are persisted
     *
     * @phpstan-return Proxy<TProxiedObject>
     */
    public function random(array $attributes = []): Proxy
    {
        return $this->randomSet(1, $attributes)[0];
    }

    /**
     * Fetch a random set of objects.
     *
     * @param int   $number     The number of objects to return
     * @param array $attributes The findBy criteria
     *
     * @return list<Proxy<TProxiedObject>>
     *
     * @throws \RuntimeException         if not enough persisted objects to satisfy the number requested
     * @throws \InvalidArgumentException if number is less than zero
     */
    public function randomSet(int $number, array $attributes = []): array
    {
        if ($number < 0) {
            throw new \InvalidArgumentException(\sprintf('$number must be positive (%d given).', $number));
        }

        return $this->randomRange($number, $number, $attributes);
    }

    /**
     * Fetch a random range of objects.
     *
     * @param int   $min        The minimum number of objects to return
     * @param int   $max        The maximum number of objects to return
     * @param array $attributes The findBy criteria
     *
     * @return list<Proxy<TProxiedObject>>
     *
     * @throws \RuntimeException         if not enough persisted objects to satisfy the max
     * @throws \InvalidArgumentException if min is less than zero
     * @throws \InvalidArgumentException if max is less than min
     */
    public function randomRange(int $min, int $max, array $attributes = []): array
    {
        if ($min < 0) {
            throw new \InvalidArgumentException(\sprintf('$min must be positive (%d given).', $min));
        }

        if ($max < $min) {
            throw new \InvalidArgumentException(\sprintf('$max (%d) cannot be less than $min (%d).', $max, $min));
        }

        $all = \array_values($this->findBy($attributes));

        \shuffle($all);

        if (\count($all) < $max) {
            throw new \RuntimeException(\sprintf('At least %d "%s" object(s) must have been persisted (%d persisted).', $max, $this->getClassName(), \count($all)));
        }

        return \array_slice($all, 0, \random_int($min, $max)); // @phpstan-ignore-line
    }

    /**
     * @param object|array|mixed $criteria
     *
     * @return (Proxy&TProxiedObject)|null
     *
     * @phpstan-param Proxy<TProxiedObject>|array|mixed $criteria
     * @phpstan-return Proxy<TProxiedObject>|null
     */
    public function find($criteria)
    {
        if ($criteria instanceof Proxy) {
            $criteria = $criteria->_real();
        }

        if (!\is_array($criteria)) {
            /** @var TProxiedObject|null $result */
            $result = $this->inner()->find($criteria);

            return $this->proxyResult($result);
        }

        $normalizedCriteria = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($criteria as $attributeName => $attributeValue) {
            if (!\is_object($attributeValue)) {
                $normalizedCriteria[$attributeName] = $attributeValue;

                continue;
            }

            if ($attributeValue instanceof Factory) {
                $attributeValue = $attributeValue->withoutPersisting()->createAndUnproxify();
            } elseif ($attributeValue instanceof Proxy) {
                $attributeValue = $attributeValue->_real();
            }

            try {
                $metadataForAttribute = $this->getObjectManager()->getClassMetadata($attributeValue::class);
            } catch (MappingException|ORMMappingException) {
                $normalizedCriteria[$attributeName] = $attributeValue;

                continue;
            }

            $isEmbedded = match ($metadataForAttribute::class) {
                ORMClassMetadata::class => $metadataForAttribute->isEmbeddedClass,
                ODMClassMetadata::class => $metadataForAttribute->isEmbeddedDocument,
                default => throw new \LogicException(\sprintf('Metadata class %s is not supported.', $metadataForAttribute::class)),
            };

            // it's a regular entity
            if (!$isEmbedded) {
                $normalizedCriteria[$attributeName] = $attributeValue;

                continue;
            }

            foreach ($metadataForAttribute->getFieldNames() as $field) {
                $embeddableFieldValue = $propertyAccessor->getValue($attributeValue, $field);
                if (\is_object($embeddableFieldValue)) {
                    throw new \InvalidArgumentException('Nested embeddable objects are still not supported in "find()" method.');
                }

                $normalizedCriteria["{$attributeName}.{$field}"] = $embeddableFieldValue;
            }
        }

        return $this->findOneBy($normalizedCriteria);
    }

    /**
     * @return list<Proxy<TProxiedObject>>
     */
    public function findAll(): array
    {
        return $this->proxyResult($this->inner()->findAll());
    }

    /**
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return list<Proxy<TProxiedObject>>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->proxyResult($this->inner()->findBy(self::normalizeCriteria($criteria), $orderBy, $limit, $offset));
    }

    /**
     * @param array|null $orderBy Some ObjectRepository's (ie Doctrine\ORM\EntityRepository) add this optional parameter
     *
     * @return (Proxy&TProxiedObject)|null
     *
     * @throws \RuntimeException if the wrapped ObjectRepository does not have the $orderBy parameter
     *
     * @phpstan-return Proxy<TProxiedObject>|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?Proxy
    {
        if (null !== $orderBy) {
            trigger_deprecation('zenstruck\foundry', '1.38.0', 'Argument "$orderBy" of method "%s()" is deprecated and will be removed in Foundry 2.0. Use "%s::findBy()" instead if you need an order.', __METHOD__, __CLASS__);
        }

        if (\is_array($orderBy)) {
            $wrappedParams = (new \ReflectionClass($this->inner()))->getMethod('findOneBy')->getParameters();

            if (!isset($wrappedParams[1]) || 'orderBy' !== $wrappedParams[1]->getName() || !($type = $wrappedParams[1]->getType()) instanceof \ReflectionNamedType || 'array' !== $type->getName()) {
                throw new \RuntimeException(\sprintf('Wrapped repository\'s (%s) findOneBy method does not have an $orderBy parameter.', $this->inner()::class));
            }
        }

        /** @var TProxiedObject|null $result */
        $result = $this->inner()->findOneBy(self::normalizeCriteria($criteria), $orderBy); // @phpstan-ignore-line
        if (null === $result) {
            return null;
        }

        return $this->proxyResult($result);
    }

    /**
     * @return class-string<TProxiedObject>
     */
    public function getClassName(): string
    {
        return $this->inner()->getClassName();
    }

    /**
     * @param TProxiedObject|list<TProxiedObject>|null $result
     *
     * @return Proxy|Proxy[]|object|object[]|mixed
     *
     * @phpstan-return ($result is array ? list<Proxy<TProxiedObject>> : Proxy<TProxiedObject>)
     */
    private function proxyResult(mixed $result)
    {
        if (\is_array($result)) {
            return \array_map(fn(mixed $o): mixed => $this->proxyResult($o), $result);
        }

        if ($result && \is_a($result, $this->getClassName())) {
            return ProxyObject::createFromPersisted($result);
        }

        return $result;
    }

    private static function normalizeCriteria(array $criteria): array
    {
        return \array_map(
            static fn($value) => $value instanceof Proxy ? $value->_real() : $value,
            $criteria,
        );
    }

    private function getObjectManager(): ObjectManager
    {
        return Factory::configuration()->objectManagerFor($this->getClassName());
    }
}

\class_exists(\Zenstruck\Foundry\RepositoryProxy::class);
