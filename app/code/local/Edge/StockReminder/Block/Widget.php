<?php

class Edge_StockReminder_Block_Widget extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    protected function _toHtml()
    {
        $customerId = Mage::helper('stockreminder')->getCustomer();
        if (is_null($customerId)) {
            return;
        }

        $backInStock = Mage::getModel('stockreminder/stockreminder')->getBackInStockByCustomer($customerId->getId());
        if (!$backInStock) {
            return;
        }

        $html = '<div id="widget-back-in-stock-reminder">' ;
        $html .= '<div>Back In Stock Products</div>';

        foreach ($backInStock as $product) {

            $html .= '<div>'
                . '<form action='. Mage::helper('checkout/cart')->getAddUrl($product) .' method="post">'
                . '<div><a href="'.$product->getProductUrl().'" title="'. $this->escapeHtml($product->getName()).'">'
                . $this->escapeHtml($product->getName()) .'</a></div>'
                . $this->getPriceHtml($product)
                . '<input type="text" class="input-text qty validate-not-negative-number" name="qty" value="'. $product['requested_quantity']*1 .'">'
                . '<input type="hidden" name="product" value="'.$product->getId().'">'
                . '<button type="submit" title="'. $this->__('Add to Cart') .'" class="button btn-cart"><span><span>'. $this->__('Add to Cart') .'</span></span></button>'
                . '</form>';
        }

        $html .= "</div>";
        return $html;
    }
}