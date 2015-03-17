<?php

class Aleap_Integrator_Model_Source_ShippingMethod
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $result = Array();
        $shippingMethods = Aleap_Integrator_Model_Source_ShippingMethod::getShippingMethods();
        foreach (array_keys($shippingMethods) as $shippingMethodCode) {
            $sm = $shippingMethods[$shippingMethodCode];

            $result[] = array(
                'value' => $shippingMethodCode,
                'label' => $sm['carrier'] . '/' . $sm['method'] . ' (' . $sm['method_code'] . ')'
            );
        }

        return $result;
    }

    public static function getShippingMethod() {
        $shippingMethod = Mage::getStoreConfig('general/aleap_integrator/shipping_method_text');
        if (empty($shippingMethod)) {
            $shippingMethod = Mage::getStoreConfig('general/aleap_integrator/shipping_method');
        }

        return $shippingMethod;
    }

    /**
     * @return array
     */
    public static function getShippingMethods() {
        $shippingMethods = Array();

        /** @var Mage_Sales_Model_Quote_Address_Rate[] $rates */
        $rates = Mage::getModel('sales/quote_address_rate')->getCollection();
        foreach ($rates as $rate) {
            if ($rate) {
                $shippingMethods[$rate->getCode()] = Array(
                        'carrier' => $rate->getCarrierTitle(),
                        'method_code' => $rate->getMethod(),
                        'method' => $rate->getMethodTitle()
                );
            }
        }

        return $shippingMethods;
    }
}
