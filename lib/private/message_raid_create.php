<?php

    function msgRaidCreate( $aRequest )
    {
        if ( validRaidlead() )
        {
            global $gGame;
            
            loadGameSettings();
            $Connector = Connector::getInstance();
            $LocationId = $aRequest["locationId"];
    
            // Create location
    
            if ( $LocationId == 0 )
            {
                $NewLocationQuery = $Connector->prepare("INSERT INTO `".RP_TABLE_PREFIX."Location`".
                                                        "(Game, Name, Image) VALUES (:Game, :Name, :Image)");
    
                $NewLocationQuery->bindValue(":Name", requestToXML( $aRequest["locationName"], ENT_COMPAT, "UTF-8" ), PDO::PARAM_STR );
                $NewLocationQuery->bindValue(":Image", $aRequest["raidImage"], PDO::PARAM_STR );
                $NewLocationQuery->bindValue(":Game", $gGame["GameId"], PDO::PARAM_STR );
    
                if (!$NewLocationQuery->execute())
                    return; // ### return, location could not be created ###
    
                $LocationId = $Connector->lastInsertId();
            }
    
            // Create raid
    
            if ( $LocationId != 0 )
            {
                // First raid time calculation
    
                $StartHour   = intval($aRequest["startHour"]);
                $StartMinute = intval($aRequest["startMinute"]);
                $StartDay    = intval($aRequest["startDay"]);
                $StartMonth  = intval($aRequest["startMonth"]);
                $StartYear   = intval($aRequest["startYear"]);
    
                $EndHour   = intval($aRequest["endHour"]);
                $EndMinute = intval($aRequest["endMinute"]);
                $EndDay    = intval($aRequest["endDay"]);
                $EndMonth  = intval($aRequest["endMonth"]);
                $EndYear   = intval($aRequest["endYear"]);
    
                // Get users on vacation
    
                $UserSettingsQuery = $Connector->prepare("SELECT UserId, Name, IntValue, TextValue FROM `".RP_TABLE_PREFIX."UserSetting` ".
                   "WHERE Name = 'VacationStart' OR Name = 'VacationEnd' OR Name = 'VacationMessage' ORDER BY UserId");
    
                $VactionUsers = array();
                $UserSettingsQuery->loop( function($Settings) use (&$VactionUsers)
                {
                    if (!isset($VactionUsers[$Settings["UserId"]]))
                    {
                        $VactionUsers[$Settings["UserId"]] = array("Message" => "");
                    }
    
                    switch ($Settings["Name"])
                    {
                    case "VacationStart":
                        $VactionUsers[$Settings["UserId"]]["Start"] = $Settings["IntValue"];
                        break;
    
                    case "VacationEnd":
                        $VactionUsers[$Settings["UserId"]]["End"] = $Settings["IntValue"];
                        break;
    
                    case "VacationMessage":
                        $VactionUsers[$Settings["UserId"]]["Message"] = $Settings["TextValue"];
                        break;
    
                    default:
                        break;
                    }
                });
    
                // Prepare posting raids to forum
    
                $PostTargets = array();
                PluginRegistry::ForEachPlugin(function($PluginInstance) use (&$PostTargets)
                {
                    if ($PluginInstance->isActive() && $PluginInstance->postRequested())
                    {
                        array_push($PostTargets, $PluginInstance);
                    }
                });
    
                $LocationData = null;
    
                if ( sizeof($PostTargets) > 0 )
                {
                    loadSiteSettings();
    
                    $LocationQuery = $Connector->prepare("SELECT * FROM `".RP_TABLE_PREFIX."Location` WHERE LocationId = :LocationId LIMIT 1");
                    $LocationQuery->bindValue(":LocationId", intval($LocationId), PDO::PARAM_INT);
                    $LocationData = $LocationQuery->fetchFirst();
                }
    
                // Create raids(s)
    
                $Repeat = max(0, intval($aRequest["repeat"])) + 1; // repeat at least once
    
                $GroupInfo = $gGame["Groups"][$aRequest["locationSize"]];            
                $SlotRoles = implode(":", array_keys($GroupInfo));
                $SlotCount = implode(":", $GroupInfo);
                
                for ($rc=0; $rc<$Repeat; ++$rc)
                {
                    $NewRaidQuery = $Connector->prepare("INSERT INTO `".RP_TABLE_PREFIX."Raid` ".
                                                        "(LocationId, Size, Start, End, Mode, Description, SlotRoles, SlotCount ) ".
                                                        "VALUES (:LocationId, :Size, FROM_UNIXTIME(:Start), FROM_UNIXTIME(:End), :Mode, :Description, ".
                                                        ":SlotRoles, :SlotCount)");
    
                    $StartDateTime = mktime($StartHour, $StartMinute, 0, $StartMonth, $StartDay, $StartYear);
                    $EndDateTime   = mktime($EndHour, $EndMinute, 0, $EndMonth, $EndDay, $EndYear);
    
                    // Convert to UTC
    
                    $StartDateTime += $aRequest["startOffset"] * 60;
                    $EndDateTime   += $aRequest["endOffset"] * 60;
    
                    $NewRaidQuery->bindValue(":LocationId",  intval( $LocationId), PDO::PARAM_INT);
                    $NewRaidQuery->bindValue(":Size",        intval($aRequest["locationSize"]), PDO::PARAM_INT);
                    $NewRaidQuery->bindValue(":Start",       intval($StartDateTime), PDO::PARAM_INT);
                    $NewRaidQuery->bindValue(":End",         intval($EndDateTime), PDO::PARAM_INT);
                    $NewRaidQuery->bindValue(":Mode",        $aRequest["mode"], PDO::PARAM_STR);
                    $NewRaidQuery->bindValue(":Description", requestToXML( $aRequest["description"], ENT_COMPAT, "UTF-8" ), PDO::PARAM_STR);
                    $NewRaidQuery->bindValue(":SlotRoles",   $SlotRoles, PDO::PARAM_STR);
                    $NewRaidQuery->bindValue(":SlotCount",   $SlotCount, PDO::PARAM_STR);
    
                    $NewRaidQuery->execute();
                    $RaidId = $Connector->lastInsertId();
                    
                    // Set vacation attendances
    
                    foreach ($VactionUsers as $UserId => $Settings)
                    {
                        if ( ($StartDateTime >= $Settings["Start"]) && ($StartDateTime <= $Settings["End"]) )
                        {
    
                            $AbsentQuery = $Connector->prepare("INSERT INTO `".RP_TABLE_PREFIX."Attendance` (UserId, RaidId, Status, Comment) ".
                                                               "VALUES (:UserId, :RaidId, 'unavailable', :Message)");
    
                            $AbsentQuery->bindValue(":UserId", intval($UserId), PDO::PARAM_INT);
                            $AbsentQuery->bindValue(":RaidId", intval($RaidId), PDO::PARAM_INT);
                            $AbsentQuery->bindValue(":Message", $Settings["Message"], PDO::PARAM_STR);
    
                            $AbsentQuery->execute();
                        }
                    }
    
                    // Post raids to forum
    
                    if (sizeof($PostTargets) > 0)
                    {
                        $RaidQuery = $Connector->prepare("SELECT * FROM `".RP_TABLE_PREFIX."Raid` WHERE RaidId=:RaidId LIMIT 1");
                        $RaidQuery->bindValue(":RaidId", intval( $RaidId), PDO::PARAM_INT);
                        $RaidData = $RaidQuery->fetchFirst();
                        
                        $MessageData = Binding::generateMessage($RaidData, $LocationData);
                        
                        try
                        {
                            foreach($PostTargets as $PluginInstance)
                            {
                                $PluginInstance->post($MessageData["subject"], $MessageData["message"]);
                            }
                        }
                        catch (PDOException $Exception)
                        {
                            Out::getInstance()->pushError($Exception->getMessage());
                        }
                    }
    
                    // Increment start/end
    
                    switch ($aRequest["stride"])
                    {
                    case "day":
                        ++$StartDay;
                        ++$EndDay;
                        break;
    
                    case "week":
                        $StartDay += 7;
                        $EndDay += 7;
                        break;
    
                    case "month":
                        ++$StartMonth;
                        ++$EndMonth;
                        break;
    
                    default;
                    case "once":
                        $rc = $Repeat; // Force done
                        break;
                    }
                }
    
                // reload calendar
                
                $Session = Session::get();
    
                $ShowMonth = ( isset($Session["Calendar"]) && isset($Session["Calendar"]["month"]) ) ? $Session["Calendar"]["month"] : $aRequest["month"];
                $ShowYear  = ( isset($Session["Calendar"]) && isset($Session["Calendar"]["year"]) )  ? $Session["Calendar"]["year"]  : $aRequest["year"];
    
                msgQueryCalendar( prepareCalRequest( $ShowMonth, $ShowYear ) );
            }
        }
        else
        {
            $Out = Out::getInstance();
            $Out->pushError(L("AccessDenied"));
        }
    }

?>