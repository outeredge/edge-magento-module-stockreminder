<?php

class Edge_StockReminder_Model_Observer
{
    public function checkOutOfStock($data)
    {
        $product = $data['product'];
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();

        if (!$product->getId() || Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty() > 0) {
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

        $this->removeLastProductAddedToCart($product);
    }

    public function sendStockIsBack($observer)
    {
        $stockUpdated = $observer->getEvent()->getDataObject();

        if ($stockUpdated->getQty() <= 0) {
            return;
        }

        $stockReminders = Mage::getModel('stockreminder/stockreminder')->getStockByProduct($stockUpdated->getProductId());
        if (!$stockReminders) {
            return;
        }

        $translate = Mage::getSingleton('core/translate');
        /* @var $translate Mage_Core_Model_Translate */
        $translate->setTranslateInline(false);

        $templateCode = 'stock_reminder_email_template';
        $storeId      = Mage::app()->getStore()->getStoreId();

        foreach ($stockReminders as $stockReminder) {
            $productData  = Mage::getModel('catalog/product')->load($stockUpdated->getProductId());
            $customerData = Mage::getModel('customer/customer')->load($stockReminder['customer_id'])->getData();
            $email        = $customerData['email'];
            $name         = $customerData['firstname'].' '.$customerData['lastname'];

            $mailTemplate = Mage::getModel('core/email_template');
            $mailTemplate->setDesignConfig(array('area' => 'frontend', 'store' => $storeId))
                ->sendTransactional(
                    $templateCode,
                    'general',
                    $email,
                    $name,
                    array(
                        'product'  => $productData,
                        'stock'    => $stockUpdated
                    )
            );
            $translate->setTranslateInline(true);

        }

        return $this;
    }

    public function removeLastProductAddedToCart($productId)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $cart->removeItem($productId);
        $cart->save();
    }
}
