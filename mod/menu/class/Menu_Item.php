<?php
/**
 * Object class for a menu
 * @author Matthew McNaney <mcnaney at gmail dot com>
 * @version $Id$
 */

PHPWS_Core::initModClass('menu', 'Menu_Link.php');

define('MENU_MISSING_TPL', -2);

class Menu_Item {
    var $id         = 0;
    var $title      = NULL;
    var $template   = NULL;
    var $pin_all    = 0;
    var $_db        = NULL;
    var $_show_all  = false;
    var $_style     = null;
    var $_error     = NULL;

    function Menu_Item($id=NULL)
    {
        if (empty($id)) {
            return;
        }

        $this->id = (int)$id;
        $result = $this->init();
        $this->resetdb();
        if (PEAR::isError($result)) {
            $this->_error = $result;
            PHPWS_Error::log($result);
        }
    }

    function resetdb()
    {
        if (isset($this->_db)) {
            $this->_db->reset();
        } else {
            $this->_db = new PHPWS_DB('menus');
        }
    }

    function init()
    {
        if (!isset($this->id)) {
            return FALSE;
        }

        $this->resetdb();
        $result = $this->_db->loadObject($this);
        if (PEAR::isError($result)) {
            return $result;
        }
    }

    function getTitle()
    {
        $vars['site_map'] = $this->id;
        
        return PHPWS_Text::moduleLink($this->title, 'menu', $vars);
    }

    function setTitle($title)
    {
        $this->title = strip_tags($title);
    }

    function setTemplate($template)
    {
        $this->template = $template;
    }

    function setPinAll($pin)
    {
        $this->pin_all = (bool)$pin;
    }
    

    function getTemplateList()
    {
        $result = PHPWS_File::listDirectories(PHPWS_Template::getTemplateDirectory('menu') . 'menu_layout/');
        if (PHPWS_Error::logIfError($result) || empty($result)) {
            return null;
        }
        
        foreach  ($result as $dir) {
            $directories[$dir] = $dir;
        }

        return $directories;
    }

    function post()
    {
        if (empty($_POST['title'])) {
            $errors[] = dgettext('menu', 'Missing menu title.');
        } else {
            $this->setTitle($_POST['title']);
        }

        $this->setTemplate($_POST['template']);

        if (isset($_POST['pin_all'])) {
            $this->setPinAll(1);
        } else {
            $this->setPinAll(0);
        }

        if (isset($errors)) {
            return $errors;
        } else {
            $result = $this->save();
            if (PEAR::isError($result)) {
                PHPWS_Error::log($result);
                return array(dgettext('menu', 'Unable to save menu. Please check error logs.'));
            }
            return TRUE;
        }
    }

    function save()
    {
        if (empty($this->title)) {
            return FALSE;
        }

        $this->resetdb();
        $result = $this->_db->saveObject($this);
        if (PEAR::isError($result)) {
            return $result;
        }

        if (PHPWS_Settings::get('menu', 'home_link')) {
            $link = new Menu_Link;
            $link->menu_id = $this->id;
            $link->title   = dgettext('menu', 'Home');
            $link->url     = 'index.php';
            $link->key_id  = 0;
            PHPWS_Error::logIfError($link->save());
        }

        return true;
    }

    /**
     * Returns all the links in a menu for display
     */
    function displayLinks($edit=FALSE)
    {
        $all_links = $this->getLinks();
        if (empty($all_links)) {
            return NULL;
        }

        foreach ($all_links as $link) {
            if($i = $link->view()) {
                $link_list[] = $i;
            }
        }
        
        return implode("\n", $link_list);
    }

    /**
     * Returns the menu link objects associated to a menu
     */
    function getLinks($parent=0, $active_only=TRUE)
    {
        $final = NULL;

        // If we have been here already, return the data
        if (isset($GLOBALS['MENU_LINKS'][$this->id])) {
            return $GLOBALS['MENU_LINKS'][$this->id];
        }

        if (!$this->id) {
            return NULL;
        }

        $db = new PHPWS_DB('menu_links');
        $db->addWhere('menu_id', $this->id, NULL, NULL, 1);
        $db->addWhere('parent', $parent, NULL, NULL, 1);

        Key::restrictView($db);
        $db->addOrder('link_order');

        $db->setIndexBy('id');
        $result = $db->getObjects('menu_link');

        if (empty($result)) {
            return NULL;
        }

        foreach ($result as $link) {
            $link->loadChildren();
            $link->_menu = & $this;
            $final[$link->id] = $link;
        }

        $GLOBALS['MENU_LINKS'][$this->id] = $final;

        return $final;
    }



