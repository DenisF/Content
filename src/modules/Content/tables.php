<?php
/**
 * Content
 *
 * @copyright (C) 2007-2010, Content Development Team
 * @link http://code.zikula.org/content
 * @version $Id$
 * @license See license.txt
 */


function content_tables()
{
    $tables = array();

    // Page setup (pages can be nested beneath each other)
    $tables['content_page'] = DBUtil::getLimitedTablename('content_page');

    $tables['content_page_column'] = array(
        'id'            => 'page_id',         // Page ID
        'parentPageId'  => 'page_ppid',       // Parent (containing) page ID
        'title'         => 'page_title',      // Display title for this page
        'urlname'       => 'page_urlname',    // URL name for this page
        'layout'        => 'page_layout',     // Name of page layout
        'categoryId'    => 'page_categoryid', // Primary category ID
        'active'        => 'page_active',     // Bool flag: active or not?
        'activeFrom'    => 'page_activefrom', // Date - publish start
        'activeTo'      => 'page_activeto',   // Date - publish end
        'isActive'      => 'page_isactive',
        'inMenu'        => 'page_inmenu',     // Bool flag: include in menu?
        'isInMenu'      => 'page_isinmenu',
        'position'      => 'page_pos',        // Position inside current level of pages (sorting order)
        'level'         => 'page_level',      // Nested set level
        'setLeft'       => 'page_setleft',    // Nested set left
        'setRight'      => 'page_setright',   // Nested set right
        'language'      => 'page_language'    // Language of initial version
    );

    $tables['content_page_column_def'] = array(
        'id'            => "I NOTNULL AUTO PRIMARY",
        'parentPageId'  => "I NOTNULL DEFAULT 0",
        'title'         => "C(255) NOTNULL DEFAULT ''",
        'urlname'       => "C(255) NOTNULL DEFAULT ''",
        'layout'        => "C(100) NOTNULL",
        'categoryId'    => "I NOT NULL DEFAULT 0",
        'active'        => "I1 NOTNULL DEFAULT 1",
        'activeFrom'    => "T",
        'activeTo'      => "T",
        'isActive'      => "I1 NOTNULL DEFAULT 1",
        'inMenu'        => "I1 NOTNULL DEFAULT 1",
        'isInMenu'      => "I1 NOTNULL DEFAULT 1",
        'position'      => 'I NOTNULL DEFAULT 0',
        'level'         => 'I NOTNULL DEFAULT 0',
        'setLeft'       => 'I NOTNULL DEFAULT 0',
        'setRight'      => 'I NOTNULL DEFAULT 0',
        'language'      => 'C(10)'
    );

    $tables['content_page_primary_key_column'] = 'id';

    // add standard data fields
    ObjectUtil::addStandardFieldsToTableDefinition ($tables['content_page_column'], 'page_');
    ObjectUtil::addStandardFieldsToTableDataDefinition($tables['content_page_column_def']);

    // additional indexes
    $tables['content_page_column_idx'] = array('parentPageId' => 'parentPageId', 
                                               'active'       => 'active' , 
                                               'inMenu'       => 'inMenu', 
                                               'position'     => 'position',
                                               'categoryId'   => 'categoryId');


    // Content setup (multiple content items on each page)
    $tables['content_content'] = DBUtil::getLimitedTablename('content_content');

    $tables['content_content_column'] = array(
        'id'              => 'con_id',          // Content item ID
        'pageId'          => 'con_pageid',      // Reference to owner page ID
        'areaIndex'       => 'con_areaindex',   // Content area index
        'position'        => 'con_position',    // Position inside content area
        'module'          => 'con_module',      // Content module
        'type'            => 'con_type',        // Content type (depending on module)
        'data'            => 'con_data',        // Data from the content providing module
        'stylePosition'   => 'con_stylepos',    // Styled floating position
        'styleWidth'      => 'con_stylewidth',  // Styled width
        'styleClass'      => 'con_styleclass'   // Styled CSS class
    );

    $tables['content_content_column_def'] = array(
        'id'              => "I NOTNULL AUTO PRIMARY",
        'pageId'          => "I NOTNULL DEFAULT 0",
        'areaIndex'       => "I NOTNULL DEFAULT 0",
        'position'        => "I NOTNULL DEFAULT 0",
        'module'          => "C(100) NOTNULL DEFAULT ''",
        'type'            => "C(100) NOTNULL DEFAULT ''",
        'data'            => "X",
        'stylePosition'   => "C(20) NOTNULL DEFAULT 'none'",
        'styleWidth'      => "C(20) NOTNULL DEFAULT 'wauto'",
        'styleClass'      => "C(100) NOTNULL DEFAULT ''"
    );

    $tables['content_content_primary_key_column'] = 'id';

    // add standard data fields
    ObjectUtil::addStandardFieldsToTableDefinition ($tables['content_content_column'], 'con_');
    ObjectUtil::addStandardFieldsToTableDataDefinition($tables['content_content_column_def']);

    // additional indexes
    $tables['content_content_column_idx'] = array('pageId'    => 'pageId', 
                                                  'areaIndex' => 'areaIndex');


    // Multiple category relation
    $tables['content_pagecategory'] = DBUtil::getLimitedTablename('content_pagecategory');

    $tables['content_pagecategory_column'] = array(
        'pageId'     => 'con_pageid',       // Related page ID
        'categoryId' => 'con_categoryid'    // Related category ID
    );

    $tables['content_pagecategory_column_def'] = array(
        'pageId'     => 'I NOTNULL DEFAULT 0',
        'categoryId' => 'I NOTNULL DEFAULT 0'
    );


    // Searchable text from content plugins
    $tables['content_searchable'] = DBUtil::getLimitedTablename('content_searchable');

    $tables['content_searchable_column'] = array(
        'contentId' => 'search_cid',    // Content ID
        'text'      => 'search_text'    // Content searchable text
    );

    $tables['content_searchable_column_def'] = array(
        'contentId' => 'I NOTNULL AUTO PRIMARY',
        'text'      => 'X'
    );

    $tables['content_searchable_primary_key_column'] = 'contentId';

  
    // Translated pages
    $tables['content_translatedpage'] = DBUtil::getLimitedTablename('content_translatedpage');
    
    $tables['content_translatedpage_column'] = array(
        'pageId'    => 'transp_pid',      // Page ID
        'language'  => 'transp_lang',     // Translated to language
        'title'     => 'transp_title'     // Translated title
    );

    $tables['content_translatedpage_column_def'] = array(
        'pageId'    => 'I NOTNULL DEFAULT 0',
        'language'  => 'C(10) NOTNULL',
        'title'     => 'C(255) NOTNULL'
    );

    ObjectUtil::addStandardFieldsToTableDefinition ($tables['content_translatedpage_column'], 'transp_');
    ObjectUtil::addStandardFieldsToTableDataDefinition($tables['content_translatedpage_column_def']);

    // additional indexes
    $tables['content_translatedpage_column_idx'] = array('entry' => array('pageId', 'language'));


    // Translated content plugins
    $tables['content_translatedcontent'] = DBUtil::getLimitedTablename('content_translatedcontent');

    $tables['content_translatedcontent_column'] = array(
        'contentId' => 'transc_cid',     // Content ID
        'language'  => 'transc_lang',    // Translated to language
        'data'      => 'transc_data'    // Translated content
    );

    $tables['content_translatedcontent_column_def'] = array(
        'contentId' => 'I NOTNULL DEFAULT 0',
        'language'  => 'C(10) NOTNULL',
        'data'      => 'X'
    );

    ObjectUtil::addStandardFieldsToTableDefinition ($tables['content_translatedcontent_column'], 'transc_');
    ObjectUtil::addStandardFieldsToTableDataDefinition($tables['content_translatedcontent_column_def']);

    // additional indexes
    $tables['content_translatedcontent_column_idx'] = array('entry' => array('contentId', 'language'));


    // History
    $tables['content_history'] = DBUtil::getLimitedTablename('content_history');

    $tables['content_history_column'] = array(
        'id'            => 'ch_id',
        'pageId'        => 'ch_pageid',
        'data'          => 'ch_data',
        'revisionNo'    => 'ch_revisionno',
        'action'        => 'ch_action',
        'date'          => 'ch_date',
        'ipno'          => 'ch_ipno',
        'userId'        => 'ch_userid'
    );

    $tables['content_history_column_def'] = array(
        'id'           => "I NOTNULL AUTO PRIMARY",
        'pageId'       => "I NOTNULL DEFAULT 0",
        'data'         => "XL NOTNULL DEFAULT ''",
        'revisionNo'   => "I NOTNULL DEFAULT 0",
        'action'       => "C(255) NOTNULL DEFAULT ''",
        'date'         => "T NOTNULL DEFAULT ''",
        'ipno'         => "C(30) NOTNULL DEFAULT ''",
        'userId'       => "I NOTNULL DEFAULT 0"
    );

    // additional indexes
    $tables['content_history_column_idx'] = array('entry' => array('pageId', 'revisionNo'));

    return $tables;
}
