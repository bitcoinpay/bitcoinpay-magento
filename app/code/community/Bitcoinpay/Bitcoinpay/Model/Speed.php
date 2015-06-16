<?php

class Bitcoinpay_Bitcoinpay_Model_Speed
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'LOW',
                'label' => 'Low'
            ),
            array(
                'value' => 'MEDIUM',
                'label' => 'Medium'
            ),
            array(
                'value' => 'HIGH',
                'label' => 'High'
            )
        );
    }
}