    function getRowTags()
    {
        $vars['menu_id'] = $this->id;
        $vars['command'] = 'edit_menu';
        $links[] = PHPWS_Text::secureLink(dgettext('menu', 'Edit'), 'menu', $vars);

        if (!isset($_SESSION['Menu_Clip']) || 
            !isset($_SESSION['Menu_Clip'][$this->id])) {
            $vars['command'] = 'clip';
            $links[] = PHPWS_Text::secureLink(dgettext('menu', 'Clip'), 'menu', $vars);
        } else {
            $vars['command'] = 'unclip';
            $links[] = PHPWS_Text::secureLink(dgettext('menu', 'Unclip'), 'menu', $vars);
        }

        $vars['command'] = 'pin_all';
        if ($this->pin_all == 0) {
            $link_title = dgettext('menu', 'Pin');
            $vars['hook'] = 1;
        } else {
            $link_title = dgettext('menu', 'Unpin');
            $vars['hook'] = 0;
        }
        $links[] = PHPWS_Text::secureLink($link_title, 'menu', $vars);
        unset($vars['hook']);

        $vars['command'] = 'delete_menu';
        $js['QUESTION'] = dgettext('menu', 'Are you sure you want to delete this menu and all its links.');
        $js['ADDRESS']  = PHPWS_Text::linkAddress('menu', $vars, TRUE);
        $js['LINK'] = dgettext('menu', 'Delete');
        $links[] = javascript('confirm', $js);

        $links[] = PHPWS_Text::secureLink(dgettext('menu', 'Reorder links'), 'menu',
                                          array('command'=>'reorder_links',
                                                'menu_id'=>$this->id));


        $tpl['ACTION'] = implode(' | ', $links);
        return $tpl;
    }

    function kill()
    {
        $db = new PHPWS_DB('menu_assoc');
        $db->addWhere('menu_id', $this->id);
        $db->delete();

        $db->setTable('menu_links');
        $db->delete();

        $db->setTable('menus');
        $db->reset();
        $db->addWhere('id', $this->id);
        $db->delete();

        Layout::purgeBox('menu_' . $this->id);
    }

    function addRawLink($title, $url, $parent=0)
    {
        if (empty($title) || empty($url)) {
            return FALSE;
        }

        $link = new Menu_Link;
        $link->key_id = 0;
        $link->setMenuId($this->id);
        $link->setTitle($title);

        $link->setUrl($url);
        $link->setParent($parent);

        return $link->save();
    }

    function addLink($key_id, $parent=0)
    {
        $key = new Key($key_id);
        $link = new Menu_Link;

        $link->setMenuId($this->id);
        $link->setKeyId($key->id);
        $link->setTitle($key->title);
        $link->url = &$key->url;
        $link->setParent($parent);

        return $link->save();
    }

    /**
     * This link lets you add a stored link to the menu
     */
    function getPinLink($menu_id, $link_id=0, $title=false)
    {
        if (!isset($_SESSION['Menu_Pin_Links'])) {
            return null;
        }

        $vars['command'] = 'pick_link';
        $vars['menu_id'] = $menu_id;
        if ($link_id) {
            $vars['link_id'] = $link_id;
        }
        
        $js['width']   = '300';
        $js['height']  = '100';

        $js['address'] = PHPWS_Text::linkAddress('menu', $vars, true);
        if ($title) {
            $js['label'] = sprintf('%s %s', MENU_PIN_LINK, dgettext('menu', 'Add stored page'));
        } else {
            $js['label'] = MENU_PIN_LINK;
        }

        return javascript('open_window', $js);
    }


    function parseIni()
    {
        $inifile = PHPWS_Template::getTemplateDirectory('menu') . 'menu_layout/' . $this->template . '/options.ini';
        if (!is_file($inifile)) {
            return;
        }

        $results = parse_ini_file($inifile);
        if (!empty($results['show_all'])) {
            $this->_show_all = (bool)$results['show_all'];;
        }

        if (!empty($results['style_sheet'])) {
            $this->_style = $results['style_sheet'];
        }
        
    }


