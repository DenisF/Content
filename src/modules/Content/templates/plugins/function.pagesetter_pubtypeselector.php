<?php

/**
 * Pagesetter forms plugin for selecting pagesetter publication type
 *
 * @copyright (C) 2008, Content Development Team
 * @link http://code.zikula.org/content
 * @version $Id$
 * @license See license.txt
 */

/**
 * Standard Smarty function for this plugin
 */
function smarty_function_pagesetter_pubtypeselector($params, &$view)
{
    // Let the Form_Plugin class do all the hard work
    return $view->registerPlugin('Content_Form_Plugin_PagesetterPubTypeSelector', $params);
}
