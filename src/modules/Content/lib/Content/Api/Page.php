<?php
/**
 * Content
 *
 * @copyright (C) 2007-2010, Content Development Team
 * @link http://code.zikula.org/content
 * @version $Id: pnpageapi.php 406 2010-06-01 03:04:45Z drak $
 * @license See license.txt
 */

require_once 'modules/Content/common.php';

class Content_Api_Page extends Zikula_Api
{
    /*=[ Fetch pages ]===============================================================*/

    /**
     * Get a single page
     *
     * Calls $this->getPages($args) directly and returns the first page in
     * the returned set of pages. It then adds layout data.
     *
     * @param id int Page ID
     *
     * @return array Page data
     */
    public function getPage($args)
    {
        if (!isset($args['filter']) || !is_array($args['filter']))
            $args['filter'] = array();
        $args['filter']['pageId'] = $args['id'];

        $pages = $this->getPages($args);
        if ($pages === false)
            return false;
        if (count($pages) == 0)
            return LogUtil::registerError($this->__('Error! Unknown page.'), 404);

        $page = $pages[0];

        return $page;
    }

    /**
     * Get a list of pages
     *
     * This function returns an array of pages depending on the various parameters. The most
     * interesting parameter may be "filter" which contains all the restrictions on the list.
     * The filter data is passed to $this->contentGetPageListRestrictions() which is where you
     * will find the documentation.
     *
     * @param filter array See $this->contentGetPageListRestrictions().
     * @param orderBy string Field for "order by" in SQL query
     * @param orderDir string Direction for "order by" in SQL query (desc/asc) default: asc
     * @param orderBy
     * @param pageIndex int Zero based page index for browsing page by page.
     * @param pageSize int Number of pages to show on each "page".
     * @param enableEscape bool Enable HTML escape of returned text data.
     * @param language string Three letter language identifier used for translating content.
     * @param translate bool Enable translation.
     * @param makeTree bool Enable conversion of page list to recursive tree structure.
     * @param includeContent bool Enable inclusion of content items.
     * @param includeCategories bool Enable inclusion of secondary category data.
     * @param includeLanguages bool Enable inclusion of list of translated languages (array('dan','eng')).
     * @param editing bool Passed to content plugins to enable "edit" display (as opposed to normal user display).
     *
     * @return array Array of pages (each of which is an associative array).
     */

    public function getPages($args)
    {
        $filter = isset($args['filter']) ? $args['filter'] : array();
        $orderBy = !empty($args['orderBy']) ? $args['orderBy'] : 'cr_date';
        $orderDir = !empty($args['orderDir']) ? $args['orderDir'] : 'asc';
        $pageIndex = isset($args['pageIndex']) ? $args['pageIndex'] : 0;
        $pageSize = isset($args['pageSize']) ? $args['pageSize'] : 0;
        $enableEscape = (array_key_exists('enableEscape', $args) ? $args['enableEscape'] : true);
        $language = (array_key_exists('language', $args) ? $args['language'] : ZLanguage::getLanguageCode());
        $translate = (array_key_exists('translate', $args) ? $args['translate'] : true);
        $makeTree = (array_key_exists('makeTree', $args) ? $args['makeTree'] : false);
        $includeContent = (array_key_exists('includeContent', $args) ? $args['includeContent'] : false);
        $includeCategories = (array_key_exists('includeCategories', $args) ? $args['includeCategories'] : false);
        $includeVersionNo = (array_key_exists('includeVersionNo', $args) ? $args['includeVersionNo'] : false);
        $editing = (array_key_exists('editing', $args) ? $args['editing'] : false);

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];
        $pageCategoryTable = $dbtables['content_pagecategory'];
        $pageCategoryColumn = $dbtables['content_pagecategory_column'];
        $translatedTable = $dbtables['content_translatedpage'];
        $translatedColumn = $dbtables['content_translatedpage_column'];
        $userTable = $dbtables['users'];
        $userColumn = $dbtables['users_column'];

        $restrictions = array();
        $join = '';

        $this->contentGetPageListRestrictions($filter, $restrictions, $join);

        if (count($restrictions) > 0)
            $where = 'WHERE ' . join(' AND ', $restrictions);
        else
            $where = '';

        if (!empty($orderBy)) {
            $orderBy = ' ORDER BY ' . DataUtil::formatForStore($orderBy);
            $orderBy .= $orderDir == 'desc' ? ' DESC' : ' ASC';
        }

        $language = DataUtil::formatForStore($language);

        $cols = DBUtil::_getAllColumns('content_page');
        $ca = DBUtil::getColumnsArray('content_page');
        $ca[] = 'translatedTitle';
        $ca[] = 'uname';

        $sql = "
SELECT DISTINCT
                $cols,
                $translatedColumn[title],
                $userColumn[uname]
FROM $pageTable
LEFT JOIN $translatedTable t
     ON     t.$translatedColumn[pageId] = $pageColumn[id]
        AND t.$translatedColumn[language] = '$language'
LEFT JOIN $userTable usr
     ON usr.$userColumn[uid] = $pageColumn[lu_uid]
                $join
                $where
                $orderBy";

        //echo "<pre>$sql</pre>";

        if ($pageSize > 0)
            $dbresult = DBUtil::executeSQL($sql, $pageSize * $pageIndex, $pageSize);
        else
            $dbresult = DBUtil::executeSQL($sql);

