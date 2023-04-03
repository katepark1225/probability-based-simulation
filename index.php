<?php
// Establish mysqli connection
$conn = mysqli_connect();

// ---------------- VARIABLES
if (isset($_POST['filter_btn'])) {
    $homeTeam = $_POST['home']; // Spiders
    $awayTeam = $_POST['away']; // Hipass
}

$transcripts = array();

$homeServer = 0;
$awayServer = 0;
// ---------------- VARIABLES

// ---------------- PROCEDURAL FLOW
$getPlayers = getPlayers($homeTeam, $awayTeam, $conn);
$homeFrontup = $getPlayers[0];
$awayFrontup = $getPlayers[1];
$servePatterns = servePatterns($homeFrontup, $awayFrontup, $homeTeam, $awayTeam, $conn);
$filterHit = filterHit($conn);
$master = masterTable($conn, $homeTeam, $awayTeam);
$scoreboard = array();

$iteration=0;
while ($iteration <= 5) {
    $homeSetScore = 0;
    $awaySetScore = 0;
    $homeScore = 0;
    $awayScore = 0;

    $currentTeam = firstServe($homeTeam, $awayTeam);
    
    $hitTurn = 1;

    $currentPosition = "";
    $currentServer = "";
    $notPlayer = "";

    $transcript = array();

    while (True) {
        if ($hitTurn == 1) {
            if (count($transcript) > 0) {
                $lastRow=1;
                foreach($transcript as $index=>$row) {
                    if ($lastRow == count($transcript)) {
                        if ($row['hitTurn'] == 'p' or $row['hitTurn'] == 'e') {
                            $toRun = 'serve';
                        } else {
                            if ($row['action_type'] == 'serve') {
                                $toRun = 'receive';
                            } else {
                                $toRun = 'action';
                            }
                        }
                    }
                    $lastRow++;
                }
            } else {
                $toRun = 'serve';
            }
        } else {
            $toRun = 'action';
        }

        if ($toRun == 'serve') {
            $currentServer = getServer($homeTeam, $awayTeam, $currentTeam, $homeFrontup, $awayFrontup, $homeServer, $awayServer);
            $serveAction = serveAction($servePatterns, $currentTeam, $currentServer, $homeTeam, $awayTeam, $transcript, $hitTurn, $homeSetScore, $awaySetScore);
            $updateScore = $serveAction[0];
            if (count($updateScore) > 0){
                if (count($updateScore) == 1) {
                    $iteration++;
                    array_push($transcripts, $transcript);
                    array_push($scoreboard, array("homeSetScore"=>$homeSetScore, "awaySetScore"=>$awaySetScore));
                    break;
                } else {
                    $homeSetScore = $updateScore[0];
                    $awaySetScore = $updateScore[1];
                    $homeScore = $updateScore[2];
                    $awayScore = $updateScore[3];
                }
            }
            $currentPosition = $serveAction[1];
            $currentTeam = $serveAction[2];
            $hitTurn = $serveAction[3];
            $transcript = $serveAction[4];
        } else if ($toRun == 'receive') {
            $gettingReceive = gettingReceive($currentTeam, $currentPosition, $currentServer, $homeTeam, $awayTeam, $transcript, $homeSetScore, $awaySetScore, $homeScore, $awayScore, $hitTurn, $master);
            $currentPosition = $gettingReceive[0];
            $currentTeam = $gettingReceive[1];
            $notPlayer = $gettingReceive[2];
            $hitTurn = $gettingReceive[3];
            $updateScore = $gettingReceive[4];
            if (count($updateScore) > 0) {
                if (count($updateScore) == 1) {
                    $iteration++;
                    array_push($transcripts, $transcript);
                    array_push($scoreboard, array("homeSetScore"=>$homeSetScore, "awaySetScore"=>$awaySetScore));
                    break;
                } else {
                    $homeSetScore = $updateScore[0];
                    $awaySetScore = $updateScore[1];
                    $homeScore = $updateScore[2];
                    $awayScore = $updateScore[3];
                }
            }
            $transcript = $gettingReceive[5];
        } else if ($toRun == 'action') {
            $gettingOffense = gettingOffense($hitTurn, $currentTeam, $notPlayer, $currentPosition, $homeTeam, $awayTeam, $homeScore, $awayScore, $homeSetScore, $awaySetScore, $transcript, $filterHit, $master);
            $hitTurn = $gettingOffense[0];
            $currentTeam = $gettingOffense[1];
            $updateScore = $gettingOffense[2];
            $currentPosition = $gettingOffense[3];
            if (count($updateScore) > 0) {
                if (count($updateScore) == 1) {
                    $iteration++;
                    array_push($transcripts, $transcript);
                    array_push($scoreboard, array("homeSetScore"=>$homeSetScore, "awaySetScore"=>$awaySetScore));
                    break;
                } else {
                    $homeSetScore = $updateScore[0];
                    $awaySetScore = $updateScore[1];
                    $homeScore = $updateScore[2];
                    $awayScore = $updateScore[3];
                }
            }
            $transcript = $gettingOffense[4];
            $notPlayer = $gettingOffense[5];
        }
    }
}
// ---------------- PROCEDURAL FLOW

