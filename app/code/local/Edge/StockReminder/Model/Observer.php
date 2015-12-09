<?php

class Edge_StockReminder_Model_Observer
{
    private $removeQty = false;

    public function checkOutOfStock($data)
    {
        $message        = '';
        $product        = $data->getEvent()->getProduct();
	$productId 	= $product->getId();
        $quantity       = Mage::app()->getRequest()->getParam('qty');
        $superAttribute = Mage::app()->getRequest()->getParam('super_attribute');
        $bundleOption   = Mage::app()->getRequest()->getParam('bundle_option');
        $customerId     = Mage::getSingleton('customer/session')->getCustomerId();

        if (!$productId) {
            return;
        }

        if (empty($superAttribute)) {
            $extraData = Mage::app()->getRequest()->getParam('product');
            if (is_array($extraData)) {
               foreach ($extraData as $extra) {
                    $extraId = Mage::getModel('catalog/product')->getIdBySku($extra['sku']);
                    if ($extraId == $productId) {
                        if (isset($extra['super_attribute'])) {
                            $superAttribute = $extra['super_attribute'];
                        }
                        break;
                    }
                }
            }
        }

        if ($superAttribute) {
            $product = Mage::getModel('catalog/product')->load($productId);
            $productId = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($superAttribute, $product)->getId();
        }

        $this->removeQty = true;
        $productStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
        $stockReminderQty  = $productStock->getQty();

        //Bundle Check
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $bundled_product = new Mage_Catalog_Model_Product();
            $bundled_product->load($product->getId());

            $selectionCollection = $bundled_product->getTypeInstance(true)->getSelectionsCollection(
            $bundled_product->getTypeInstance(true)->getOptionsIds($bundled_product), $bundled_product
            );

            foreach ($selectionCollection as $option) {
                $bundleItemStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($option->product_id);
                $bundleStockArray[] = $bundleItemStock->getQty();
            }
            $productStock->setQty(min($bundleStockArray));
        }

	$productStock->setQty(max($productStock->getQty(), 0));
        if ($productStock->getQty() >= $quantity) {
            //Nothing to save on stockreminder
            $this->removeQty = false;

            //Delete product from stock reminder if exist
            $data = array('customer_id' => $customerId, 'product_id' => $productId);
            if ($result = Mage::getModel('stockreminder/stockreminder')->getByCustomerAndProduct($data)) {
                Mage::getModel('stockreminder/stockreminder')->removeStockReminder($result['stockreminder_id']);
            }
            return;
        } elseif($productStock->getQty() != 0) {
            $stockReminderQty = $quantity - $productStock->getQty();
            $this->removeQty  = $productStock->getQty();
        }

        $stockExist = Mage::getModel('stockreminder/stockreminder')
            ->getByCustomerAndProduct([
                'customer_id' => $customerId,
                'product_id'  => $productId
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
                $message = 'Limited by stock.';
            } catch (Exception $e){
                echo $e->getMessage();
            }
        } else {
            //create
            $data = array(
                'customer_id' => $customerId,
                'product_id'  => $productId,
                'store_id'    => Mage::app()->getStore()->getStoreId(),
                'added_at'    => Mage::getModel('core/date')->gmtTimestamp(),
                'qty'         => $stockReminderQty
                );

            $model = Mage::getModel('stockreminder/stockreminder')->setData($data);

            try {
                $model->save()->getId();
                $message = 'Limited by stock.';
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

        return  Mage::app()->getResponse()->setBody($message);
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

    public function sendStockIsBack()
    {
        $allStockReminders = Mage::getModel('stockreminder/stockreminder')->getCollection()
            ->addFilter('stock', 0);

        $stockReminders = [];
        foreach ($allStockReminders as $stockReminder) {
            $model = Mage::getModel('catalog/product');
            $_product = $model->load($stockReminder['product_id']);
            $stocklevel = (int)Mage::getModel('cataloginventory/stock_item')
                            ->loadByProduct($_product)->getQty();

            if ($stocklevel > 0) {
                $stockReminders[] = $stockReminder;
            }
        }

        $translate = Mage::getSingleton('core/translate');
        /* @var $translate Mage_Core_Model_Translate */
        $translate->setTranslateInline(false);

        $storeId = Mage::app()->getStore()->getStoreId();
        $templateConfigPath = 'stockreminder/email/stock_reminder_email_template';
        $mailTemplate = Mage::getModel('core/email_template');
        $template     = Mage::getStoreConfig($templateConfigPath, $storeId);

        foreach ($stockReminders as $stockReminder) {
            $productData  = Mage::getModel('catalog/product')->load($stockReminder['product_id']);
            $customerData = Mage::getModel('customer/customer')->load($stockReminder['customer_id'])->getData();
            $email        = $customerData['email'];
            $name         = $customerData['firstname'].' '.$customerData['lastname'];

            $mailTemplate = Mage::getModel('core/email_template');
            $mailTemplate->setDesignConfig(array('area' => 'frontend', 'store' => $storeId))
                ->sendTransactional(
                    $template,
                    'general',
                    $email,
                    $name,
                    array(
                        'productName'  => $productData->getName(),
                        'productUrl'   => $productData->getProductUrl(),
                        'stock'        => $productData->getStockItem()->getQty() * 1
                    )
            );

            Mage::getModel('stockreminder/stockreminder')->emailStockReminderSent($stockReminder['stockreminder_id']);
            $translate->setTranslateInline(true);
        }

        return $this;
    }

}
