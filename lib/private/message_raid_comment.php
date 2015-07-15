<?php

    function msgRaidComment( $aRequest )
    {
        if (validUser())
        {
            global $gGame;
            
            loadGameSettings();
            $Connector = Connector::getInstance();

            $RaidId  = intval( $aRequest['id'] );
            $UserId  = intval( UserProxy::getInstance()->UserId );
            $Message = $aRequest['message'];

            // update/insert new comment data

            $CommentQuery = $Connector->prepare('INSERT INTO `'.RP_TABLE_PREFIX.'RaidComment` ( RaidId, UserId, Comment, InsertDate) '.
                'VALUES ( :RaidId, :UserId, :Comment, FROM_UNIXTIME(:Timestamp) )' );

            $CommentQuery->bindValue(':RaidId',      intval($RaidId),      PDO::PARAM_INT);
            $CommentQuery->bindValue(':UserId',      intval($UserId),      PDO::PARAM_INT);
            $CommentQuery->bindValue(':Comment',     $Message,             PDO::PARAM_STR);
            $CommentQuery->bindValue(':Timestamp',   time(),               PDO::PARAM_INT);

            if ( $CommentQuery->execute() ) {
                // success
            }

            $CommentQuery = $Connector->prepare('SELECT Start FROM `'.RP_TABLE_PREFIX.'Raid` WHERE RaidId = :RaidId LIMIT 1');
            $CommentQuery->bindValue(':RaidId',  $RaidId, PDO::PARAM_INT);
            $CommentQuery->execute();


            msgRaidDetail( $aRequest );
        }
        else
        {
            $Out = Out::getInstance();
            $Out->pushError(L('AccessDenied'));
        }
    }

?>