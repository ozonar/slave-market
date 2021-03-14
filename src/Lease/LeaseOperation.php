<?php

namespace SlaveMarket\Lease;

use Cassandra\Date;
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
        $period = $this->getLeasePeriod($request->timeFrom, $request->timeTo);
        $master = $this->mastersRepository->getById($request->masterId);

        $contracts = $this->contractsRepository->getForSlave($request->slaveId, $period->start->format('Y-m-d'), $period->end->format('Y-m-d'));

        if (($busyHours = $this->findBusyHours($contracts, $period))) {
            $slave = $this->slavesRepository->getById($request->slaveId);
            $slaveId = $slave->getId();
            $slaveName = $slave->getName();
            $busyHoursString = implode(', ', $busyHours);

            $response->addError("Ошибка. Раб #$slaveId \"$slaveName\" занят. Занятые часы: $busyHoursString");
            return $response;
        }

        if ($this->isSameDay($period)) {
            if (!$this->checkMaxHourCount($contracts)) {
                $response->addError('Ошибка. Рабы не могут работать больше 16 часов в сутки.');
                return $response;
            }
        }


        $slave = $this->slavesRepository->getById($request->slaveId);

        $hours = $this->countLeaseDays($period);
        $cost = $hours * $slave->getPricePerHour();

        $leaseContract = new LeaseContract($master, $slave, $cost);
        $leaseContract->setLeasedHoursByPeriod($period);
        $response->setLeaseContract($leaseContract);

        return $response;
    }

    /**
     * @param $timeFrom
     * @param $timeTo
     * @return DatePeriod
     * @throws Exception
     */
    private function getLeasePeriod($timeFrom, $timeTo): DatePeriod
    {
        $timeFrom = new DateTime($timeFrom);
        $timeFrom = new DateTime($timeFrom->format('Y-m-d h:00'));

        $timeTo = new DateTime($timeTo);
        $timeTo = new DateTime($timeTo->format('Y-m-d h:01'));

        $step = new DateInterval('PT1H');
        return new DatePeriod($timeFrom, $step, $timeTo);
    }

    /**
     * @param $period
     * @return int
     */
    private function countLeaseDays($period): int
    {
        $hoursInDays = [];
        foreach ($period as $item) {
            $currentDay = $hoursInDays[$item->format('Y-m-d')];
            if ($currentDay < self::MAX_WORK_HOUR_COUNT) {
                $hoursInDays[$item->format('Y-m-d')]++;
            }
        }

        return array_sum($hoursInDays);
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
     * Сравнение часов каждого выбранного контракта с часами из желательного периода
     * TODO: Не выполено условие про расширяемость VIP. И тестов нет
     *
     * @param LeaseContract[] $contracts
     * @param DatePeriod $period
     * @param bool $isCurrentMasterVIP
     * @return array
     */
    private function findBusyHours(array $contracts, DatePeriod $period, $isCurrentMasterVIP = false): array
    {
        foreach ($contracts as $contract) {
            foreach ($contract->leasedHours as $contractHour) {
                foreach ($period as $periodHour) {
                    $datesSame = $contractHour->getDateTime() == $periodHour;

                    if (
                        $datesSame && !$isCurrentMasterVIP ||
                        $datesSame && $isCurrentMasterVIP && !$contract->master->isVIP()
                    ) {
                        $busyHours[] = $periodHour->format('Y-m-d h');
                    }
                }
            }
        }

        return $busyHours ?? [];
    }

}