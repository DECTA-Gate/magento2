<?php

namespace Decta\Decta\Model;

class Decta extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'decta_decta';

    protected $_code = self::CODE;
}