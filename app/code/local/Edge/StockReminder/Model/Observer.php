<?php

class Edge_StockReminder_Model_Observer
{
    public function checkOutOfStock($data)
    {
        $product = $data['product'];
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();

        if (!$product->getId() || Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getIsInStock()) {
            return;
        }

        $stockExist = Mage::getModel('stockreminder/stockreminder')
            ->getByCustomerAndProduct([
                'customer_id' => $customerId,
                'product_id'  => $product->getId()
                ]);

        if ($stockExist) {
            //update
            $data = array(
                'added_at' => Mage::getModel('core/date')->gmtTimestamp(),
                'qty'      => $product->getQty() + $stockExist->getQty()
                );

            $model = Mage::getModel('stockreminder/stockreminder')->load($stockExist->getStockreminderId())->addData($data);
            try {
                $model->setId($stockExist->getStockreminderId())->save();
            } catch (Exception $e){
                echo $e->getMessage();
            }
        } else {
            //create
            $data = array(
                'customer_id' => $customerId,
                'product_id'  => $product->getId(),
                'store_id'    => Mage::app()->getStore()->getStoreId(),
                'added_at'    => Mage::getModel('core/date')->gmtTimestamp(),
                'qty'         => $product->getQty()
                );

            $model = Mage::getModel('stockreminder/stockreminder')->setData($data);

            try {
                $model->save()->getId();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

    }

}
