<?php
/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */
namespace CyberSource\Payment\Block\Adminhtml\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field;

class Obscure extends Field
{
   protected function _getElementHtml(AbstractElement $element)
   {
       $element->setType('password'); 
       return parent::_getElementHtml($element);
   }
}