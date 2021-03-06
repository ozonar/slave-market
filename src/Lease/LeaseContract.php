<?php

namespace SlaveMarket\Lease;

use SlaveMarket\Master;
use SlaveMarket\Slave;

/**
 * Договор аренды
 *
 * @package SlaveMarket\Lease
 */
class LeaseContract
{
    /** @var Master Хозяин */
    public $master;

    /** @var Slave Раб */
    public $slave;

    /** @var float Стоимость */
    public $price = 0;

    /** @var LeaseHour[] Список арендованных часов */
    public $leasedHours = [];

    public function __construct(Master $master, Slave $slave, float $price, array $leasedHours = [])
    {
        $this->master      = $master;
        $this->slave       = $slave;
        $this->price       = $price;
        $this->leasedHours = $leasedHours ?? [];
    }

    public function getIntervalCount()
    {
        return count($this->leasedHours);
    }

    /**
     * @param \DatePeriod $period
     */
    public function setLeasedHoursByPeriod(\DatePeriod $period)
    {
        foreach ($period as $item) {
            $this->leasedHours[] = new LeaseHour($item->format('Y-m-d H'));
        }
    }

}
