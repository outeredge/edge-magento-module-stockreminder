<?php

class Edge_StockReminder_Model_Stockreminder extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        $this->_init('stockreminder/stockreminder');
    }

    /**
     * Load stockreminder by customer
     *
     * @param mixed $customer
     * @param bool $create Create stockreminder if don't exists
     * @return Edge_StockReminder_Model_Stockreminder
     */
    public function loadByCustomer($customer, $create = false)
    {
        if ($customer instanceof Mage_Customer_Model_Customer) {
            $customer = $customer->getId();
        }

        $customer = (int) $customer;
        $customerIdFieldName = $this->_getResource()->getCustomerIdFieldName();
        $this->_getResource()->load($this, $customer, $customerIdFieldName);
        if (!$this->getId() && $create) {
            $this->setCustomerId($customer);
            $this->save();
        }
        return $this;
    }

    /**
     * Retrieve stockreminder items count
     *
     * @return int
     */
    public function getItemsCount()
    {
        return $this->getCollection()->getSize();
    }

    public function getProduct()
    {
        $product = $this->_getData('product');
        if (is_null($product)) {
            if (!$this->getProductId()) {
                throw new Mage_Core_Exception(Mage::helper('stockreminder')->__('Cannot specify product.'),
                    self::EXCEPTION_CODE_NOT_SPECIFIED_PRODUCT);
            }

            $product = Mage::getModel('catalog/product')
                ->setStoreId($this->getStoreId())
                ->load($this->getProductId());

            $this->setData('product', $product);
        }

        /**
         * Reset product final price because it related to custom options
         */
        $product->setFinalPrice(null);
        return $product;
    }

    public function getByCustomerAndProduct($data)
    {
        $result = $this->getCollection()
            ->addFilter('customer_id', $data['customer_id'])
            ->addFilter('product_id', $data['product_id']);

        if ($result->getData()) {
            return $result->getFirstItem();
        }

        return false;
    }

    public function removeStockReminder($idStockReminder)
    {
        try {
            $this->setId($idStockReminder)->delete();
        } catch (Exception $e){
            return $e->getMessage();
        }

        return true;
    }

    public function getStockByProduct($productId) {

        $result = $this->getCollection()
            ->addFilter('product_id', $productId);

        return $result->getData();
    }

    public function emailStockReminderSent($idStockReminder)
    {
        $model = $this->load($idStockReminder)->addData(array('stock' => 1));
        try {
            $model->setId($idStockReminder)->save();
        } catch (Exception $e){
            return $e->getMessage();
        }

        return true;
    }

    public function getBackInStockByCustomer($customer)
    {
        $result = $this->getCollection()
            ->addFilter('customer_id', $customer);

        if ($result->getData()) {

            $output = [];
            foreach ($result->getData() as $item) {

                if (is_array($item) && isset($item['product_id'])) {

                    $product = Mage::getModel('catalog/product')->load((int)$item['product_id']);
                    $stocklevel = (int)Mage::getModel('cataloginventory/stock_item')
                        ->loadByProduct($product)->getQty();

                    if ($product->isSaleable() && $stocklevel >= $item['qty']) {

                        $product['requested_quantity'] = $item['qty'];
                        $output[] = $product;
                    }
                }
            }
            return $output;
        }

        return false;
    }
}

