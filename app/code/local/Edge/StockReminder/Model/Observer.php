<?php

class Edge_StockReminder_Model_Observer
{
    private $removeQty = false;

    public function checkOutOfStock($data)
    {
        $product    = $data->getEvent()->getProduct();
        $quoteItem  = $data->getEvent()->getQuoteItem();
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();

        if (!$product->getId()) {
            return;
        }

        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $quoteItem->getSku());

        $this->removeQty = true;
        $stockReminderQty  = $product->getQty();
        $productStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        if ($productStock->getQty() >= $quoteItem->getQty()) {
            //Nothing to save on stockreminder
            $this->removeQty = false;
            return;
        } elseif($productStock->getQty() != 0) {
            $stockReminderQty = $quoteItem->getQty() - $productStock->getQty();
            $this->removeQty  = $productStock->getQty();
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
                'qty'      => $stockReminderQty + $stockExist->getQty()
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
                'qty'         => $stockReminderQty
                );

            $model = Mage::getModel('stockreminder/stockreminder')->setData($data);

            try {
                $model->save()->getId();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
   }

    public function updateCart($observer)
    {
        $product = $observer->getEvent()->getProduct();

        $lastQuoteId = Mage::getSingleton('checkout/session')->getQuoteId();

        if ($lastQuoteId) {
            $customerQuote = Mage::getModel('sales/quote')
                ->loadByCustomer(Mage::getSingleton('customer/session')->getCustomerId());
            $customerQuote->setQuoteId($lastQuoteId);

            if ($this->removeQty === true) {
                $this->_removeItem($customerQuote, $product->getId());
            } elseif (is_numeric($this->removeQty)) {
                $this->_updateItem($customerQuote, $product->getId());
            }

        }
    }

    protected function _removeItem($quote, $productItemId)
    {
        foreach ($quote->getAllItems() as $item) {

            if ($item->getProductId() == $productItemId) {
                $item->isDeleted(true);
            }

            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $child->isDeleted(true);
                }
            }
        }
        $quote->collectTotals()->save();
    }

    protected function _updateItem($quote, $productItemId)
    {
        foreach ($quote->getAllItems() as $item) {

            if ($item->getProductId() == $productItemId) {
                $item->setQty($this->removeQty);
            }

            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $item->setQty($this->removeQty);
                }
            }
        }
        $quote->collectTotals()->save();
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

}