    /**
     * Returns a menu and its links for display
     */
    function view($pin_mode=FALSE)
    {
        $key = Key::getCurrent();

        if ($pin_mode && $key->isDummy(true)) {
            return;
        }

        $tpl_dir = PHPWS_Template::getTemplateDirectory('menu');
        $edit = FALSE;
        $file = 'menu_layout/' . $this->template . '/menu.tpl';

        if (!is_file($tpl_dir . $file)) {
            PHPWS_Error::log(MENU_MISSING_TPL, 'menu', 'Menu_Item::view', $tpl_dir . $file);
            return false;
        }

        $this->parseIni();

        if ($this->_style) {
            $style = sprintf('menu_layout/%s/%s', $this->template, $this->_style);
            Layout::addStyle('menu', $style);
        }
        
        $admin_link = !PHPWS_Settings::get('menu', 'miniadmin');

        $content_var = 'menu_' . $this->id;

        if ( !$pin_mode && Current_User::allow('menu') ) {
            if (Menu::isAdminMode()) {
                if(!isset($_REQUEST['authkey'])) {
                    $pinvars['command'] = 'pin_page';
                    if ($key) {
                        if ($key->isDummy()) {
                            $pinvars['ltitle'] = urlencode($key->title);
                            $pinvars['lurl'] = urlencode($key->url);
                        } else {
                            $pinvars['key_id'] = $key->id;
                        }
                    } else {
                        $pinvars['lurl'] = urlencode(PHPWS_Core::getCurrentUrl());
                    }
                    
                    $js['address'] = PHPWS_Text::linkAddress('menu', $pinvars);
                    $js['label']   = dgettext('menu', 'Pin page');
                    $js['width']   = 300;
                    $js['height']  = 180;
                    $tpl['PIN_PAGE'] = javascript('open_window', $js);
                }

                $tpl['ADD_LINK'] = Menu::getAddLink($this->id);
                $tpl['ADD_SITE_LINK'] = Menu::getSiteLink($this->id, 0, isset($key));

                if (!empty($key)) {
                    $tpl['CLIP'] = Menu::getUnpinLink($this->id, $key->id, $this->pin_all);
                } else {
                    $tpl['CLIP'] = Menu::getUnpinLink($this->id, -1, $this->pin_all);
                }

                if ($admin_link) {
                    $vars['command'] = 'disable_admin_mode';
                    $vars['return'] = 1;
                    $tpl['ADMIN_LINK'] = PHPWS_Text::moduleLink(MENU_ADMIN_OFF, 'menu', $vars);
                }

                if (isset($_SESSION['Menu_Pin_Links'])) {
                    $tpl['PIN_LINK'] = $this->getPinLink($this->id);
                }
            } elseif ($admin_link) {
                $vars['command'] = 'enable_admin_mode';
                $vars['return'] = 1;
                $tpl['ADMIN_LINK'] = PHPWS_Text::moduleLink(MENU_ADMIN_ON, 'menu', $vars);
            }
        }

        $tpl['TITLE'] = $this->getTitle();
        $tpl['LINKS'] = $this->displayLinks($edit);

        if ($pin_mode &&
            Current_User::allow('menu') && 
            isset($_SESSION['Menu_Clip']) && 
            isset($_SESSION['Menu_Clip'][$this->id])) {

            $pinvars['command'] = 'pin_menu';
            $pinvars['key_id'] = $key->id;
            $pinvars['menu_id'] = $this->id;
            $tpl['CLIP'] = PHPWS_Text::secureLink(MENU_PIN, 'menu', $pinvars);
        }

        $content = PHPWS_Template::process($tpl, 'menu', $file);

        Layout::set($content, 'menu', $content_var);
    }
    
    function reorderLinks()
    {
        if (!$this->id) {
            return false;
        }
        $db = new PHPWS_DB('menu_links');
        $db->addWhere('menu_id', $this->id);
        $db->addColumn('id');
        $db->addColumn('parent');
        $db->addOrder('link_order');
        $db->setIndexBy('parent');

        $result = $db->select();
        if (empty($result)) {
            return;
        }

        foreach ($result as $parent_id => $links) {
            $count = 1;
            foreach ($links as $link) {
                $db->reset();
                $db->addWhere('id', $link['id']);
                $db->addValue('link_order', $count);
                $db->update();
                $count++;
            }
        }
        return true;
    }
}

?>