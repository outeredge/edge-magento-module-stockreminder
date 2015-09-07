<?php
class Edge_StockReminder_Helper_Data extends Mage_Core_Helper_Abstract
{

    protected $_stock = null;

    /**
     * Currently logged in customer
     *
     * @var Mage_Customer_Model_Customer
     */
    protected $_currentCustomer = null;

    /**
     * Retreive customer session
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

     /**
     * Retrieve current customer
     *
     * @return Mage_Customer_Model_Customer|null
     */
    public function getCustomer()
    {
        if (!$this->_currentCustomer && $this->_getCustomerSession()->isLoggedIn()) {
            $this->_currentCustomer = $this->_getCustomerSession()->getCustomer();
        }
        return $this->_currentCustomer;
    }

    public function getStockreminderlist()
    {
        $this->_stock = Mage::registry('stockreminder');
        if ($this->getCustomer()) {
            $this->_stock = Mage::getModel('stockreminder/stockreminder');
            $this->_stock->loadByCustomer($this->getCustomer());
        }

        return $this->_stock;
    }

}
