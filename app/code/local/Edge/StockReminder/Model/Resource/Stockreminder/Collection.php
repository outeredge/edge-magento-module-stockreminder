<?php

class Edge_StockReminder_Model_Resource_Stockreminder_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _constuct()
    {
        $this->_init('stockreminder/stockreminder');
    }

    /**
     * Filter collection by customer
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return Edge_StockReminder_Model_Resource_Stockreminder_Collection
     */
    public function filterByCustomer(Mage_Customer_Model_Customer $customer)
    {
        return $this->filterByCustomerId($customer->getId());
    }

    /**
     * Filter collection by customer id
     *
     * @param int $customerId
     * @return Mage_Wishlist_Model_Resource_Wishlist_Collection
     */
    public function filterByCustomerId($customerId)
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter collection by customer ids
     *
     * @param array $customerIds
     * @return Mage_Wishlist_Model_Resource_Wishlist_Collection
     */
    public function filterByCustomerIds(array $customerIds)
    {
        $this->addFieldToFilter('customer_id', array('in' => $customerIds));
        return $this;
    }
}

