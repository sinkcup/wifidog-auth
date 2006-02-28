<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +-------------------------------------------------------------------+
// | WiFiDog Authentication Server                                     |
// | =============================                                     |
// |                                                                   |
// | The WiFiDog Authentication Server is part of the WiFiDog captive  |
// | portal suite.                                                     |
// +-------------------------------------------------------------------+
// | PHP version 5 required.                                           |
// +-------------------------------------------------------------------+
// | Homepage:     http://www.wifidog.org/                             |
// | Source Forge: http://sourceforge.net/projects/wifidog/            |
// +-------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or     |
// | modify it under the terms of the GNU General Public License as    |
// | published by the Free Software Foundation; either version 2 of    |
// | the License, or (at your option) any later version.               |
// |                                                                   |
// | This program is distributed in the hope that it will be useful,   |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of    |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the     |
// | GNU General Public License for more details.                      |
// |                                                                   |
// | You should have received a copy of the GNU General Public License |
// | along with this program; if not, contact:                         |
// |                                                                   |
// | Free Software Foundation           Voice:  +1-617-542-5942        |
// | 59 Temple Place - Suite 330        Fax:    +1-617-542-2652        |
// | Boston, MA  02111-1307,  USA       gnu@gnu.org                    |
// |                                                                   |
// +-------------------------------------------------------------------+

/**
 * @package    WiFiDogAuthServer
 * @author     Benoit Gregoire <bock@step.polymtl.ca>
 * @copyright  2005-2006 Benoit Gregoire, Technologies Coeus inc.
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * @internal We put a call to validate_schema() here so it systematically called
 * from any UI page, but not from any machine readable pages
 */
require_once('include/schema_validate.php');
validate_schema();

/**
 * If the database doesn't get cleaned up by a cron job, we'll do now
 */
if (CONF_USE_CRON_FOR_DB_CLEANUP == false) {
    garbage_collect();
}

/**
 * Load required file
 */
require_once('include/common_interface.php');

/**
 * Style contains functions managing headers, footers, stylesheet, etc.
 *
 * @package    WiFiDogAuthServer
 * @author     Benoit Gregoire <bock@step.polymtl.ca>
 * @copyright  2005-2006 Benoit Gregoire, Technologies Coeus inc.
 */
class MainUI
{
    /**
     * Content to be displayed in the main pane
     *
     * @var string
     * @access private
     */
    private $main_content;

    /**
     * Content to be displayed in the tool pane
     *
     * @var string
     * @access private
     */
    private $tool_content;

    /**
     * Object for Smarty class
     *
     * @var object
     * @access private
     */
    private $smarty;

    /**
     * Title of HTML page
     *
     * @var string
     * @access private
     */
    private $title;

    /**
     * Headers of HTML page
     *
     * @var private
     * @access private
     */
    private $html_headers;

    /**
     * Defines if tool section of HTML page is enabled or not
     *
     * @var bool
     * @access private
     */
    private $tool_section_enabled = true;

    /**
     * Scripts for the footer
     *
     * @var array
     * @access private
     */
    private $footer_scripts = array ();

    /**
     * Contructor
     *
     * @return void
     *
     * @access public
     */
    public function __construct()
    {
        // Init Smarty
        $this->smarty = new SmartyWifidog();

        // Set default title
        $this->title = Network::getCurrentNetwork()->getName() . ' ' . _("authentication server");
    }

    /**
     * Check if the tool section is enabled
     *
     * @return bool True or false
     *
     * @access public
     */
    public function isToolSectionEnabled()
    {
        return $this->tool_section_enabled;
    }

    /**
     * Check if the tool section is enabled
     *
     * @return bool True or false
     *
     * @access public
     */
    public function setToolSectionEnabled($status)
    {
        $this->tool_section_enabled = $status;
    }

    /**
     * Set the content to be displayed in the main pane
     *
     * @param string $html HTML content to be displayed in the main pane
     *
     * @return void
     *
     * @access public
     */
    public function setMainContent($html)
    {
        $this->main_content = $html;
    }

    /**
     * Set the title of the HTML page
     *
     * @param string $title_string Title of the HTML page
     *
     * @return void
     *
     * @access public
     */
    public function setTitle($title_string)
    {
        $this->title = $title_string;
    }

