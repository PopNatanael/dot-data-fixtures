<?php

declare(strict_types=1);

namespace DotTest\DataFixtures\Factory;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Dot\DataFixtures\Command\ExecuteFixturesCommand;
use Dot\DataFixtures\Exception\NotFoundException;
use Dot\DataFixtures\Factory\ExecuteFixturesCommandFactory;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function getcwd;

class ExecuteFixturesCommandFactoryTest extends TestCase
{
    protected ContainerInterface|MockObject $container;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundException
     * @throws NotFoundExceptionInterface
     */
    public function testWillNotCreateServiceWithoutEntityManager(): void
    {
        $this->container->expects($this->once())
            ->method('has')
            ->with(EntityManager::class)
            ->willReturn(false);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('EntityManager not found.');
        (new ExecuteFixturesCommandFactory())($this->container);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testWillNotCreateServiceWithoutLoader(): void
    {
        $this->container->method('has')->willReturnMap([
            [EntityManager::class, true],
            [Loader::class, false],
            [ORMPurger::class, true],
            [ORMExecutor::class, true],
            ['config', true],
        ]);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Loader not found.');
        (new ExecuteFixturesCommandFactory())($this->container);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testWillNotCreateServiceWithoutPurger(): void
    {
        $this->container->method('has')->willReturnMap([
            [EntityManager::class, true],
            [Loader::class, true],
            [ORMPurger::class, false],
            [ORMExecutor::class, true],
            ['config', true],
        ]);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('ORMPurger not found.');
        (new ExecuteFixturesCommandFactory())($this->container);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testWillNotCreateServiceWithoutExecutor(): void
    {
        $this->container->method('has')->willReturnMap([
            [EntityManager::class, true],
            [Loader::class, true],
            [ORMPurger::class, true],
            [ORMExecutor::class, false],
            ['config', true],
        ]);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('ORMExecutor not found.');
        (new ExecuteFixturesCommandFactory())($this->container);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundException
     * @throws NotFoundExceptionInterface
     */
    public function testWillNotCreateServiceWithoutPath(): void
    {
        $this->container->method('has')->willReturnMap([
            [EntityManager::class, true],
            [Loader::class, true],
            [ORMPurger::class, true],
            [ORMExecutor::class, true],
            ['config', true],
        ]);
        $this->container->method('get')
            ->with('config')
            ->willReturn(null);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Key `fixtures` not found in doctrine configuration.');
        (new ExecuteFixturesCommandFactory())($this->container);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundException
     * @throws NotFoundExceptionInterface
     */
    public function testPath(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $connection    = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManager::class);
        $eventManager  = $this->createMock(EventManager::class);
        $loader        = $this->createMock(Loader::class);
        $purger        = $this->createMock(ORMPurger::class);
        $executor      = $this->createMock(ORMExecutor::class);
        $connection->method('getConfiguration')->willReturn($configuration);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getEventManager')->willReturn($eventManager);
        $purger->method('getObjectManager')->willReturn($entityManager);
        $loader->method('getFixtures')->willReturnMap([
            [
                [],
            ],
        ]);
        $this->container->method('has')->willReturnMap([
            [EntityManager::class, true],
            [Loader::class, true],
            [ORMPurger::class, true],
            [ORMExecutor::class, true],
            ['config', true],
        ]);

        $this->container->method('get')->willReturnMap([
            [EntityManager::class, $entityManager],
            [Loader::class, $loader],
            [ORMPurger::class, $purger],
            [ORMExecutor::class, $executor],
            ['config', ['doctrine' => ['fixtures' => getcwd() . '/data/doctrine/fixtures']]],
        ]);

        $entityManager->method('getConnection')->willReturn($connection);
        $connection->method('getConfiguration')->willReturn($configuration);
        $factory = (new ExecuteFixturesCommandFactory())($this->container);
        $this->assertInstanceOf(ExecuteFixturesCommand::class, $factory);
        $path = $this->container->get('config')['doctrine']['fixtures'];
        $this->assertSame(getcwd() . '/data/doctrine/fixtures', $path);
    }
}