        $pages = DBUtil::marshallObjects($dbresult, $ca);

        if (isset($filter['expandedPageIds']) && is_array($filter['expandedPageIds']))
            $expandedPageIdsMap = $filter['expandedPageIds'];
        else
            $expandedPageIdsMap = null;

        for ($i = 0, $cou = count($pages); $i < $cou; ++$i) {
            $p = &$pages[$i];
            $p['translated'] = array('title' => $p['translatedTitle']);
            $p['layoutData'] = ModUtil::apiFunc('Content', 'layout', 'getLayout', array('layout' => $p['layout']));
            $p['layoutTemplate'] = 'layout/' . $p['layoutData']['name'] . '.html';
            if ($includeCategories)
                $p['categories'] = $this->contentGetPageCategories($p['id']);
            if ($includeVersionNo)
                $p['versionNo'] = ModUtil::apiFunc('Content', 'history', 'getPageVersionNo', array('pageId' => $p['id']));

            if (!empty($p['translatedTitle'])) {
                if ($translate) {
                    $p = array_merge($p, $p['translated']);
                }
                $p['isTranslated'] = true;
            } else
                $p['isTranslated'] = false;

            if ($enableEscape)
                $this->contentEscapePageData($p);

            if ($includeContent) {
                $content = ModUtil::apiFunc('Content', 'Content', 'getPageContent', array('pageId' => $p['id'], 'editing' => $editing, 'translate' => $translate));
                if ($content === false)
                    return false;
            } else
                $content = null;

            $p['content'] = $content;

            if ($expandedPageIdsMap !== null) {
                if (!empty($expandedPageIdsMap[$p['id']]))
                    $p['isExpanded'] = 1;
                else
                    $p['isExpanded'] = 0;
            }
        }

        if ($makeTree && count($pages) > 0) {
            $i = 0;
            $pages = $this->contentMakePageTree($pages, $i, $pages[0]['level']);
        }

        return $pages;
    }

    /**
     * Count number of pages for a given filter
     *
     * @param filter array See $this->contentGetPageListRestrictions().
     *
     * @return int Page count
     */
    public function getPageCount($args)
    {
        $filter = isset($args['filter']) ? $args['filter'] : array();

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];
        $pageCategoryTable = $dbtables['content_pagecategory'];
        $pageCategoryColumn = $dbtables['content_pagecategory_column'];

        $restrictions = array();
        $join = '';

        $this->contentGetPageListRestrictions($filter, $restrictions, $join);

        if (count($restrictions) > 0)
            $where = 'WHERE ' . join(' AND ', $restrictions);
        else
            $where = '';

        $sql = "
