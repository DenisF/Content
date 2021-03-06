<?php

class Content_Form_Handler_Edit_NewPage extends Form_Handler
{
    var $pageId; // Parent or previous page ID or null for new top page
    var $location; // Create 'sub' page or next page (at same level)

    function __construct($args)
    {
        $this->args = $args;
    }

    function initialize($view)
    {
        $dom = ZLanguage::getModuleDomain('Content');
        $this->pageId = FormUtil::getPassedValue('pid', isset($this->args['pid']) ? $this->args['pid'] : null);
        $this->location = FormUtil::getPassedValue('loc', isset($this->args['loc']) ? $this->args['loc'] : null);

        if (!contentHasPageCreateAccess()) {
            return $view->registerError(LogUtil::registerPermissionError());
        }

        // Only allow subpages if edit access on parent page
        if (!contentHasPageEditAccess($this->pageId)) {
            return LogUtil::registerPermissionError();
        }

        if ($this->pageId != null) {
            $page = ModUtil::apiFunc('Content', 'Page', 'getPage', array('id' => $this->pageId, 'includeContent' => false));
            if ($page === false) {
                return $view->registerError(null);
            }
        } else {
            $page = null;
        }

        $layouts = ModUtil::apiFunc('Content', 'layout', 'getLayouts');
        if ($layouts === false) {
            return $view->registerError(null);
        }

        PageUtil::setVar('title', __('Add new page', $dom));

        $view->assign('layouts', $layouts);
        $view->assign('page', $page);
        $view->assign('location', $this->location);
        if ($this->location == 'sub') {
            $view->assign('locationLabel', __('Located below:', $dom));
        } else {
            $view->assign('locationLabel', __('Located after:', $dom));
        }
        contentAddAccess($view, $this->pageId);

        return true;
    }

    function handleCommand($view, &$args)
    {
        if (!contentHasPageCreateAccess())
            return $view->setErrorMsg(__('Error! You have not been granted access to this page.', $dom));

        if ($args['commandName'] == 'create') {
            if (!$view->isValid()) {
                return false;
            }
            $pageData = $view->getValues();
            $id = ModUtil::apiFunc('Content', 'Page', 'newPage', array('page' => $pageData, 'pageId' => $this->pageId, 'location' => $this->location));
            if ($id === false) {
                return false;
            }
            $url = ModUtil::url('Content', 'edit', 'editpage', array('pid' => $id));
        } else if ($args['commandName'] == 'cancel') {
            $id = null;
            $url = ModUtil::url('Content', 'edit', 'main');
        }

        return $view->redirect($url);
    }
}