    /**
     * Add content at the very end of the <body>.
     *
     * This is NOT meant to add footers or other display content, it is meant
     * to add <script></script> tag pairs that have to be executed only once
     * the page is loaded.
     *
     * @param string $script A piece of script surrounded by
     *                       <script></script> tags.
     *
     * @return void
     *
     * @access public
     */
    public function addFooterScript($script)
    {
        $this->footer_scripts[] = $script;
    }

    /**
     * Set the HTML page headers
     *
     * @param string $headers_string HTML page headers
     *
     * @return void
     *
     * @access public
     */
    public function setHtmlHeader($headers_string)
    {
        $this->html_headers = $headers_string;
    }

    /**
     * Set the section to be displayed in the tool pane
     *
     * @param string $section Section to be displayed:
     *                          + ADMIN for administration tool pane
     *
     * @return string HTML code of tool pane
     *
     * @access public
     */
    public function setToolSection($section)
    {
        // Init ALL smarty SWITCH values
        $this->smarty->assign('sectionADMIN', false);

        switch ($section) {
        case "ADMIN":
            // Set section of Smarty template
            $this->smarty->assign('sectionADMIN', true);

            // Get information about user
            $_currentUser = User::getCurrentUser();

            if ($_currentUser && $_currentUser->isNobody()) {
                // The user has no permission to access the administrative functions
                $_html = _("You do not have permissions to access any administration functions.");
            } else {
                // Init values
                $_sqlAdditionalWhere = "";

                // Init ALL smarty values
                $this->smarty->assign('isSuperAdmin', false);
                $this->smarty->assign('isOwner', false);
                $this->smarty->assign('formAction', "");
                $this->smarty->assign('nodeUI', "");
                $this->smarty->assign('networkUI', "");

                // Define user security levels for the template
                $this->smarty->assign('isSuperAdmin', $_currentUser && $_currentUser->isSuperAdmin());
                $this->smarty->assign('isOwner', $_currentUser && $_currentUser->isOwner());

                /*
                 * If the user is super admin OR owner of at least one node
                 * show the node menu
                 */
                if ($_currentUser && ($_currentUser->isSuperAdmin() || $_currentUser->isOwner())) {
                    // Assign the action URL for the form
                    $this->smarty->assign('formAction', GENERIC_OBJECT_ADMIN_ABS_HREF);

                    /*
                     * If current user is a owner the SQL query must be changed
                     * to return his nodes only
                     */
                    if (!$_currentUser->isSuperAdmin()) {
                        $_sqlAdditionalWhere = "AND node_id IN (SELECT node_id from node_stakeholders WHERE is_owner = true AND user_id='" . $_currentUser->getId() . "')";
                    }

                    // Provide node select control to the template
                    $this->smarty->assign('nodeUI', Node::getSelectNodeUI('object_id', $_sqlAdditionalWhere));
                }

                // If the user is network admin show the network menu
                if ($_currentUser && $_currentUser->isSuperAdmin()) {
                    // Provide network select control to the template
                    $this->smarty->assign('networkUI', Network::getSelectNetworkUI('object_id'));
                }

                // Compile HTML code
                $_html = $this->smarty->fetch("templates/classes/MainUI_ToolSection.tpl");
            }
            break;

        default:
            $_html = _("Unknown section:") . $section;
            break;
        }

        $this->tool_content = $_html;
    }

    /**
     * Set the content to be displayed in the tool pane
     *
     * @param string $html Content to be displayed
     *
     * @return void
     *
     * @access public
     */
    public function setToolContent($html)
    {
        $this->tool_content = $html;
    }