function getPlayers($homeTeam, $awayTeam, $conn) {
    $homeFrontup = array();
    $awayFrontup = array();
    $getFrontup = "SELECT * FROM player_list WHERE team = '".$homeTeam."' AND is_frontup = 1 OR team = '".$awayTeam."' AND is_frontup = 1";
    // This query does not include player changes (due to lack of data for backup players).
    if ($gotFrontup = mysqli_query($conn, $getFrontup)) {
        while ($row = mysqli_fetch_assoc($gotFrontup)) {
            if ($row['team'] == $homeTeam) {
                array_push($homeFrontup, $row['player_number']);
            } else if ($row['team'] == $awayTeam) {
                array_push($awayFrontup, $row['player_number']);
            }
        }
    }
    return array($homeFrontup, $awayFrontup);
}

function firstServe($homeTeam, $awayTeam) {
    $teams = array($homeTeam, $awayTeam);
    return($teams[array_rand($teams)]);
}

function getServer($homeTeam, $awayTeam, $currentTeam, $homeFrontup, $awayFrontup, $homeServer, $awayServer) {
    if ($currentTeam == $homeTeam) {
        $currentServer = $homeServer+1;
        if ($currentServer > count($homeFrontup)) {
            $currentServer = 1;
        }
        return $homeFrontup[$currentServer];
    } else if ($currentTeam == $awayTeam) {
        $currentServer = $awayServer+1;
        if ($currentServer > count($awayFrontup)) {
            $currentServer = 1;
        }
        return $awayFrontup[$currentServer];
    }
}

function servePatterns($homeFrontup, $awayFrontup, $homeTeam, $awayTeam, $conn) {
    $servePatterns = array();
    foreach ($homeFrontup as $player) {
        $getPattern = "SELECT * FROM vleague_women WHERE player_number = '".$player."' AND team = '".$homeTeam."' AND action_type = 'serve'";
        if ($gotPattern = mysqli_query($conn, $getPattern)) {
            $i=0;
            while ($row = mysqli_fetch_assoc($gotPattern)) {
                array_push($servePatterns, array("key"=>$i, "player_number"=>$row['player_number'], "team"=>$row['team'], "position"=>$row['position'], "poe"=>$row['hit_turn']));
                $i++;
            }
        }
    }
    foreach ($awayFrontup as $player) {
        $getPattern = "SELECT * FROM vleague_women WHERE player_number = '".$player."' AND team = '".$awayTeam."' AND action_type = 'serve'";
        if ($gotPattern = mysqli_query($conn, $getPattern)) {
            $i=0;
            while ($row = mysqli_fetch_assoc($gotPattern)) {
                array_push($servePatterns, array("key"=>$i, "player_number"=>$row['player_number'], "team"=>$row['team'], "position"=>$row['position'], "poe"=>$row['hit_turn']));
                $i++;
            }
        }
    }
    return $servePatterns;
}

function updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam) {
    if ($currentScorer == $homeTeam) {
        $homeScore++;
    } else if ($currentScorer == $awayTeam) {
        $awayScore++;
    }

    if ($homeSetScore + $awaySetScore == 5) {
        if ($homeScore-$awayScore >= 2 OR $awayScore - $homeScore >= 2) {
            if ($homeScore >= 15 OR $awayScore >= 15) {
                if ($homeScore > $awayScore) {
                    return array($homeTeam);
                } else {
                    return array($awayTeam);
                }
            }
        }
    } else {
        if ($homeScore-$awayScore >= 2 OR $awayScore-$homeScore >= 2) {
            if ($homeScore >= 25 OR $awayScore >= 25) {
                if ($homeScore > $awayScore) {
                    $homeSetScore++;
                    if ($homeSetScore == 3) {
                        return array($homeTeam);
                    }
                } else {
                    $awaySetScore++;
                    if ($awaySetScore == 3) {
                        return array($awayTeam);
                    }
                }
            }
        }
    }
    return array($homeSetScore, $awaySetScore, $homeScore, $awayScore);
}

function masterTable($conn, $homeTeam, $awayTeam) {
    if (isset($_POST['filter_btn'])) {
        $from_date = $_POST['from_date'];
        $to_date = $_POST['to_date'];
        $homeTeam = $_POST['home'];
        $awayTeam = $_POST['away'];
        
        if ($from_date == "") {
            $from_date = "AND datetime_game >= '".$from_date."'";
        } else {
            $from_date = "";
        }
        if ($to_date == "") {
            $to_date = "AND datetime_game <= '".$to_date."'";
        } else {
            $to_date = "";
        }
    } else {
        $from_date = "";
        $to_date = "";
    }
    
    $master = array();
    $query = "SELECT * FROM vleague_women WHERE team = '".$homeTeam."' ".$from_date.$to_date." OR team = '".$awayTeam."' ".$from_date.$to_date." ORDER BY id ASC";
    if ($res = mysqli_query($conn, $query)) {
        while ($row = mysqli_fetch_assoc($res)) {
            array_push($master, array("id"=>$row['id'], "team"=>$row['team'], "datetime_game"=>$row['datetime_game'], "player_number"=>$row['player_number'], "action_type"=>$row['action_type'], "position"=>$row['position'], "hit_turn"=>$row['hit_turn'], "additional_comments"=>$row['additional_comments'], "unexpected_error"=>$row['unexpected_error'], "set_number"=>$row['set_number']));
        }
    }
    return $master;
}

