<?php

require_once(dirname(__FILE__) . "/../Model/Source/ShippingMethod.php");

class Aleap_Integrator_SetupController extends Mage_Core_Controller_Front_Action
{
    public function storesAction()
    {
        $stores = Mage::app()->getStores();
        $storesArray = array();
        foreach ($stores as $store) {
            $storesArray[] = $store->getData();
        }
        $this->sendJson($storesArray);
    }

    public function htAction()
    {
        $filePath = Mage::getBaseDir() . '/.htaccess';
        $this->getResponse()->setHeader('Content-type', 'text/plain', true);
        $this->getResponse()->setBody(file_get_contents($filePath));
    }

    public function configAction()
    {
        $filePath = Mage::getBaseDir() . '/app/code/community/Aleap/Integrator/etc/config.xml';
        $this->getResponse()->setHeader('Content-type', 'application/xml');
        $this->getResponse()->setBody(file_get_contents($filePath));
    }

    public function shippingMethodsAction() {
        $result = Array(
                'current' => Aleap_Integrator_Model_Source_ShippingMethod::getShippingMethod(),
                'available' => Aleap_Integrator_Model_Source_ShippingMethod::getShippingMethods(),
        );
        $this->sendJson($result);
    }

    public function currenciesAction() {
        $allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies();
        $this->sendJson($allowedCurrencies);
    }

    private function sendJson($data) {
        $jsonData = json_encode($data);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);
    }

    public function shippingMethodsByOrdersAction() {
        $orderMap = Array();

        $orders = Mage::getModel('sales/order')->getCollection();
        foreach($orders as $order) {
            $sm = $order->getShippingMethod();
            if (!$sm) {
                $sm = '[null]';
            }

            if (!array_key_exists($sm, $orderMap)) {
                $orderMap[$sm] = Array('count' => 0);
            }

            $orderMap[$sm]['count'] += 1;
            $orderMap[$sm]['last_order_id'] = $order->getId();
        }

        $this->sendJson($orderMap);
    }

    public function errorAction() {
        $code = (int) $this->getRequest()->getParam('http_code', '500');
        $response = $this->getResponse();
        $response->setHeader('Content-type', 'application/json');
        $response->setHttpResponseCode($code);
        $response->setBody('{ "http_code": ' . $code . " }");
    }
}
