<?php
/**
 * Content html plugin
 *
 * @copyright (C) 2007-2010, Content Development Team
 * @link http://code.zikula.org/content
 * @version $Id$
 * @license See license.txt
 */

class content_contenttypesapi_htmlPlugin extends contentTypeBase
{
    var $text;
    var $inputType;

    function getModule()
    {
        return 'Content';
    }
    function getName()
    {
        return 'html';
    }
    function getTitle()
    {
        $dom = ZLanguage::getModuleDomain('Content');
        return __('HTML text', $dom);
    }
    function getDescription()
    {
        $dom = ZLanguage::getModuleDomain('Content');
        return __('A rich HTML editor for adding text to your page.', $dom);
    }
    function isTranslatable()
    {
        return true;
    }
    function loadData(&$data)
    {
        if (!isset($data['inputType']))
            $data['inputType'] = 'html';
        if (!ModUtil::available('scribite') && $data['inputType'] == 'html')
            $data['inputType'] = 'text';
        $this->text = $data['text'];
        $this->inputType = $data['inputType'];
    }
    function display()
    {
        $text = DataUtil::formatForDisplayHTML($this->text);
        $text = ModUtil::callHooks('item', 'transform', '', array($text));
        $text = $text[0];
        $view = Zikula_View::getInstance('Content', false);
        $view->assign('inputType', $this->inputType);
        $view->assign('text', $text);

        return $view->fetch('contenttype/paragraph_view.html');
    }
    function displayEditing()
    {
        return $this->display();
    }
    function getDefaultData()
    {
        $dom = ZLanguage::getModuleDomain('Content');
        return array('text' => __('Add text here ...', $dom), 'inputType' => (ModUtil::available('scribite') ? 'html' : 'text'));
    }
    function startEditing(&$view)
    {
        $scripts = array('javascript/ajax/prototype.js', 'javascript/helpers/Zikula.js');
        PageUtil::addVar('javascript', $scripts);
    }
    function getSearchableText()
    {
        return html_entity_decode(strip_tags($this->text));
    }
}

function content_contenttypesapi_html($args)
{
    return new content_contenttypesapi_htmlPlugin();
}