    /**
     * Get the content to be displayed in the tool pane
     *
     * @param string $section Section to be displayed:
     *                          + START
     *                          + LOGIN
     *
     * @return string HTML markup
     *
     * @access private
     */
    private function getToolContent($section = "START")
    {
        // Define globals
        global $session;
        global $AVAIL_LOCALE_ARRAY;

        // Init values
        $_html = "";
        $_gwId = null;
        $_gwAddress = null;
        $_gwPort = null;
        $_selected = "";
        $_languageChooser = array();

        // Init ALL smarty SWITCH values
        $this->smarty->assign('sectionSTART', false);
        $this->smarty->assign('sectionLOGIN', false);

        switch ($section) {
        case "START":
            // Set section of Smarty template
            $this->smarty->assign('sectionSTART', true);

            // Get information about user
            $_currentUser = User::getCurrentUser();

            // Init ALL smarty values
            $this->smarty->assign('networkHomepageURL', "");
            $this->smarty->assign('networkName', "");
            $this->smarty->assign('isValidUser', false);
            $this->smarty->assign('username', "");
            $this->smarty->assign('logoutParameters', "");
            $this->smarty->assign('loginParameters', "");
            $this->smarty->assign('formAction', "");
            $this->smarty->assign('toolContent', "");
            $this->smarty->assign('accountInformation', "");
            $this->smarty->assign('techSupportInformation', "");

            // Provide Smarty with information about the network
            $this->smarty->assign('networkHomepageURL', Network::getCurrentNetwork()->getHomepageURL());
            $this->smarty->assign('networkName', Network::getCurrentNetwork()->getName());

            /*
             * Provide Smarty information about the user's login/logout status
             */

            if ($_currentUser != null) {
                // User is logged in
                $this->smarty->assign('isValidUser', true);

                // Set username for Smarty
                $this->smarty->assign('username', $_currentUser->getUsername());

                // Detect gateway information
                $_gwId = $session->get(SESS_GW_ID_VAR);
                $_gwAddress = $session->get(SESS_GW_ADDRESS_VAR);
                $_gwPort = $session->get(SESS_GW_PORT_VAR);

                // If gateway information could be detected tell them Smarty
                if ($_gwId && $_gwAddress && $_gwPort) {
                    $this->smarty->assign('logoutParameters', "&amp;gw_id=" . $_gwId . "&amp;gw_address=" . $_gwAddress . "&amp;gw_port=" . $_gwPort);
                }
            } else {
                // Detect gateway information
                $_gwId = !empty ($_REQUEST['gw_id']) ? $_REQUEST['gw_id'] : $session->get(SESS_GW_ID_VAR);
                $_gwAddress = !empty ($_REQUEST['gw_address']) ? $_REQUEST['gw_address'] : $session->get(SESS_GW_ADDRESS_VAR);
                $_gwPort = !empty ($_REQUEST['gw_port']) ? $_REQUEST['gw_port'] : $session->get(SESS_GW_PORT_VAR);

                // If gateway information could be detected tell them Smarty
                if (!empty ($_gwId) && !empty ($_gwAddress) && !empty ($_gwPort)) {
                    $this->smarty->assign('loginParameters', "?gw_id=" . $_gwId . "&amp;gw_address=" . $_gwAddress . "&amp;gw_port=" . $_gwPort);
                }
            }

            /*
             * Provide Smarty information for the language chooser
             */

            // Assign the action URL for the form
            $this->smarty->assign('formaAction', $_SERVER['REQUEST_URI']);

            foreach ($AVAIL_LOCALE_ARRAY as $_langIds => $_langNames) {
                if (Locale::getCurrentLocale()->getId() == $_langIds) {
                    $_selected = ' selected="selected"';
                } else {
                    $_selected = "";
                }

                $_languageChooser[] = '<option label="' . $_langNames . '" value="' . $_langIds . '"' . $_selected . '>' . $_langNames . '</option>';
            }

            // Provide Smarty all available languages
            $this->smarty->assign('languageChooser', $_languageChooser);

            /*
             * Provide Smarty information for the language chooser
             */

            // Provide content
            $this->smarty->assign('toolContent', $this->tool_content);

            // Provide information
            $this->smarty->assign('accountInformation', sprintf(_("Accounts on %s are and will stay completely free."), Network::getCurrentNetwork()->getName()));
            $this->smarty->assign('techSupportInformation', sprintf(_("Please inform us of any problem or service interruption at: %s"), '<a href="mailto:' . Network::getCurrentNetwork()->getTechSupportEmail() . '">' . Network::getCurrentNetwork()->getTechSupportEmail() . '</a>'));

            // Compile HTML code
            $_html = $this->smarty->fetch("templates/classes/MainUI_ToolContent.tpl");
            break;

        case "LOGIN":
            // Set section of Smarty template
            $this->smarty->assign('sectionLOGIN', true);
            break;

        default:
            $_html = _("Unknown section:") . $section;
            break;
        }

        return $_html;
    }