SELECT COUNT(*)
FROM $pageTable
                $join
                $where";

        $count = DBUtil::selectScalar($sql);

        return $count;
    }

    /**
     * Convert filter information to SQL
     *
     * @param filter[category] int Restrict to specific category ID.
     * @param filter[pageId] int Restrict to specific page ID.
     * @param filter[urlname] string Restrict to specific page using the page's permalink name.
     * @param filter[checkActive] bool Enable restricting to only active pages (default true).
     * @param filter[superParentId] int Restrict to pages beneath this ID (includes itself).
     * @param filter[where] string Any SQL to be used in the resulting restriction.
     * @param restrictions array Output array of restrictions (SQL expressions).
     * @param join string Output string with required join statement.
     *
     * @return void
     */
    protected function contentGetPageListRestrictions($filter, &$restrictions, &$join)
    {
        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];
        $pageCategoryTable = $dbtables['content_pagecategory'];
        $pageCategoryColumn = $dbtables['content_pagecategory_column'];

        if (!empty($filter['category'])) {
            $c = (int) $filter['category'];
            $restrictions[] = "($pageCategoryColumn[categoryId] = $c OR $pageColumn[categoryId] = $c)";
            $join .= "LEFT JOIN $pageCategoryTable ON $pageCategoryColumn[pageId] = $pageColumn[id]\n";
        }

        if (!empty($filter['pageId'])) {
            $restrictions[] = "$pageColumn[id] = " . (int) $filter['pageId'];
        }

        if (!empty($filter['urlname'])) {
            $restrictions[] = "$pageColumn[urlname] = '" . DataUtil::formatForStore($filter['urlname']) . "'";
        }

        if (!array_key_exists('checkActive', $filter) || !empty($filter['checkActive'])) {
            $restrictions[] = "$pageColumn[isActive] = 1";
        }

        if (!empty($filter['superParentId'])) {
            $pageData = ModUtil::apiFunc('Content', 'Page', 'getPage', array('id' => $filter['superParentId']));
            if ($pageData === false)
                return false;

            $where = "    $pageData[setLeft] <= $pageColumn[setLeft]
              AND $pageColumn[setRight] <= $pageData[setRight]";

            $restrictions[] = $where;
        }

        if (isset($filter['expandedPageIds']) && is_array($filter['expandedPageIds'])) {
            $pageIdStr = '-1';
            foreach (array_keys($filter['expandedPageIds']) as $pageId)
                $pageIdStr .= ',' . (int) $pageId;

            // Only select pages that do not have a collapsed (not expanded) page above it
            $restriction = "
NOT EXISTS (SELECT 1 FROM $pageTable parentPage
            WHERE     parentPage.$pageColumn[setLeft] < $pageTable.$pageColumn[setLeft]
                  AND $pageTable.$pageColumn[setRight] < parentPage.$pageColumn[setRight]
                  AND parentPage.$pageColumn[id] NOT IN ($pageIdStr))";

            // MySQL 4.x users should remove the line below
            $restrictions[] = $restriction;
        }

        if (!empty($filter['where'])) {
            $restrictions[] = $filter['where'];
        }
    }

    /**
     * Convert linear array of pages to recursive page tree structure.
     */
    protected function contentMakePageTree(&$pages, &$i, $level)
    {
        $newPages = array();

        for ($cou = count($pages); $i < $cou; ++$i) {
            $page = $pages[$i];
            //echo "($page[id]: $level, $i, $page[level]) ";

            if ($page['level'] == $level) {
                //echo "Append ";
                $page['subPages'] = array();
                $newPages[] = $page;
            } else if ($page['level'] > $level) {
                //echo "Sub{ ";
                $newPages[count($newPages) - 1]['subPages'] = $this->contentMakePageTree($pages, $i, $page['level']);
                //echo " } ";
            } else if ($page['level'] < $level) {
                //echo "None ";
                --$i;
                break;
            }
        }

        //echo " |"; dumpTree($newPages); echo "|";
        return $newPages;
    }

    /*
function dumpTree($pages)
{
  foreach ($pages as $p)
  {
    echo "Page: $p[title] ($p[id]) {";
    dumpTree($p['subPages']);
    echo "} ";
  }
}
    */

    protected function contentEscapePageData(&$page)
    {
        $page['title'] = DataUtil::formatForDisplay($page['title']);
    }

    /*=[ New page ]==================================================================*/

    public function newPage($args)
    {
        $pageData = $args['page'];
        $pageId = (int) $args['pageId'];
        $location = $args['location'];

        if ($location == 'sub' && $pageId <= 0)
            return LogUtil::registerError($this->__("Error! Cannot create sub-page without parent page ID"));

        if ($pageId > 0) {
            $sourcePageData = ModUtil::apiFunc('Content', 'Page', 'getPage', array('id' => $pageId, 'enableEscape' => false, 'includeContent' => false));
            if ($sourcePageData === false)
                return false;
        } else
            $sourcePageData = null;

        $pageData['language'] = ZLanguage::getLanguageCode();

        if ($location == 'sub' || $pageId == 0) {
            $pageData['position'] = $this->contentGetLastSubPagePosition($pageId) + 1;
            $pageData['parentPageId'] = $pageId;
            $pageData['level'] = ($sourcePageData == null ? 0 : $sourcePageData['level'] + 1);
        } else {
            $pageData['position'] = $this->contentGetLastPagePosition($pageId) + 1;
            $pageData['parentPageId'] = ($sourcePageData == null ? 0 : $sourcePageData['parentPageId']);
            $pageData['level'] = ($sourcePageData == null ? 0 : $sourcePageData['level']);
        }

        if (!isset($pageData['urlname']) || empty($pageData['urlname']))
            $pageData['urlname'] = $pageData['title'];
        $pageData['urlname'] = DataUtil::formatPermalink(strtolower($pageData['urlname']));

        $ok = ModUtil::apiFunc('Content', 'Page', 'isUniqueUrlnameByParentID', array('urlname' => $pageData['urlname'], 'parentId' => $pageData['parentPageId']));
        if (!$ok)
            return LogUtil::registerError($this->__('Error! There is already another page registered with the supplied permalink URL.'));

        $pageData['setLeft'] = -2;
        $pageData['setRight'] = -1;

        $newPage = DBUtil::insertObject($pageData, 'content_page');
        contentMainEditExpandSet($pageData['parentPageId'], true);

        $ok = $this->insertPage(array('pageId' => $pageData['id'], 'position' => $pageData['position'], 'parentPageId' => $pageData['parentPageId']));
        if ($ok === false)
            return false;

        $ok = ModUtil::apiFunc('Content', 'history', 'addPageVersion', array('pageId' => $pageData['id'], 'action' => '_CONTENT_HISTORYPAGEADDED' /* delayed translation */));
        if ($ok === false)
            return false;

        $this->callHooks('item', 'create', $pageData['id'], array ('module' => 'Content')); 

        contentClearCaches();
        return $pageData['id'];
    }

    /*=[ Update page ]===============================================================*/

    public function updatePage($args)
    {
        $pageData = $args['page'];
        $pageId = (int) $args['pageId'];
        $revisionText = (isset($args['revisionText']) ? $args['revisionText'] : '_CONTENT_HISTORYPAGEUPDATED' /* delayed translation */);

        if (!isset($pageData['urlname']) || empty($pageData['urlname']))
            $pageData['urlname'] = $pageData['title'];
        $pageData['urlname'] = DataUtil::formatPermalink(strtolower($pageData['urlname']));

        if (!ModUtil::apiFunc('Content', 'Page', 'isUniqueUrlnameByPageId', array('urlname' => $pageData['urlname'], 'pageId' => $pageId)))
            return LogUtil::registerError($this->__('Error! There is already another page registered with the supplied permalink URL.'));

        $oldPageData = ModUtil::apiFunc('Content', 'Page', 'getPage', array('id' => $pageId, 'editing' => true, 'filter' => array('checkActive' => false), 'enableEscape' => false));
        if ($oldPageData === false)
            return false;

        if ($oldPageData['layout'] != $pageData['layout'])
            if (!$this->contentUpdateLayout($pageId, $oldPageData['layout'], $pageData['layout']))
                return false;

        if (!$this->contentUpdatePageRelations($pageId, $pageData))
            return LogUtil::registerError($this->__('Error! There is already another page registered with the supplied permalink URL.'));

        $pageData['id'] = $pageId;

        DBUtil::updateObject($pageData, 'content_page');

        $ok = ModUtil::apiFunc('Content', 'history', 'addPageVersion', array('pageId' => $pageId, 'action' => $revisionText));
        if ($ok === false)
            return false;

        $this->callHooks('item', 'update', $pageData['id'], array ('module' => 'Content')); 

        contentClearCaches();
        return true;
    }

