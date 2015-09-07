<?php

require_once('Mage/Wishlist/controllers/IndexController.php');
class Edge_StockReminder_IndexController extends Mage_Wishlist_IndexController
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->getLayout()->getBlock('head')->setTitle($this->__('Stock Reminder'));

        $session = Mage::getSingleton('customer/session');
        $block   = $this->getLayout()->getBlock('stockreminder');

        $referer = $session->getAddActionReferer(true);
        if ($block) {
            $block->setRefererUrl($this->_getRefererUrl());
            if ($referer) {
                $block->setRefererUrl($referer);
            }
        }

        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('checkout/session');
        $this->_initLayoutMessages('catalog/session');
        $this->_initLayoutMessages('stockreminder/session');

        $this->renderLayout();
    }

    /**
     * Retrieve stockreminder object
     * @param int $stockreminderId
     * @return Edge_StockReminder_Model_StockReminder|bool
     */
    protected function _getStockreminder($stockreminderId = null)
    {
        $stockreminder = Mage::registry('stockreminder');
        if ($stockreminder) {
            return $stockreminder;
        }

        try {
            if (!$stockreminderId) {
                $stockreminderId = $this->getRequest()->getParam('stockreminder_id');
            }
            $customerId = Mage::getSingleton('customer/session')->getCustomerId();
            /* @var Edge_StockReminder_Model_StockReminder $stockreminder */
            $stockreminder = Mage::getModel('stockreminder/stockreminder');

            if ($stockreminderId) {
                $stockreminder->load($stockreminderId);
            } else {
                $stockreminder->loadByCustomer($customerId, true);
            }

            if (!$stockreminder->getId() || $stockreminder->getCustomerId() != $customerId) {
                $stockreminder = null;
                Mage::throwException(
                    Mage::helper('stockreminder')->__("Requested stockreminder doesn't exist")
                );
            }

            Mage::register('stockreminder', $stockreminder);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('stockreminder/session')->addError($e->getMessage());
            return false;
        } catch (Exception $e) {
            Mage::getSingleton('stockreminder/session')->addException($e,
                Mage::helper('stockreminder')->__('Stockreminder could not be created.')
            );
            return false;
        }

        return $stockreminder;
    }

    public function removeAction()
    {
        $id = (int) $this->getRequest()->getParam('id');

        $stockItem = Mage::getModel('stockreminder/stockreminder')->load($id);
        if (!$stockItem->getId()) {
            return $this->norouteAction();
        }

        try {
            Mage::getModel('stockreminder/stockreminder')->setId($stockItem->getId())->delete();
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('stockreminder/session')->addError(
                $this->__('An error occurred while deleting the item from stockreminder: %s', $e->getMessage())
            );
        } catch (Exception $e) {
            Mage::getSingleton('stockreminder/session')->addError(
                $this->__('An error occurred while deleting the item from stockreminder.')
            );
        }

        $this->_redirectReferer(Mage::getUrl('*/*'));

    }
}

