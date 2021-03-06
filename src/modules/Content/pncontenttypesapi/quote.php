<?php
/**
 * Content quote plugin
 *
 * @copyright (C) 2007-2010, Content Development Team
 * @link http://code.zikula.org/content
 * @version $Id$
 * @license See license.txt
 */

class content_contenttypesapi_quotePlugin extends contentTypeBase
{
    var $text;
    var $inputType;

    function getModule()
    {
        return 'Content';
    }
    function getName()
    {
        return 'quote';
    }
    function getTitle()
    {
        $dom = ZLanguage::getModuleDomain('Content');
        return __('Quote', $dom);
    }
    function getDescription()
    {
        $dom = ZLanguage::getModuleDomain('Content');
        return __('A highlighted quote with source.', $dom);
    }
    function isTranslatable()
    {
        return true;
    }
    function loadData(&$data)
    {
        $this->text = $data['text'];
        $this->source = $data['source'];
        $this->desc = $data['desc'];
    }
    function display()
    {
        $text = DataUtil::formatForDisplayHTML($this->text);
        $source = DataUtil::formatForDisplayHTML($this->source);
        $desc = DataUtil::formatForDisplayHTML($this->desc);

        $text = ModUtil::callHooks('item', 'transform', '', array($text));
        $text = $text[0];

        $view = Zikula_View::getInstance('Content', false);
        $view->assign('source', $source);
        $view->assign('text', $text);
        $view->assign('desc', $desc);

        return $view->fetch('contenttype/quote_view.html');
    }
    function displayEditing()
    {
        $text = DataUtil::formatForDisplayHTML($this->text);
        $source = DataUtil::formatForDisplayHTML($this->source);
        $desc = DataUtil::formatForDisplayHTML($this->desc);

        $text = ModUtil::callHooks('item', 'transform', '', array($text));
        $text = trim($text[0]);

        $text = '<div class="content-quote"><blockquote>' . $text . '</blockquote><p>-- ' . $desc . '</p></div>';

        return $text;
    }
    function getDefaultData()
    {
        $dom = ZLanguage::getModuleDomain('Content');
        return array('text' => __('Add quote text here...', $dom), 'source' => 'http://', 'desc' => __('Name of the Source', $dom));
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

function content_contenttypesapi_quote($args)
{
    return new content_contenttypesapi_quotePlugin();
}