// Update layout
    protected function contentUpdateLayout($pageId, $oldLayoutName, $newLayoutName)
    {
        $oldLayout = ModUtil::apiFunc('Content', 'layout', 'getLayoutPlugin', array('layout' => $oldLayoutName));
        $newLayout = ModUtil::apiFunc('Content', 'layout', 'getLayoutPlugin', array('layout' => $newLayoutName));

        $dbtables = DBUtil::getTables();
        $contentTable = $dbtables['content_content'];
        $contentColumn = $dbtables['content_content_column'];

        for ($i = $newLayout->getNumberOfContentAreas(); $i < $oldLayout->getNumberOfContentAreas(); ++$i) {
            $sql = "
SELECT MAX($contentColumn[position])
FROM $contentTable
WHERE $contentColumn[pageId] = $pageId
      AND $contentColumn[areaIndex] = " . ($newLayout->getNumberOfContentAreas() - 1);

            $maxPos = DBUtil::selectScalar($sql);
            if ($maxPos == null)
                $maxPos = -1;

            $sql = "
UPDATE $contentTable SET
                    $contentColumn[areaIndex] = " . ($newLayout->getNumberOfContentAreas() - 1) . ",
                    $contentColumn[position] = $contentColumn[position] + $maxPos + 1
WHERE $contentColumn[pageId] = $pageId
      AND $contentColumn[areaIndex] = $i";
            //echo "UPDATE: $sql\n ";


            DBUtil::executeSQL($sql);
        }

        contentClearCaches();
        return true;
    }

    protected function contentUpdatePageRelations($pageId, $pageData)
    {
        if (isset($pageData['categories'])) {
            $dbtables = DBUtil::getTables();
            $pageCategoryTable = $dbtables['content_pagecategory'];
            $pageCategoryColumn = $dbtables['content_pagecategory_column'];
            $pageId = (int) $pageId;

            $this->contentDeletePageRelations($pageId);
            foreach ($pageData['categories'] as $categoryId) {
                $categoryId = (int) $categoryId;
                $sql = "
INSERT INTO $pageCategoryTable
  ($pageCategoryColumn[pageId], $pageCategoryColumn[categoryId])
VALUES
  ($pageId, $categoryId)";

                DBUtil::executeSQL($sql);
            }
        }

        return true;
    }

    protected function contentDeletePageRelations($pageId)
    {
        $dbtables = DBUtil::getTables();
        $pageCategoryTable = $dbtables['content_pagecategory'];
        $pageCategoryColumn = $dbtables['content_pagecategory_column'];
        $pageId = (int) $pageId;

        $sql = "
DELETE FROM $pageCategoryTable
WHERE $pageCategoryColumn[pageId] = $pageId";

        DBUtil::executeSQL($sql);

        return true;
    }

    protected function contentGetPageCategories($pageId)
    {
        $dbtables = DBUtil::getTables();
        $pageCategoryTable = $dbtables['content_pagecategory'];
        $pageCategoryColumn = $dbtables['content_pagecategory_column'];
        $pageId = (int) $pageId;

        $sql = "
SELECT $pageCategoryColumn[categoryId]
FROM $pageCategoryTable
WHERE $pageCategoryColumn[pageId] = $pageId";

        $dbresult = DBUtil::executeSQL($sql);
        $categories = array();

        for (; !$dbresult->EOF; $dbresult->MoveNext()) {
            $categories[] = (int) $dbresult->fields[0];
        }


        return $categories;
    }

    /*=[ Translate page ]============================================================*/

    public function updateTranslation($args)
    {

        $pageId = (int) $args['pageId'];
        $language = DataUtil::formatForStore($args['language']);
        $translated = $args['translated'];
        $addVersion = isset($args['addVersion']) ? $args['addVersion'] : true;

        $dbtables = DBUtil::getTables();
        $translatedTable = $dbtables['content_translatedpage'];
        $translatedColumn = $dbtables['content_translatedpage_column'];

        // Delete optional existing translation
        $where = "$translatedColumn[pageId] = $pageId AND $translatedColumn[language] = '$language'";
        DBUtil::deleteWhere('content_translatedpage', $where);

        // Insert new
        $translatedData = array('pageId' => $pageId, 'language' => $language, 'title' => $translated['title']);
        DBUtil::insertObject($translatedData, 'content_translatedpage');

        if ($addVersion) {
            $ok = ModUtil::apiFunc('Content', 'history', 'addPageVersion', array('pageId' => $pageId, 'action' => $this->__("Translated") /* delayed translation */));
            if ($ok === false)
                return false;
        }

        contentClearCaches();
        return true;
    }

    public function deleteTranslation($args)
    {
        $pageId = (int) $args['pageId'];
        $language = isset($args['language']) ? $args['language'] : null;
        $addVersion = isset($args['addVersion']) ? $args['addVersion'] : true;

        $dbtables = DBUtil::getTables();
        $translatedColumn = $dbtables['content_translatedpage_column'];

        // Delete existing translation
        if ($language != null)
            $where = "$translatedColumn[pageId] = $pageId AND $translatedColumn[language] = '" . DataUtil::formatForStore($language) . "'";
        else
            $where = "$translatedColumn[pageId] = $pageId";

        DBUtil::deleteWhere('content_translatedpage', $where);

        $ok = ModUtil::apiFunc('Content', 'Content', 'deletePageTranslations', array('pageId' => $pageId, 'language' => $language));
        if ($ok === false)
            return false;

        if ($addVersion) {
            $ok = ModUtil::apiFunc('Content', 'history', 'addPageVersion', array('pageId' => $pageId, 'action' => $this->__("Translation deleted") /* delayed translation */));
            if ($ok === false)
                return false;
        }

        contentClearCaches();
        return true;
    }

    public function getTranslations($args)
    {
        $pageId = (int) $args['pageId'];

        $dbtables = DBUtil::getTables();
        $translatedTable = $dbtables['content_translatedpage'];
        $translatedColumn = $dbtables['content_translatedpage_column'];

        $where = "$translatedColumn[pageId] = $pageId";
        $translations = DBUtil::selectObjectArray('content_translatedpage', $where);

        return $translations;
    }

    /*=[ Delete page ]===============================================================*/

    public function deletePage($args)
    {
        $pageId = (int) $args['pageId'];

        // Delete translations first - they depend on content data
        $ok = ModUtil::apiFunc('Content', 'Page', 'deleteTranslation', array('pageId' => $pageId));
        if (!$ok)
            return false;

        // Delete all content items on this page and all it's sub pages
        $ok = ModUtil::apiFunc('Content', 'Content', 'deletePageAndSubPageContent', array('pageId' => $pageId));
        if (!$ok)
            return false;

        $pageData = DBUtil::selectObjectByID('content_page', $pageId);

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        // Delete by left/right before updating left/right for remaining pages
        // Do not delete "this" in order to "removePage" to work
        $sql = "
DELETE FROM $pageTable
WHERE     $pageColumn[setLeft] > $pageData[setLeft]
      AND $pageColumn[setRight] < $pageData[setRight]";

        DBUtil::executeSQL($sql);

        if (!$this->removePage(array('id' => $pageId)))
            return false;

        $ok = ModUtil::apiFunc('Content', 'history', 'deletePage', array('pageId' => $pageData['id']));
        if ($ok === false)
            return false;

        // Now safe to delete page
        DBUtil::deleteObjectByID('content_page', $pageId);

        $this->contentDeletePageRelations($pageId);

        $this->callHooks('item', 'delete', $pageId, array ('module' => 'content')); 

        contentClearCaches();
        return true;
    }

    /*=[ Helper functions for moving pages ]=========================================*/

    protected function contentGetLastSubPagePosition($pageId)
    {
        $pageId = (int) $pageId;

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $sql = "
SELECT MAX($pageColumn[position])
FROM $pageTable
WHERE $pageColumn[parentPageId] = $pageId";

        $pos = DBUtil::selectScalar($sql);
        return $pos === null ? -1 : (int) $pos;
    }

    protected function contentGetLastPagePosition($pageId)
    {
        $pageId = (int) $pageId;

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $sql = "
SELECT MAX(page.$pageColumn[position])
FROM $pageTable page
JOIN $pageTable orgPage
     ON orgPage.$pageColumn[id] = $pageId
WHERE page.$pageColumn[parentPageId] = orgPage.$pageColumn[parentPageId]";

        $pos = DBUtil::selectScalar($sql);
        return $pos === null ? -1 : (int) $pos;
    }

    public function updateNestedSetValues($args)
    {
        $count = -1;
        $level = -1;

        $dbconn = DBConnectionStack::getConnection();
        $dbtables = DBUtil::getTables();

        $ok = $this->contentUpdateNestedSetValues_Rec(0, $level, $count, $dbconn, $dbtables);

        return $ok;
    }

    protected function contentUpdateNestedSetValues_Rec($pageId, $level, &$count, &$dbconn, &$dbtables)
    {
        $pageId = (int) $pageId;

        $pageTable = $dbtables['content_page'];
        $pageColumn = &$dbtables['content_page_column'];

        $left = $count++;

        $sql = "SELECT $pageColumn[id]
          FROM $pageTable
          WHERE $pageColumn[parentPageId] = $pageId
          ORDER BY $pageColumn[position]";

        $dbresult = DBUtil::executeSQL($sql);

        for (; !$dbresult->EOF; $dbresult->MoveNext()) {
            $subPageId = $dbresult->fields[0];

            $this->contentUpdateNestedSetValues_Rec($subPageId, $level + 1, $count, $dbconn, $dbtables);
        }

        $right = $count++;

        $sql = "UPDATE $pageTable
          SET $pageColumn[setLeft] = $left,
                $pageColumn[setRight] = $right,
                $pageColumn[level] = $level
          WHERE $pageColumn[id] = $pageId";

        DBUtil::executeSQL($sql);

        return true;
    }

    public function drag($args)
    {
        $srcId = (int) $args['srcId'];
        $dstId = (int) $args['dstId'];

        $srcPage = DBUtil::selectObjectByID('content_page', $srcId);
        $dstPage = DBUtil::selectObjectByID('content_page', $dstId);

        // Is $src a parent of $dst? This is not allowed
        if ($srcPage['setLeft'] < $dstPage['setLeft'] && $srcPage['setRight'] > $dstPage['setRight'])
            return LogUtil::registerError($this->__('Error! It is not possible to move a parent page beneath one of its descendants.'));

        $ok = ModUtil::apiFunc('Content', 'Page', 'removePage', array('id' => $srcId));
        if ($ok === false)
            return false;

        DBUtil::flushCache('content_page');

        // Get destination again so we get an updated position after the above "removePage"
        $dstPage = DBUtil::selectObjectByID('content_page', $dstId);
//        $dstPage = DBUtil::selectObjectByID('content_page', $dstId, 'id', null, null, null, false);

        $test = ModUtil::apiFunc('Content', 'Page', 'isUniqueUrlnameByParentID', array('urlname' => $srcPage['urlname'], 'parentId' => $dstPage['parentPageId'], 'currentPageId' => $srcId));
        if (!$test) {
            ModUtil::apiFunc('Content', 'Page', 'insertPage', array('pageId' => $srcId, 'position' => $srcPage['position'], 'parentPageId' => $srcPage['parentPageId']));
            // FIXME: This causes a "page not found". But I don't know why. Pls help ;)
            return LogUtil::registerError($this->__('Error! There is already another page registered with the supplied permalink URL.'));
        }
        $ok = ModUtil::apiFunc('Content', 'Page', 'insertPage', array('pageId' => $srcId, 'position' => $dstPage['position'] + 1, 'parentPageId' => $dstPage['parentPageId']));
        if ($ok === false)
            return false;

        contentClearCaches();
        return true;
    }

    public function increaseIndent($args)
    {
        $pageId = (int) $args['pageId'];

        $page = DBUtil::selectObjectByID('content_page', $pageId);

        // Cannot indent topmost page
        if ($page['position'] == 0)
            return true;

        $parentPageId = $page['parentPageId'];
        $position = $page['position'];

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $where = "
                $pageColumn[parentPageId] = $parentPageId
AND $pageColumn[position] = $position-1";

        $previousPage = DBUtil::selectObject('content_page', $where);

        $thisPage = DBUtil::selectObjectByID('content_page', $pageId);

        $ok = ModUtil::apiFunc('Content', 'Page', 'isUniqueUrlnameByParentID', array('urlname' => $thisPage['urlname'], 'parentId' => $previousPage['id']));
        // FIXME: This causes a "page not found" if $ok == false. But I don't know why. Pls help ;)
        if (!$ok)
            return LogUtil::registerError($this->__('Error! There is already another page registered with the supplied permalink URL.'));

        $ok = ModUtil::apiFunc('Content', 'Page', 'removePage', array('id' => $pageId));
        if ($ok === false)
            return false;

        DBUtil::flushCache('content_page');

        // Find new position (last in existing sub-pages)
        $sql = "
SELECT MAX($pageColumn[position])
FROM $pageTable
WHERE $pageColumn[parentPageId] = $previousPage[id]";

        $newPosition = DBUtil::selectScalar($sql);
        if ($newPosition == null)
            $newPosition = 0;

        $ok = ModUtil::apiFunc('Content', 'Page', 'insertPage', array('pageId' => $pageId, 'position' => $newPosition, 'parentPageId' => $previousPage['id']));
        if ($ok === false)
            return false;
        /*
  $ok = ModUtil::apiFunc('Content', 'Page', 'updateNestedSetValues');
  if ($ok === false)
    return false;
        */
        contentClearCaches();
        return true;
    }

