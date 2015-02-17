<?php
    define("LOCALE_MAIN", true);
    define("STYLE_DEBUG", false);
    define("SCRIPT_DEBUG", true);
    
    require_once("lib/private/locale.php");
    require_once("lib/private/tools_site.php");

    // PHP version check

    if ( PHP_VERSION_ID < 50304 )
    {
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo L("PHPVersionWarning");
        die();
    }

    // Old browser check

    if (!isset($_GET["nocheck"]))
        include_once("oldbrowser.php");

    // Update or setup required check

    if ( !file_exists("lib/config/config.php") || !checkVersion($gVersion) )
    {
        include_once("runsetup.php");
        die();
    }

    // Site framework

    loadSiteSettings();
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<!doctype html>
<html ng-app="raidplanerApp">
    <head>
        <title>Raidplaner</title>
        <meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
        <meta name="keywords" content="raidplaner, ppx"/>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>

        <link rel="icon" href="favicon.png" type="image/png"/>
        <link rel="stylesheet" type="text/css" href="lib/layout/allstyles.php?v=<?php echo $gSite["Version"].((STYLE_DEBUG) ? "&debug" : ""); ?>"/>
        <link rel="stylesheet" type="text/css" href="lib/layout/bootstrap.min.css" />
    </head>

    <body>
        <div id="appwindow"<?php if ($gSite["PortalMode"]) echo " class=\"portalmode\""; ?>>
            <div id="banner" ></div>
            <div id="menu" ng-controller="MenuController">
                <span ng-if="!gUser" id="button_login" class="menu_button">
                  <div class="icon"></div><div class="text" ng-bind="gLocale.Login"></div><div class="indicator"></div>
                </span>

                <span ng-if="!gUser && gSite.AllowRegistration" id="button_register" class="menu_button">
                  <div class="icon"></div><div class="text" ng-bind="gLocale.Register"></div><div class="indicator"></div>
                </span>

                <!-- Blocked user @todo -->
                <div ng-if="!gUser.validUser && gUser.registeredUser" id="logout" ng-style="gSite.Logout ? 'display:none' : ''">
                    <button class="btn btn-default">
                        <span class="glyphicon glyphicon-log-out"></span>
                        {{gLocale.Logout}}
                    </button>
                </div>

                <span ng-if="gUser" id="button_calendar" class="menu_button"><div class="icon"></div><div class="text" ng-bind="gLocale.Calendar"></div><div class="indicator"></div></span>
                <span ng-if="gUser" id="button_raid" class="menu_button"><div class="icon"></div><div class="text" ng-bind="gLocale.Raid"></div><div class="indicator"></div></span>
                <span ng-if="gUser" id="button_profile" class="menu_button"><div class="icon"></div><div class="text" ng-bind="gLocale.Profile"></div><div class="indicator"></div></span>

                <span ng-if="gUser && gUser.isAdmin" id="button_settings_users" class="menu_button"><div class="icon"></div><div class="text" ng-bind="gLocale.Settings"></div><div class="indicator"></div></span>

                <div ng-if="gUser && gSite.Logout" id="logout">
                    <button class="button_logout">
                        <span class="glyphicon glyphicon-log-out"></span>
                        {{gLocale.Logout}}
                    </button>
                </div>

                <span ng-if="gSite.HelpLink" id="help">
                    <button class="button_help" ng-click="openLink(gSite.HelpLink)">
                        <span class="glyphicon glyphicon-question-sign"></span>
                    </button>
                </span>
            </div>
            <div id="body" ng-view></div>

            <span id="version"><?php echo "version ".intVal($gSite["Version"] / 100).".".intVal(($gSite["Version"] % 100) / 10).".".intVal($gSite["Version"] % 10).(($gSite["Version"] - intval($gSite["Version"]) > 0) ? chr(round(($gSite["Version"] - intval($gSite["Version"])) * 10) + ord("a")-1) : ""); ?></span>
        </div>

        <div id="eventblocker"></div>
        <div id="dialog"></div>
        <div id="ajaxblocker">
            <div class="background"></div>
            <div class="notification ui-corner-all">
                <img src="lib/layout/images/busy.gif"/><br/><br/>
                <?php echo L("Busy"); ?>
            </div>
        </div>
        <div id="tooltip">
            <div id="tooltip_arrow"></div>
            <div id="info_text"></div>
        </div>
        <div id="sheetoverlay">
            <div id="closesheet" class="clickable"></div>
            <div id="sheet_body"></div>
        </div>

        <script type="application/javascript" src="lib/script/angular/angular.min.js"></script>
        <script type="application/javascript" src="lib/script/angular-route/angular-route.min.js"></script>
        <script type="application/javascript" src="lib/script/ui-bootstrap-tpls-0.12.0.min.js"></script>
        <script type="application/javascript" src="lib/script/app.js"></script>
        <?php // Load scripts
            if (defined("SCRIPT_DEBUG") && SCRIPT_DEBUG)
            {
                include_once("lib/script/allscripts.php");
            }
            else
            {
                echo "<script type=\"text/javascript\" src=\"lib/script/raidplaner.js?v=".$gSite["Version"]."\"></script>";
            }
        ?>
    </body>
</html>
