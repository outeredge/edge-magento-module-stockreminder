<?php

class Edge_StockReminder_Model_Resource_Stockreminder extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Store customer ID field name
     *
     * @var string
     */
    protected $_customerIdFieldName = 'customer_id';

    /**
     * Initialize stockreminder model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('stockreminder/stockreminder', 'stockreminder_id');
    }

    /**
     * Getter for customer ID field name
     *
     * @return string
     */
    public function getCustomerIdFieldName()
    {
        return $this->_customerIdFieldName;
    }

    /**
     * Setter for customer ID field name
     *
     * @param $fieldName
     *
     * @return Edge_StockReminder_Model_Resource_Stockreminder
     */
    public function setCustomerIdFieldName($fieldName)
    {
        $this->_customerIdFieldName = $fieldName;
        return $this;
    }

        /**
     * Prepare wishlist load select query
     *
     * @param string $field
     * @param mixed $value
     * @param mixed $object
     * @return Zend_Db_Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        if ($field == $this->_customerIdFieldName) {
            $select->order('added_at ' . Zend_Db_Select::SQL_ASC)
                ->limit(1);
        }
        return $select;
    }
}

