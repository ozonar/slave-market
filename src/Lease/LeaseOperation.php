<?php

namespace SlaveMarket\Lease;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use SlaveMarket\MastersRepository;
use SlaveMarket\SlavesRepository;

/**
 * Операция "Арендовать раба"
 *
 * @package SlaveMarket\Lease
 */
class LeaseOperation
{

    const MAX_WORK_HOUR_COUNT = 16;
    /**
     * @var LeaseContractsRepository
     */
    protected $contractsRepository;

    /**
     * @var MastersRepository
     */
    protected $mastersRepository;

    /**
     * @var SlavesRepository
     */
    protected $slavesRepository;

    /**
     * LeaseOperation constructor.
     *
     * @param LeaseContractsRepository $contractsRepo
     * @param MastersRepository $mastersRepo
     * @param SlavesRepository $slavesRepo
     */
    public function __construct(LeaseContractsRepository $contractsRepo, MastersRepository $mastersRepo, SlavesRepository $slavesRepo)
    {
        $this->contractsRepository = $contractsRepo;
        $this->mastersRepository = $mastersRepo;
        $this->slavesRepository = $slavesRepo;
    }

    /**
     * Выполнить операцию
     *
     * TODO Таймзоны, функция округления дат, обработка ошибок
     * @param LeaseRequest $request
     * @return LeaseResponse
     * @throws Exception
     */
    public function run(LeaseRequest $request): LeaseResponse
    {
        $response = new LeaseResponse();

        $timeFrom = new DateTime($request->timeFrom);
        $timeFrom = new DateTime($timeFrom->format('Y-m-d h:00'));

        $timeTo = new DateTime($request->timeTo);
        $timeTo = new DateTime($timeTo->format('Y-m-d h:01'));

        $step = new DateInterval('PT1H');
        $period = new DatePeriod($timeFrom, $step, $timeTo);

        $contracts = $this->contractsRepository->getForSlave($request->slaveId, $timeFrom->format('Y-m-d'), $timeTo->format('Y-m-d'));

        if ($busyHours = $this->findBusyHours($contracts, $period)) {
            $slave = $this->slavesRepository->getById($request->slaveId);
            $slaveId = $slave->getId();
            $slaveName = $slave->getName();
            $busyHoursString = implode(', ', $busyHours);

            $response->addError("Ошибка. Раб #$slaveId \"$slaveName\" занят. Занятые часы: $busyHoursString");
            return $response;
        }

        if ($this->isSameDay($period)) {
//            $this->oneDayLease();


            if (!$this->checkMaxHourCount($contracts)) {
                $response->addError('Ошибка. Рабы не могут работать больше 16 часов в сутки.');
                return $response;
            }

            $hours = iterator_count($period);

        } else {
//            $this->severalDayLease();
            $hoursInDays = [];
            foreach ($period as $item) {
                $currentDay = $hoursInDays[$item->format('Y-m-d')];
                if ($currentDay < self::MAX_WORK_HOUR_COUNT) {
                    $hoursInDays[$item->format('Y-m-d')]++;
                }
            }

            $hours = array_sum($hoursInDays);

        }

        $slave = $this->slavesRepository->getById($request->slaveId);
        $master = $this->mastersRepository->getById($request->masterId);

        $cost = $hours * $slave->getPricePerHour();
        $leaseContract = new LeaseContract($master, $slave, $cost);
        $leaseContract->setLeasedHoursByPeriod($period);
        $response->setLeaseContract($leaseContract);

        return $response;
    }

    private function isSameDay($period): bool
    {
        return $period->start->format('Y-m-d') === $period->end->format('Y-m-d');
    }


    private function checkMaxHourCount($contracts): bool
    {
        $hourCount = 0;
        foreach ($contracts as $contract) {
            $hourCount += $contract->getInterval();
        }

        if ($hourCount > self::MAX_WORK_HOUR_COUNT) {
            return false;
        }

        return true;
    }

    /**
     * @param LeaseContract[] $contracts
     * @param DatePeriod $period
     * @return array
     */
    private function findBusyHours(array $contracts, DatePeriod $period): array
    {
        foreach ($contracts as $contract) {
            foreach ($contract->leasedHours as $contractHour) {
                foreach ($period as $periodHour) {
                    if ($contractHour->getDateTime() == $periodHour) {
                        $busyHours[] = $periodHour->format('Y-m-d h');
                    }
                }
            }
        }

        return $busyHours ?? [];
    }

    private function oneDayLease()
    {

    }

    private function severalDayLease()
    {

    }

}