// Remove page from hierarchy, but don't delete it
    public function removePage($args)
    {
        $id = (int) $args['id'];

        $pageData = DBUtil::selectObjectByID('content_page', $id);

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $sql = "
UPDATE $pageTable
SET $pageColumn[position] = $pageColumn[position]-1
WHERE     $pageColumn[parentPageId] = $pageData[parentPageId]
      AND $pageColumn[position] > $pageData[position]";

        DBUtil::executeSQL($sql);

        // Move page and it's subpages out of the left/right system (to avoid later problems)
        $diff = $pageData['setRight'] + 1;
        //echo "diff=$diff. ";
        //var_dump($pageData);
        $sql = "
UPDATE $pageTable SET
                $pageColumn[setLeft] = $pageColumn[setLeft]-$diff,
                $pageColumn[setRight] = $pageColumn[setRight]-$diff
WHERE $pageData[setLeft] <= $pageColumn[setLeft] AND $pageColumn[setRight] <= $pageData[setRight]";

        DBUtil::executeSQL($sql);

        // Update all left/right values for all pages "right of this page"
        $diff = $pageData['setRight'] - $pageData['setLeft'] + 1;

        $sql = "
UPDATE $pageTable SET
                $pageColumn[setLeft] = $pageColumn[setLeft]-$diff
WHERE $pageColumn[setLeft] > $pageData[setRight]";

        DBUtil::executeSQL($sql);

        $sql = "
UPDATE $pageTable SET
                $pageColumn[setRight] = $pageColumn[setRight]-$diff
WHERE $pageColumn[setRight] > $pageData[setRight]";

        DBUtil::executeSQL($sql);

        return true;
    }

