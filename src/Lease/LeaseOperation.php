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

    const MAX_WORK_HOUR = 16;
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
     * TODO Таймзоны, функция округления дат, создание текста ошибок
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

        if ($timeFrom->format('Y-m-d') === $timeFrom->format('Y-m-d')) {
//            $this->oneDayLease();

            $contracts = $this->contractsRepository->getForSlave($request->slaveId, $timeFrom->format('Y-m-d'), $timeTo->format('Y-m-d'));
            if ($this->checkHourAvailablity($contracts, $period)) {

                $slave = current($contracts)->slave; // TODO Костыль, взять из базы

                $slaveId = $slave->getId();
                $slaveName = $slave->getName();

                $busyHours = $this->getBusyHours($contracts, $period);
                $busyHoursString = implode(', ', $busyHours);
                $response->addError("Ошибка. Раб #$slaveId \"$slaveName\" занят. Занятые часы: $busyHoursString");
            }

            if (!$this->checkMaxHourCount($contracts)) {
                $response->addError('Ошибка. Рабы не могут работать больше 16 часов в сутки.');
            }

        } else {
//            $this->severalDayLease();
        }


        return $response;
    }

    private function checkMaxHourCount($contracts): bool
    {
        $hourCount = 0;
        foreach ($contracts as $contract) {
            $hourCount += $contract->getInterval();
        }

        if ($hourCount > self::MAX_WORK_HOUR) {
            return false;
        }

        return true;
    }

    /**
     * @param LeaseContract[] $contracts
     * @param $period
     * @return bool
     */
    private function checkHourAvailablity(array $contracts, DatePeriod $period): bool
    {
        foreach ($contracts as $contract) {
            foreach ($contract->leasedHours as $contractHour) {
                foreach ($period as $periodHour) {
                    if ($contractHour == $periodHour) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param LeaseContract[] $contracts
     * @param DatePeriod $period
     * @return array
     */
    private function getBusyHours(array $contracts, DatePeriod $period): array
    {
        foreach ($contracts as $contract) {
            foreach ($contract->leasedHours as $contractHour) {
                foreach ($period as $periodHour) {
//                        echo "<pre>\n"; var_dump($contractHour->getDateTime(),':', $periodHour); echo "\n</pre>";
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