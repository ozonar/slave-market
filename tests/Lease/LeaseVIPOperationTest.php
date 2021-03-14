<?php

namespace SlaveMarket\Lease;

use PHPUnit\Framework\TestCase;
use SlaveMarket\Master;
use SlaveMarket\MastersRepository;
use SlaveMarket\Slave;
use SlaveMarket\SlavesRepository;

/**
 * Тесты операции аренды раба
 *
 * @package SlaveMarket\Lease
 */
class LeaseOperationTest extends TestCase
{
    /**
     * Stub репозитория хозяев
     *
     * @param Master ...$masters
     * @return MastersRepository
     */
    private function makeFakeMasterRepository(...$masters): MastersRepository
    {
        /** @var MastersRepository $mastersRepository */
        $mastersRepository = $this->prophesize(MastersRepository::class);
        foreach ($masters as $master) {
            $mastersRepository->getById($master->getId())->willReturn($master);
        }

        return $mastersRepository->reveal();
    }

    /**
     * Stub репозитория рабов
     *
     * @param Slave ...$slaves
     * @return SlavesRepository
     */
    private function makeFakeSlaveRepository(...$slaves): SlavesRepository
    {
        /** @var SlavesRepository $slavesRepository */
        $slavesRepository = $this->prophesize(SlavesRepository::class);
        foreach ($slaves as $slave) {
            $slavesRepository->getById($slave->getId())->willReturn($slave);
        }

        return $slavesRepository->reveal();
    }

    public function test_vipCanLeaseNotVip()
    {
        // -- Arrange
        {
            // Хозяева
            $master1    = new Master(1, 'Господин Боб');
            $master2    = new Master(2, 'сэр Вонючка', true);
            $masterRepo = $this->makeFakeMasterRepository($master1, $master2);

            // Раб
            $slave1    = new Slave(1, 'Уродливый Фред', 20);
            $slaveRepo = $this->makeFakeSlaveRepository($slave1);

            // Договор аренды. 1й хозяин арендовал раба
            $leaseContract1 = new LeaseContract($master1, $slave1, 80, [
                new LeaseHour('2017-01-01 00'),
                new LeaseHour('2017-01-01 01'),
                new LeaseHour('2017-01-01 02'),
                new LeaseHour('2017-01-01 03'),
            ]);

            // Stub репозитория договоров
            /** @var \Prophecy\Prophecy\ObjectProphecy|LeaseContractsRepository $contractsRepo */
            $contractsRepo = $this->prophesize(LeaseContractsRepository::class);
            $contractsRepo
                ->getForSlave($slave1->getId(), '2017-01-01', '2017-01-01')
                ->willReturn([$leaseContract1]);

            // Запрос на новую аренду. 2й хозяин выбрал занятое время
            $leaseRequest           = new LeaseRequest();
            $leaseRequest->masterId = $master2->getId();
            $leaseRequest->slaveId  = $slave1->getId();
            $leaseRequest->timeFrom = '2017-01-01 01:30:00';
            $leaseRequest->timeTo   = '2017-01-01 02:01:00';

            // Операция аренды
            $leaseOperation = new LeaseOperation($contractsRepo->reveal(), $masterRepo, $slaveRepo);
        }

        // -- Act
        $response = $leaseOperation->run($leaseRequest);

        // -- Assert
        $this->assertEmpty($response->getErrors());
        $this->assertInstanceOf(LeaseContract::class, $response->getLeaseContract());
        $this->assertEquals(40, $response->getLeaseContract()->price);
    }

    public function test_vipCanNotLeaseVip()
    {
        // -- Arrange
        {
            // Хозяева
            $master1    = new Master(1, 'Господин Боб', true);
            $master2    = new Master(2, 'сэр Вонючка', true);
            $masterRepo = $this->makeFakeMasterRepository($master1, $master2);

            // Раб
            $slave1    = new Slave(1, 'Уродливый Фред', 20);
            $slaveRepo = $this->makeFakeSlaveRepository($slave1);

            // Договор аренды. 1й хозяин арендовал раба
            $leaseContract1 = new LeaseContract($master1, $slave1, 80, [
                new LeaseHour('2017-01-01 00'),
                new LeaseHour('2017-01-01 01'),
                new LeaseHour('2017-01-01 02'),
                new LeaseHour('2017-01-01 03'),
            ]);

            // Stub репозитория договоров
            /** @var \Prophecy\Prophecy\ObjectProphecy|LeaseContractsRepository $contractsRepo */
            $contractsRepo = $this->prophesize(LeaseContractsRepository::class);
            $contractsRepo
                ->getForSlave($slave1->getId(), '2017-01-01', '2017-01-01')
                ->willReturn([$leaseContract1]);

            // Запрос на новую аренду. 2й хозяин выбрал занятое время
            $leaseRequest           = new LeaseRequest();
            $leaseRequest->masterId = $master2->getId();
            $leaseRequest->slaveId  = $slave1->getId();
            $leaseRequest->timeFrom = '2017-01-01 01:30:00';
            $leaseRequest->timeTo   = '2017-01-01 02:01:00';

            // Операция аренды
            $leaseOperation = new LeaseOperation($contractsRepo->reveal(), $masterRepo, $slaveRepo);
        }

        // -- Act
        $response = $leaseOperation->run($leaseRequest);

        // -- Assert
        $expectedErrors = ['Ошибка. Раб #1 "Уродливый Фред" занят. Занятые часы: 2017-01-01 01, 2017-01-01 02'];

        $this->assertArraySubset($expectedErrors, $response->getErrors());
        $this->assertNull($response->getLeaseContract());
    }
}