    /**
     * Display the main page
     *
     * @return void
     *
     * @access public
     * @internal Uses a few request parameters to displaty debug information.
     * If $_REQUEST['debug_request'] is present, it will print out the
     * $_REQUEST array at the top of the page.
     */
    public function display()
    {
        // Init values
        $_stylesheetFile = "";

        // Init ALL smarty values
        $this->smarty->assign('htmlHeaders', "");
        $this->smarty->assign('title', "");
        $this->smarty->assign('stylesheetURL', "");
        $this->smarty->assign('stylesheetParsedFile', "");
        $this->smarty->assign('isSuperAdmin', false);
        $this->smarty->assign('isOwner', false);
        $this->smarty->assign('debugRequested', false);
        $this->smarty->assign('debugOutput', "");
        $this->smarty->assign('toolPaneEnabled', false);
        $this->smarty->assign('toolPaneContent', "");
        $this->smarty->assign('mainContent', "");
        $this->smarty->assign('footerScripts', array());

        // Add HTML headers
        $this->smarty->assign('htmlHeaders', $this->html_headers);

        // Asign title
        $this->smarty->assign('title', $this->title);

        // Asign path to CSS stylesheet
        $this->smarty->assign('stylesheetURL', COMMON_CONTENT_URL.STYLESHEET_NAME);

        /*
         * Include stylesheet to be parsed by Smarty
         */
        if (is_file(NODE_CONTENT_PHP_RELATIVE_PATH . STYLESHEET_NAME)) {
            $_stylesheetFile = NODE_CONTENT_SMARTY_PATH . STYLESHEET_NAME;
        } else {
            $_stylesheetFile = DEFAULT_CONTENT_SMARTY_PATH . STYLESHEET_NAME;
        }

        // Asign path to CSS stylesheet to be parsed by Smarty
        $this->smarty->assign('stylesheetParsedFile', $_stylesheetFile);

        /*
         * Allow super admin to display debug output if requested by using
         * $_REQUEST['debug_request']
         */

        // Get information about user
        $_currentUser = User::getCurrentUser();

        // Define user security levels for the template
        $this->smarty->assign('isSuperAdmin', $_currentUser && $_currentUser->isSuperAdmin());
        $this->smarty->assign('isOwner', $_currentUser && $_currentUser->isOwner());

        if (isset($_REQUEST['debug_request']) && ($_currentUser && $_currentUser->isSuperAdmin())) {
            // Tell Smarty everything it needs to know
            $this->smarty->assign('debugRequested', true);
            $this->smarty->assign('debugOutput', print_r($_REQUEST, true));
        }

        /*
         * Build tool pane if it has been enabled
         */
        if ($this->isToolSectionEnabled()) {
            $this->smarty->assign('toolPaneEnabled', true);
            $this->smarty->assign('toolPaneContent', $this->getToolContent());
        }

        // Provide main content to Smarty
        $this->smarty->assign('mainContent', $this->main_content);

        // Provide footer scripts to Smarty
        $this->smarty->assign('footerScripts', $this->footer_scripts);

        // Compile HTML code and output it
        $this->smarty->display("templates/classes/MainUI_Display.tpl");
    }

    /**
     * Display a generic error message
     *
     * @param string $errmsg                  The error message to be displayed
     * @param bool   $show_tech_support_email Defines wether to show the link of
     *                                        the tech-support
     *
     * @return void
     *
     * @access public
     */
    function displayError($errmsg, $show_tech_support_email = true)
    {
        // Init ALL smarty values
        $this->smarty->assign("error", "");
        $this->smarty->assign("show_tech_support_email", false);
        $this->smarty->assign("tech_support_email", "");

        // Define needed error content
        $this->smarty->assign("error", $errmsg);

        if ($show_tech_support_email) {
            $this->smarty->assign("show_tech_support_email", true);
            $this->smarty->assign("tech_support_email", Network::getCurrentNetwork()->getTechSupportEmail());
        }

        /*
         * Output the error message
         */
        $_html = $this->smarty->fetch("templates/sites/error.tpl");

        $this->setMainContent($_html);
        $this->display();
    }

}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