function gettingOffense($hitTurn, $currentTeam, $notPlayer, $currentPosition, $homeTeam, $awayTeam, $homeScore, $awayScore, $homeSetScore, $awaySetScore, $transcript, $filterHit, $master) {
    $localArray = array();
    $defense_types = array('serve', 'receive');
    $blockAction = array('single block', 'no block', 'block assist');
    $lastRow=1;
    foreach ($transcript as $index=>$value) {
        if ($lastRow == count($transcript)) {
            if ($value['team'] == $currentTeam && in_array($value['action_type'], $blockAction)) {
                $defense_types = array('serve', 'receive', 'single block', 'no block', 'block assist');
            }
            $currentAction = $value['action_type'];
            $currentPosition = $value['position'];
            $lastTeam = $value['team'];
        }
        $lastRow++;
    }
    $count=0;
    $i=0;
    foreach ($master as $index=>$row) {
            if ($hitTurn == 1) {
                if ($count != 0 && $row['team'] == $currentTeam && !in_array($row['action_type'], $defense_types)) {
                    $i2=1;
                    while ($i2 <= $count) {
                        array_push($localArray, array("key"=>$i, "player_number"=>$row['player_number'], "actionType"=>$row['action_type'], "position"=>$row['position'], "hitTurn"=>$row['hit_turn'], "additional_comments"=>$row['additional_comments']));
                    $i2++;
                    }
                } else {
                    $count = 0;
                }
                
                if ($lastTeam == $currentTeam) {
                    if ($row['position'] == $currentPosition && $row['team'] == $currentTeam) {
                        $count++;
                    }
                    if ($row['position'] == $currentPosition && $row['action_type'] == $currentAction && $row['team'] == $currentTeam) {
                        $count++;
                    }
                } else {
                    if ($row['position'] == $currentPosition && $row['team'] != $currentTeam) {
                        $count++;
                    }
                    if ($row['position'] == $currentPosition && $row['action_type'] == $currentAction && $row['team'] != $currentTeam) {
                        $count++;
                    }
                }
            } else {
                if ($count != 0 && $row['team'] == $currentTeam && $row['player_number'] != $notPlayer && !in_array($row['action_type'], $defense_types)) {
                    if ($hitTurn == 3 && !in_array($row['action_type'], $filterHit) && $row['additional_comments'] == "") {
                        //
                    } else {
                       $i2=1;
                        while ($i2 <= $count) {
                            array_push($localArray, array("key"=>$i, "player_number"=>$row['player_number'], "actionType"=>$row['action_type'], "position"=>$row['position'], "hitTurn"=>$row['hit_turn'], "additional_comments"=>$row['additional_comments']));
                        $i2++;
                        } 
                    }
                } else {
                    $count = 0;
                }
                if ($row['position'] == $currentPosition && $row['team'] == $currentTeam) {
                    $count++;
                }
                if ($row['position'] == $currentPosition && $row['action_type'] == $currentAction && $row['team'] == $currentTeam) {
                    $count++;
                }
            }
            
        $i++;
    }
    $sampleArray = array();
    foreach ($localArray as $i=>$v) {
        array_push($sampleArray, $v['key']);
    }
    $currentPosition = $sampleArray[array_rand($sampleArray)];
    foreach ($localArray as $i=>$v) {
        if ($v['key'] == $currentPosition) {
            if ($v['hitTurn'] == 'p') {
                if ($currentTeam == $homeTeam) {
                    array_push($transcript, array("team"=>$currentTeam, "player_number"=>$v['player_number'], "action_type"=>$v['actionType'], "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'p'));
                    $hitTurn = 1;
                    $currentScorer = $homeTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                    $currentTeam = $awayTeam;
                } else {
                    array_push($transcript, array("team"=>$currentTeam, "player_number"=>$v['player_number'], "action_type"=>$v['actionType'], "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'p'));
                    $hitTurn = 1;
                    $currentScorer = $awayTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                    $currentTeam = $homeTeam;
                }
            } else if ($v['hitTurn'] == 'e') {
                if ($currentTeam == $homeTeam) {
                    array_push($transcript, array("team"=>$homeTeam, "player_number"=>$v['player_number'], "action_type"=>$v['actionType'], "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'e'));
                    $hitTurn = 1;
                    $currentScorer = $awayTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                    $currentTeam = $currentScorer;
                } else {
                    array_push($transcript, array("team"=>$awayTeam, "player_number"=>$v['player_number'], "action_type"=>$v['actionType'], "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'e'));
                    $hitTurn = 1;
                    $currentScorer = $homeTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                    $currentTeam = $currentScorer;
                }
            } else {
                $notPlayer = $v['player_number'];
                $blockAction = array('single block', 'block assist', 'no block');
                array_push($transcript, array("team"=>$currentTeam, "player_number"=>$v['player_number'], "action_type"=>$v['actionType'], "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>$hitTurn));
                
                if (in_array($v['actionType'], $blockAction)) {
                    if ($v['additional_comments'] == "") {
                        if ($hitTurn == 3) {
                            $hitTurn = 1;
                            if ($currentTeam == $homeTeam) {
                                $currentTeam = $awayTeam;
                            } else {
                                $currentTeam = $homeTeam;
                            }
                        }
                    } else {
                        $hitTurn = 1;
                        if ($currentTeam == $homeTeam) {
                                $currentTeam = $awayTeam;
                            } else {
                                $currentTeam = $homeTeam;
                            }
                    }
                } else {
                    if ($hitTurn == 3) {
                        $hitTurn = 1;
                        if ($currentTeam == $homeTeam) {
                                $currentTeam = $awayTeam;
                            } else {
                                $currentTeam = $homeTeam;
                            }
                    } else {
                        if (in_array($v['actionType'], $filterHit)) {
                            $hitTurn = 1;
                            if ($currentTeam == $homeTeam) {
                                $currentTeam = $awayTeam;
                            } else {
                                $currentTeam = $homeTeam;
                            }
                        } else {
                            $hitTurn++;
                        }
                    }
                }
            }
            break;
        }
    }
    if (!isset($updateScore)) {
        $updateScore = array();
    }
    if (!isset($notPlayer)) {
        $notPlayer = "";
    }
    return array($hitTurn, $currentTeam, $updateScore, $currentPosition, $transcript, $notPlayer);
}

function serveAction($servePatterns, $currentTeam, $currentServer, $homeTeam, $awayTeam, $transcript, $hitTurn, $homeSetScore, $awaySetScore) {
    $localArray = array();
    foreach ($servePatterns as $i=>$v) {
        if ($v['team'] == $currentTeam && $v['player_number'] == $currentServer) {
            array_push($localArray, $v['key']);
        }
    }
    $currentPosition = $localArray[array_rand($localArray)];
    foreach ($servePatterns as $i=>$v) {
        if ($v['team'] == $currentTeam && $v['key'] == $currentPosition) {
            if ($v['poe'] == 'p') {
                if ($currentTeam == $homeTeam) {
                    array_push($transcript, array("team"=>$currentTeam, "player_number"=>$currentServer, "action_type"=>'serve', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'p'));
                    $hitTurn = 1;
                    $currentScorer = $homeTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                } else {
                    array_push($transcript, array("team"=>$currentTeam, "player_number"=>$currentServer, "action_type"=>'serve', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'p'));
                    $hitTurn = 1;
                    $currentScorer = $awayTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                }
            } else if ($v['poe'] == 'e') {
                if ($currentTeam == $homeTeam) {
                    array_push($transcript, array("team"=>$homeTeam, "player_number"=>$currentServer, "action_type"=>'serve', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'e'));
                    $hitTurn = 1;
                    $currentScorer = $awayTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                    $currentTeam = $awayTeam;
                } else {
                    array_push($transcript, array("team"=>$awayTeam, "player_number"=>$currentServer, "action_type"=>'serve', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'e'));
                    $hitTurn = 1;
                    $currentScorer = $homeTeam;
                    $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
                    $currentTeam = $homeTeam;
                }
            } else {
                if ($currentTeam == $homeTeam) {
                    array_push($transcript, array("team"=>$homeTeam, "player_number"=>$currentServer, "action_type"=>'serve', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'1'));
                    $hitTurn = 1;
                    $currentTeam = $awayTeam;
                } else {
                    array_push($transcript, array("team"=>$awayTeam, "player_number"=>$currentServer, "action_type"=>'serve', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'1'));
                    $hitTurn = 1;
                    $currentTeam = $homeTeam;
                }
                $currentPosition = $v['position'];
            }
            break;
        }
    }
    if (!isset($currentPosition)) {
        $currentPosition = "";
    }
    if (!isset($updateScore)) {
        $updateScore = array();
    }
    return array($updateScore, $currentPosition, $currentTeam, $hitTurn, $transcript);
}

function gettingReceive($currentTeam, $currentPosition, $currentServer, $homeTeam, $awayTeam, $transcript, $homeSetScore, $awaySetScore, $homeScore, $awayScore, $hitTurn, $master) {
    $localArray = array();
    
    $bool = 0;
    $i=0;
    foreach ($master as $index=>$row) {
            if ($bool > 0 && $row['action_type'] == 'receive' && $row['team'] == $currentTeam) {
                $c=0;
                while ($c <= $bool) {
                    array_push($localArray, array('key'=>$i, 'player_number'=>$row['player_number'], 'position'=>$row['position'], 'additional_comments'=>$row['additional_comments'], "point"=>$row['hit_turn']));
                    $c++;
                }
            } else {
                $bool = 0;
            }
            if ($row['team'] != $currentTeam && $row['action_type'] == 'serve' && $row['position'] == $currentPosition) {
                $bool++;
            } else if ($row['team'] != $currentTeam && $row['action_type'] == 'serve' && $row['position'] == $currentPosition && $row['player_number'] == $currentServer) {
                $bool++;
            } else {
                $bool = 0;
            }
            $i++;
    }
    $sampleArray = array();
    foreach ($localArray as $i=>$v) {
        array_push($sampleArray, $v['key']);
    }
    $currentPosition = $sampleArray[array_rand($sampleArray)];
    foreach ($localArray as $i=>$v) {
        if ($v['key'] == $currentPosition) {
            if ($v['point'] == 'p') {
                $hitTurn = 1;
                $updateScore = updateScore($homeSetScore, $awaySetScore, $homeScore, $awayScore, $currentScorer, $homeTeam, $awayTeam);
            } else if ($v['additional_comments'] != "") {
                if ($currentTeam == $homeTeam) {
                    $currentTeam = $awayTeam;
                } else {
                    $currentTeam = $homeTeam;
                }
                $hitTurn = 1;
            } else {
                $currentPosition = $v['position'];
                $notPlayer = $v['player_number'];
                $hitTurn++;
            }
            array_push($transcript, array("team"=>$currentTeam, "player_number"=>$v['player_number'], "action_type"=>'receive', "position"=>$v['position'], "set_number"=>$homeSetScore+$awaySetScore, "hitTurn"=>'1'));
            break;
        }
    }
    if (!isset($currentPosition) && !isset($notPlayer)) {
        $currentPosition = "";
        $notPlayer = "";
    }
    if (!isset($updateScore)) {
        $updateScore = array();
    }
    if (!isset($notPlayer)) {
        $notPlayer = "";
    }
    return array($currentPosition, $currentTeam, $notPlayer, $hitTurn, $updateScore, $transcript);
}

function filterHit($conn) {
    $hitTypes = array();
    $getInfo = "SELECT * FROM sportsnow_information WHERE definition = 'hit'";
    if ($gotInfo = mysqli_query($conn, $getInfo)) {
        while ($row = mysqli_fetch_assoc($gotInfo)) {
            array_push($hitTypes, $row['value']);
        }
    }
    return $hitTypes;
}

// To save predictions as a json file:
// $json = json_encode($transcripts);
// file_put_contents("data.json", $json);
?>
<html>
    <head>
        <title>Simulation</title>
        <style>
            body {
                    padding: 0;
                    margin: 0;
                    background: #F7F7F7;
                }
            fieldset {
                border-radius: 5px;
                box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
                border: 0px;
                background: white;
                margin: 10px;
                margin-bottom: 20px;
            }
            .teamLabel {
                border-radius: 5px;
                background: #eeeeee;
                cursor: pointer;
                padding: 5px;
                text-align: center;
                float: left;
                width: calc(33.33% - 20px);
                transition: 0.3s ease;
                margin-bottom: 10px;
            }
            .teamLabel:not(:first-child) {
                margin-right: 10px;
            }
            .teamLabel:hover {
                background: #eaeaea;
            }
            .teamLabel2 {
                border-radius: 5px;
                cursor: pointer;
                padding: 5px;
                text-align: center;
                float: left;
                width: 100px;
                transition: 0.3s ease;
                background: #111;
                color: white;
                margin-top: 10px;
            }
            .teams:checked + label {
              background: #111;
              color: white;
            }
            .teams {
                display: none;
            }
            .actionChoice {
              border-radius: 5px;
              padding: 7px;
              width: 100%;
              margin-top: 10px;
              margin-bottom: 10px;
              border: 2px solid #eaeaea;
            }
            .fieldset_label {
              padding-top: 10px;
              padding-bottom: 5px;
            }
            .left {
                float: left;
                width: 30%;
            }
            .right {
                float: left;
                width: 70%;
            }
            .field {
                border-radius: 5px;
                padding: 20px;
                background: #eaeaea;
            }
            .field .position_group {
                display: flex;
                justify-content: space-between;
            }
            .field .position_group:not(:first-child) {
                margin-top: 15px;
            }
            .field .position_group:last-child {
                justify-content: center;
            }
            .field .position_group .position {
                background: white;
                border-radius: 50%;
                width: 25px;
                aspect-ratio: 1/1;
                padding: 10px;
                text-align: center;
                font-size: 17px;
                transition: 0.3s ease;
            }
            
            .ThirdOfBlock {
                width: 33.33%;
                float: left;
                text-align: center;
            }
            .setList {
                width: 100%;
                float: left;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .setItem {
                float: left;
                margin-left: 10px;
                margin-right: 10px;
                background-color: black;
                padding: 6px;
                text-align: center;
                width: 50px;
                border-radius: 5px;
                color: white;
            }
            .unsetItem {
                float: left;
                margin-left: 10px;
                margin-right: 10px;
                padding: 6px;
                border-radius: 5px;
                width: 50px;
                text-align: center;
                transition: 0.3s ease;
            }
            .unsetItem:hover {
                background-color: #eaeaea;
            }
            .transcript_column {
                width: calc(50% - 2px);
                float: left;
                display: flex;
                justify-content: center;
                align-items: center;
                border: 1px solid #F7F7F7;
            }
            .transcript_column div {
                text-align: center;
            }
            .teamName {
                background: #eeeeee;
                margin-top: 20px;
                margin-bottom: 20px;
                padding-top: 15px;
                padding-bottom: 15px;
                font-size: 1.2rem;
            }
            .circle_pn {
                border-radius: 50%;
                padding: 6px;
            }
            .point {
                font-size: 1.4rem;
                font-weight: 600;
            }
            .transcript_row {
                transition: 0.3s ease;
                width: calc(100% - 20px);
                float: left;
                margin-left: 10px;
                margin-right: 10px;
            }
            .transcript_row2 {
                width: calc(100% - 40px);
                margin-left: 20px;
            }
            .transcript_row2:hover {
                background: #eeeeee;
            }
            a {
                color: black;
            }
            .team_label {
                margin-bottom: 10px;
                float: left;
                width: 100%;
            }
            .selectedGame {
                background-color: #111;
            }
            .unselectedGame {
                cursor: pointer;
                transition: 0.3s ease;
            }
            .unselectedGame:hover {
                background-color: #eeeeee;
            }
        </style>
    </head>
    <body>
        <div class="left">
            <?php
            // Displaying final set scores of each simulation
            foreach ($scoreboard as $i=>$r) {
                if (empty($_GET['game'])) {
                    $game = 0;
                } else {
                    $game = $_GET['game'];
                }
                if ($game == $i) {
                    echo'
                    <fieldset class="selectedGame">
                        <div class="scoreBlock">
                            <div class="ThirdOfBlock">
                                <div>'.$r['homeSetScore'].'</div>
                                <div><small>'.$homeTeam.'</small></div>
                            </div>
                            <div class="ThirdOfBlock">:</div>
                            <div class="ThirdOfBlock">
                                <div>'.$r['awaySetScore'].'</div>
                                <div><small>'.$awayTeam.'</small></div>
                            </div>
                        </div>
                    </fieldset>';
                } else {
                    echo'
                    <fieldset class="unselectedGame">
                        <div class="scoreBlock">
                            <div class="ThirdOfBlock">
                                <div>'.$r['homeSetScore'].'</div>
                                <div><small>'.$homeTeam.'</small></div>
                            </div>
                            <div class="ThirdOfBlock">:</div>
                            <div class="ThirdOfBlock">
                                <div>'.$r['awaySetScore'].'</div>
                                <div><small>'.$awayTeam.'</small></div>
                            </div>
                        </div>
                    </fieldset>';
                }
            }
            ?>
        </div>
        <div class="right">
            <div class="setList">
            <?php
            // Displaying the details of the selected simulation
            $totalSets = 0;
            $game = 0;
            foreach ($scoreboard as $i=>$r) {
                if (empty($_GET['game'])) {
                    $game = 0;
                } else {
                    $game = $_GET['game'];
                }
                if ($i == $game) {
                    $totalSets = $r['homeSetScore'] + $r['awaySetScore'];
                }
            }
            if (empty($_GET['set'])) {
                $set = 1;
            } else {
                $set = $_GET['set'];
            }
            $i=1;
            foreach(range(0, $totalSets) as $ts) {
                if ($set == $i) {
                    echo '<div class="setItem">Set '.$i.'</div>';
                } else {
                    echo '<div class="unsetItem">Set '.$i.'</div>';
                }
                $i++;
            }
            echo '</div>';
            
            echo '
            <div class="transcript_row">
            <div class="transcript_column teamName" style="margin-left: 10px; width: calc(50% - 20px);">'.$homeTeam.'</div>
            <div class="transcript_column teamName" style="margin-left: 20px; width: calc(50% - 20px);">'.$awayTeam.'</div>
            </div>
            ';
            
            $thisSet = $transcripts[$set];
            $homePoint = 0;
            $awayPoint = 0;
            foreach ($thisSet as $i=>$v) {
                $position = $v['position'];
                    echo '
                    <div class="transcript_row transcript_row2" onmouseover="hoverFieldMap(`'.$position.'`)">
                        <div class="transcript_column">';
                            if ($v['team'] == $homeTeam) {
                                if ($v['hitTurn'] == 'p') {
                                    $homePoint++;
                                }
                                echo '
                                <div class="transcript_actionType">'.$v['action_type'].'</div>
                                <div class="transcript_playerNumber"><div class="circle_pn">'.$v['player_number'].'</div></div>
                                ';
                            } else if ($v['hitTurn'] == 'e') {
                                $homePoint++;
                            }
                        echo '
                        </div>

                        <div class="transcript_column">
                        ';
                            if ($v['team'] == $awayTeam) {
                                if ($v['hitTurn'] == 'p') {
                                    $awayPoint++;
                                }
                                echo '
                                <div class="transcript_actionType">'.$v['action_type'].'</div>
                                <div class="transcript_playerNumber"><div class="circle_pn">'.$v['player_number'].'</div></div>
                                ';
                            } else if ($v['hitTurn'] == 'e') {
                                $awayPoint++;
                            }
                            echo '
                        </div>
                    </div>
                    ';
                    
                    if ($v['team'] == $awayTeam && $v['hitTurn'] == 'p') {
                        echo '
                        <div class="transcript_row onmouseover="hoverFieldMap(`'.$position.'`)">
                        <div class="transcript_row">
                        <div class="transcript_column point">'.$homePoint.'</div>
                        <div class="transcript_column point">'.$awayPoint.'</div>
                        </div>
                        </div>
                        ';
                    } else if ($v['team'] != $awayTeam && $v['hitTurn'] == 'p') {
                        echo '
                        <div class="transcript_row onmouseover="hoverFieldMap(`'.$position.'`)">
                        <div class="transcript_row">
                        <div class="transcript_column point">'.$homePoint.'</div>
                        <div class="transcript_column point">'.$awayPoint.'</div>
                        </div>
                        </div>
                        ';
                    } else if ($v['team'] != $awayTeam && $v['hitTurn'] == 'e') {
                        echo '
                        <div class="transcript_row onmouseover="hoverFieldMap(`'.$position.'`)">
                        <div class="transcript_row">
                        <div class="transcript_column point">'.$homePoint.'</div>
                        <div class="transcript_column point">'.$awayPoint.'</div>
                        </div>
                        </div>
                        ';
                    }
            }
            ?>
        </div>
    </body>
</html>
