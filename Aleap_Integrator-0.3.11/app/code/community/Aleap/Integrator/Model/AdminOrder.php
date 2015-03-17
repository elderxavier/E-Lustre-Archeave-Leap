<?php

class Aleap_Integrator_Model_AdminOrder extends Mage_Core_Model_Abstract
{
    private $storeId = '1';
    private $groupId = '1';
    private $sendConfirmation = '0';

    private $orderData = array();

    private $sourceCustomer;
    private $aleapOrder;
    private $products;
    private $error;

    function __construct($aleapOrder, $storeId = null)
    {
        $this->aleapOrder = $aleapOrder;
        $this->storeId = $storeId;
    }

    public function getError()
    {
        return $this->error;
    }

    public function register()
    {
        $customer = Aleap_Integrator_Model_Customer::createFromOrder($this->aleapOrder);
        $this->sourceCustomer = $customer->getCustomer();
        $this->setOrderData($customer->getCustomerAddress());

        return $this->create();
    }

    private function setOrderData($customerAddress)
    {
        $orderItems = $this->loadProducts();

        $address = array(
                'customer_address_id' => $customerAddress->getId(),
                'prefix' => '',
                'firstname' => $customerAddress->getFirstname(),
                'middlename' => '',
                'lastname' => $customerAddress->getLastname(),
                'suffix' => '',
                'company' => '',
                'street' => $customerAddress->getStreet(),
                'city' => $customerAddress->getCity(),
                'country_id' => $customerAddress->getCountryId(),
                'region' => $customerAddress->getRegion(),
                'region_id' => $customerAddress->getRegionId(),
                'postcode' => $customerAddress->getPostcode(),
                'telephone' => $customerAddress->getTelephone(),
                'fax' => '',
                'vat_id' => $this->sourceCustomer->getTaxvat()
        );
        $billing_address = $address;
        $billing_address['firstname'] = $this->sourceCustomer->getFirstname();
        $billing_address['lastname'] = $this->sourceCustomer->getLastname();

        $marketplace = $this->aleapOrder->marketplace;
        $this->orderData = array(
                'session' => array(
                        'customer_id' => $this->sourceCustomer->getId(),
                        'store_id' => $this->storeId,
                ),
                'payment' => array(
                        'method' => 'aleap',
                ),
                'add_products' => $orderItems,
                'order' => array(
                        'currency' => 'BRL',
                        'account' => array(
                                'group_id' => $this->groupId,
                                'email' => $this->sourceCustomer->getEmail()
                        ),
                        'billing_address' => $billing_address,
                        'shipping_address' => $address,
                        'shipping_method' => Aleap_Integrator_Model_Source_ShippingMethod::getShippingMethod(),
                        'comment' => array(
                                'customer_note' => "Pedido feito em $marketplace via Achieve Leap.",
                        ),
                        'send_confirmation' => $this->sendConfirmation
                )
        );
    }

    private function loadProducts() {
        $result = Array();
        $this->products = Array();
        foreach ($this->aleapOrder->products as $orderProduct) {
            $product = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToFilter('sku', $orderProduct->sku)
                    ->addAttributeToSelect('*')
                    ->getFirstItem();

            $product->load($product->getId());
            $this->products[] = $product;
            $result[$product->getId()] = array('qty' => $orderProduct->quantity);
        }

        return $result;
    }

    /**
     * Retrieve order create model
     *
     * @return  Mage_Adminhtml_Model_Sales_Order_Create
     */
    protected function _getOrderCreateModel()
    {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    /**
     * Retrieve session object
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Initialize order creation session data
     *
     * @param array $data
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    protected function _initSession($data)
    {
        /* Get/identify customer */
        if (!empty($data['customer_id'])) {
            $this->_getSession()->setCustomerId((int)$data['customer_id']);
        }

        /* Get/identify store */
        if (!empty($data['store_id'])) {
            $this->_getSession()->setStoreId((int)$data['store_id']);
        }

        return $this;
    }

    /**
     * Creates order
     */
    public function create()
    {
        $orderData = $this->orderData;

        if (!empty($orderData)) {

            $this->_initSession($orderData['session']);

            try {
                $this->_processQuote($orderData);
                if (!empty($orderData['payment'])) {
                    $this->_getOrderCreateModel()->setPaymentData($orderData['payment']);
                    $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($orderData['payment']);
                }

                Mage::app()->getStore($this->storeId)->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "0");

                $_order = $this->_getOrderCreateModel()
                        ->importPostData($orderData['order'])
                        ->createOrder();

                $this->_getSession()->clear();
                Mage::unregister('rule_data');

                return $_order;
            } catch (Exception $e) {
                $error_messages = Array();
                $messages = $this->_getSession()->getMessages()->getItems();
                foreach($messages as $m) {
                    $error_messages[] = $m->getText();
                }
                $this->error = Array();
                $this->error['messages'] = $error_messages;
                $this->error['exception'] = $e;
            }
        }

        return null;
    }

    protected function _processQuote($data = array())
    {
        /* Saving order data */
        if (!empty($data['order'])) {
            $this->_getOrderCreateModel()->importPostData($data['order']);
        }

        $this->_getOrderCreateModel()->getBillingAddress();
        $this->_getOrderCreateModel()->setShippingAsBilling(true);

        /* Just like adding products from Magento admin grid */
        if (!empty($data['add_products'])) {
            $this->_getOrderCreateModel()->addProducts($data['add_products']);
        }

        /* Collect shipping rates */
        $this->_getOrderCreateModel()->collectShippingRates();

        /* Add payment data */
        if (!empty($data['payment'])) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($data['payment']);
        }

        $this->_getOrderCreateModel()
                ->initRuleData()
                ->saveQuote();

        if (!empty($data['payment'])) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($data['payment']);
        }

        return $this;
    }
}