// Insert page into hierarchy
    public function insertPage($args)
    {
        $pageId = (int) $args['pageId'];
        $position = (int) $args['position'];
        $parentPageId = (int) $args['parentPageId'];

        $dbtables = DBUtil::getTables();
        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $sql = "
UPDATE $pageTable
SET $pageColumn[position] = $pageColumn[position]+1
WHERE     $pageColumn[parentPageId] = $parentPageId
      AND $pageColumn[position] >= $position";

        DBUtil::executeSQL($sql);

        // *** Update all left/right values for all pages "right of this page"
        // Assume "this page" has left/right values that matches it's subtree (but with a wrong offset)


        $pageData = DBUtil::selectObjectByID('content_page', $pageId);
        if ($parentPageId > 0) {
            $parentPageData = DBUtil::selectObjectByID('content_page', $parentPageId);
            $parentLevel = $parentPageData['level'];
        } else {
            $parentPageData = null;
            $parentLevel = -1;
        }

        // Fetch largest left/right value left of this page's new position
        $sql = "
SELECT MAX($pageColumn[setRight])
FROM $pageTable
WHERE     $pageColumn[parentPageId] = $parentPageId
      AND $pageColumn[position] < $position
      AND $pageColumn[id] != $pageId
                ";

        $maxLeftOfthis = DBUtil::selectScalar($sql);
        if (empty($maxLeftOfthis))
            $maxLeftOfthis = -1;

        if ($parentPageData != null && $parentPageData['setLeft'] > $maxLeftOfthis)
            $maxLeftOfthis = $parentPageData['setLeft'];

        //echo "maxLeftOfthis=$maxLeftOfthis. ";
        $diff = $pageData['setRight'] - $pageData['setLeft'] + 1;
        //var_dump($pageData);
        //echo "diff=$diff. ";
        $sql = "
UPDATE $pageTable SET
                $pageColumn[setRight] = $pageColumn[setRight]+$diff
WHERE $pageColumn[setRight] > $maxLeftOfthis AND $pageColumn[id] != $pageId";

        DBUtil::executeSQL($sql);

        $sql = "
UPDATE $pageTable SET
                $pageColumn[setLeft] = $pageColumn[setLeft]+$diff
WHERE $pageColumn[setLeft] > $maxLeftOfthis AND $pageColumn[id] != $pageId";

        DBUtil::executeSQL($sql);

        // *** Update level/left/right values for this page and all pages below


        $levelDiff = $pageData['level'] - ($parentLevel + 1);
        $diff2 = $pageData['setLeft'] - $maxLeftOfthis - 1;
        //echo "diff2=$diff2. ";
        $sql = "
UPDATE $pageTable SET
                $pageColumn[setLeft] = $pageColumn[setLeft]-$diff2,
                $pageColumn[setRight] = $pageColumn[setRight]-$diff2,
                $pageColumn[level] = $pageColumn[level]-$levelDiff
WHERE $pageData[setLeft] <= $pageColumn[setLeft] AND $pageColumn[setRight] <= $pageData[setRight]";

        DBUtil::executeSQL($sql);

        // Update this page
        $newPageData = array('id' => $pageId, 'position' => $position, 'parentPageId' => $parentPageId, 'level' => ($parentPageData == null ? 0 : $parentPageData['level'] + 1));
        DBUtil::updateObject($newPageData, 'content_page');

        return true;
    }

    /*=[ Utility functions ]=========================================================*/

    /**
     * Test if the permalink url is already exists by urlname and the page ID of its parent
     *
     * @author Philipp Niethammer <webmaster@nochwer.de>
     * @param string    urlname
     * @param int       parentId
     * @return bool
     */
    public function isUniqueUrlnameByParentID($args)
    {
        if (!isset($args['urlname']) || empty($args['urlname']) || !isset($args['parentId']))
            return LogUtil::registerArgsError();

        $currentPageId = isset($args['currentPageId']) ? (int) $args['currentPageId'] : -1;
        $url = $args['urlname'];

        if ($args['parentId'] > 0) {
            $parenturl = ModUtil::apiFunc('Content', 'Page', 'getUrlPath', array('pageId' => $args['parentId']));
            $url = $parenturl . '/' . $url;
        }

        $pageId = ModUtil::apiFunc('Content', 'Page', 'solveURLPath', array('urlname' => $url));

        // It is unique if no other page exists OR the found page is the same as we are testing from
        if ($pageId == false || $pageId == $currentPageId)
            return true;

        return false;
    }

    /**
     * Test if the permlink is unique with help of its pageId
     *
     * @param string urlname
     * @param string pageId
     * @return bool
     */
    public function isUniqueUrlnameByPageId($args)
    {
        // Argument check
        if (!isset($args['urlname']) || empty($args['urlname']) || !isset($args['pageId']) || empty($args['pageId']))
            return LogUtil::registerArgsError();

        $page = ModUtil::apiFunc('Content', 'Page', 'getPage', array('id' => $args['pageId'], 'includeContent' => false));

        $parenturl = ModUtil::apiFunc('Content', 'Page', 'getUrlPath', array('pageId' => $page['parentPageId']));

        $url = $parenturl . '/' . $args['urlname'];

        $pageId = ModUtil::apiFunc('Content', 'Page', 'solveURLPath', array('urlname' => $url));

        if ($pageId == false || $pageId == $args['pageId'])
            return true;

        return false;
    }

    /**
     * get page path for url
     *
     * @author Philipp Niethammer <webmaster@nochwer.de>
     *
     * @param int   pageId
     * @return string page path
     */
    public function getURLPath($args)
    {
        // Argument check
        if (!isset($args['pageId']))
            return LogUtil::registerArgsError();

        $pageId = (int) $args['pageId'];

        $dbtables = DBUtil::getTables();

        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $sql = "SELECT parentPage.$pageColumn[urlname]
          FROM $pageTable parentPage
          LEFT OUTER JOIN $pageTable page
               ON     page.$pageColumn[setLeft] >= parentPage.$pageColumn[setLeft]
                  AND page.$pageColumn[setRight] <= parentPage.$pageColumn[setRight]
          WHERE page.$pageColumn[id] = $pageId
          ORDER BY parentPage.$pageColumn[setLeft]";

        $result = DBUtil::executeSQL($sql);

        if (!$result)
            return LogUtil::registerError($this->__('Error! Could not load items.'));

        if ($result->EOF)
            return false;

        $path = '';
        for (; !$result->EOF; $result->MoveNext()) {
            if (!empty($path)) {
                $path .= '/';
            }
            $path .= $result->fields[0];
        }

        return $path;
    }

    /**
     * Solve url path to pageID
     *
     * XXX not very pretty, but it works. Please replace if you have a better solution.
     *
     * @param string urlname
     * @return int pageID
     */
    public function solveURLPath($args)
    {
        // Argument check
        if (!isset($args['urlname']))
            return LogUtil::registerArgsError();

        $urlname = $args['urlname'];

        // Remove trailing slash
        if (substr($urlname, -1) == '/')
            $urlname = substr($urlname, 0, -1);
        $parts = explode('/', $urlname);

        $dbtables = DBUtil::getTables();

        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $tables = array();
        $parent = array();
        for ($i = 0, $count = count($parts); $i < $count; $i++) {
            $tables[] = $pageTable . " tbl$i";
            $url = DataUtil::formatForStore($parts[$i]);
            $urlRestriction = "tbl$i.$pageColumn[urlname] = '$url'";
            if ($i < $count - 1)
                $parent[] = $urlRestriction . " AND tbl$i.$pageColumn[id] = tbl" . ($i + 1) . ".$pageColumn[parentPageId]";
            else
                $parent[] = $urlRestriction;
        }
        $tablesql = implode(",\n", $tables);
        $parentsql = implode("\nAND ", $parent);

        $lastelement = $count - 1;
        $sql = "SELECT tbl$lastelement.$pageColumn[id]
          FROM $tablesql
          WHERE tbl0.$pageColumn[parentPageId] = 0
          AND $parentsql";

        $result = DBUtil::executeSQL($sql);
        $pageId = null;
        for (; !$result->EOF; $result->MoveNext()) {
            $pageId = reset($result->fields);
        }
/*
        $result = DBUtil::executeSQL($sql);
        $objectArray = DBUtil::marshallObjects($result);
        $pageId = null;
        foreach ($objectArray as $object) {
            $pageId = reset($object);
        }
*/        
        return $pageId;
    }

    public function getPagePath($args)
    {
        // Argument check
        if (!isset($args['pageId']))
            return LogUtil::registerArgsError();

        $pageId = (int) $args['pageId'];

        $dbtables = DBUtil::getTables();

        $pageTable = $dbtables['content_page'];
        $pageColumn = $dbtables['content_page_column'];

        $sql = "SELECT parentPage.$pageColumn[id],
                 parentPage.$pageColumn[title]
          FROM $pageTable parentPage
          LEFT OUTER JOIN $pageTable page
               ON     page.$pageColumn[setLeft] >= parentPage.$pageColumn[setLeft]
                  AND page.$pageColumn[setRight] <= parentPage.$pageColumn[setRight]
          WHERE page.$pageColumn[id] = $pageId
          ORDER BY parentPage.$pageColumn[setLeft]";

        $result = DBUtil::executeSQL($sql);
        if (!$result) {
            return LogUtil::registerError($this->__('Error! Could not load items.'));
        }
        $path = array();
        for (; !$result->EOF; $result->MoveNext()) {
            $path[] = array('id' => $result->fields[0], 'title' => DataUtil::formatForDisplay($result->fields[1]));
        }

/*
        $result = DBUtil::executeSQL($sql);
        if (!$result) {
            return LogUtil::registerError($this->__('Error! Could not load items.'));
        }
        $objectArray = DBUtil::marshallObjects($result);
        $path = array();
        foreach ($objectArray as $object) {
            $path[] = array('id' => $object[0], 'title' => DataUtil::formatForDisplay($object[1]));
        }
*/        
        return $path;
    }
}