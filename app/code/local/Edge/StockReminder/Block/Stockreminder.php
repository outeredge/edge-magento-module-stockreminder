<?php

class Edge_StockReminder_Block_Stockreminder extends Mage_Core_Block_Template
{
    protected $_collection = null;

     /**
     * Price template
     *
     * @var string
     */
    protected $_priceBlockDefaultTemplate = 'catalog/product/price.phtml';

      /**
     * Flag which allow/disallow to use link for as low as price
     *
     * @var bool
     */
    protected $_useLinkForAsLowAs = true;

      /**
     * Price types
     *
     * @var array
     */
    protected $_priceBlockTypes = array();

    /**
     * Price block array
     *
     * @var array
     */
    protected $_priceBlock = array();

    /**
     * Default price block
     *
     * @var string
     */
    protected $_block = 'catalog/product_price';


    public function getCollection()
    {
        if (is_null($this->_collection)) {
            $this->_collection = Mage::getModel('stockreminder/stockreminder')->getCollection();
        }
        return $this->_collection;
    }

    public function hasStockreminderItems()
    {
        return $this->getStockReminderItemsCount() > 0;
    }

    public function getStockReminderItemsCount()
    {
        return $this->_getStockreminderlist()->getItemsCount();
    }

    protected function _getStockreminderlist()
    {
        return $this->_getHelper()->getStockreminderlist();
    }

    protected function _getHelper()
    {
        return Mage::helper('stockreminder');
    }

    /**
     * Retrieve Wishlist Product Items collection
     *
     * @return Mage_Wishlist_Model_Resource_Item_Collection
     */
    public function getStockreminderItems()
    {
        if (is_null($this->_collection)) {
            $this->_collection = $this->_createStockreminderItemCollection();
        }

        return $this->_collection;
    }

    protected function _createStockreminderItemCollection()
    {
        return $this->_getStockreminderlist()->getCollection();
    }

    public function getColumns()
    {
        $columns = array();
        foreach ($this->getSortedChildren() as $code) {
            $child = $this->getChild($code);
            $columns[] = $child;
        }

        return $columns;
    }

    public function getPriceHtml($product, $displayMinimalPrice = false, $idSuffix = '')
    {
        $type_id = $product->getTypeId();
        if (Mage::helper('catalog')->canApplyMsrp($product)) {
            $realPriceHtml = $this->_preparePriceRenderer($type_id)
                ->setProduct($product)
                ->setDisplayMinimalPrice($displayMinimalPrice)
                ->setIdSuffix($idSuffix)
                ->setIsEmulateMode(true)
                ->toHtml();
            $product->setAddToCartUrl($this->getAddToCartUrl($product));
            $product->setRealPriceHtml($realPriceHtml);
            $type_id = $this->_mapRenderer;
        }

        return $this->_preparePriceRenderer($type_id)
            ->setProduct($product)
            ->setDisplayMinimalPrice($displayMinimalPrice)
            ->setIdSuffix($idSuffix)
            ->toHtml();
    }

    /**
     * Prepares and returns block to render some product type
     *
     * @param string $productType
     * @return Mage_Core_Block_Template
     */
    public function _preparePriceRenderer($productType)
    {
        return $this->_getPriceBlock($productType)
            ->setTemplate($this->_getPriceBlockTemplate($productType))
            ->setUseLinkForAsLowAs($this->_useLinkForAsLowAs);
    }

    /**
     * Return Block template
     *
     * @param string $productTypeId
     * @return string
     */
    protected function _getPriceBlockTemplate($productTypeId)
    {
        if (isset($this->_priceBlockTypes[$productTypeId])) {
            if ($this->_priceBlockTypes[$productTypeId]['template'] != '') {
                return $this->_priceBlockTypes[$productTypeId]['template'];
            }
        }
        return $this->_priceBlockDefaultTemplate;
    }

     /**
     * Return price block
     *
     * @param string $productTypeId
     * @return mixed
     */
    protected function _getPriceBlock($productTypeId)
    {
        if (!isset($this->_priceBlock[$productTypeId])) {
            $block = $this->_block;
            if (isset($this->_priceBlockTypes[$productTypeId])) {
                if ($this->_priceBlockTypes[$productTypeId]['block'] != '') {
                    $block = $this->_priceBlockTypes[$productTypeId]['block'];
                }
            }
            $this->_priceBlock[$productTypeId] = $this->getLayout()->createBlock($block);
        }
        return $this->_priceBlock[$productTypeId];
    }

    public function getStockItems()
    {
        return Mage::getModel('stockreminder/stockreminder')
            ->getCollection()
            ->addFilter('customer_id', Mage::getSingleton('customer/session')->getCustomerId());
    }

}