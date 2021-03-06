<?php
    $host = getenv("DB_HOST");
    $user = getenv("DB_USER");
    $password = getenv("DB_PASS");
    $database = "worldcupdb";

    $table = "";
  
    $db_connection = new mysqli($host, $user, $password, $database);
  
    if ($db_connection->connect_error) {
        die($db_connection->connect_error);
    }
    
    if(isset($_GET['queryType'])) {
        $queryType = $_GET['queryType'];
        if ($queryType === "specificCup") {
            if (isset($_GET['SCQ-year'])) {
                $year = $_GET['SCQ-year'];
                $table = specificCupQuery($db_connection, $year);
            }
        } elseif ($queryType === "specificPlayer") {
            if(isset($_GET['SPQ-player'])) {
                $player = $_GET['SPQ-player'];
                $table = specificPlayerQuery($db_connection, $player);
            }
        } elseif ($queryType === "superstars") {
            $table = superstarsQuery($db_connection);
        } elseif ($queryType === "teamHistorical") {
            if(isset($_GET['THQ-country'])) {
                $country = $_GET['THQ-country'];
                $table = teamHistoricalQuery($db_connection, $country);
            }
        } elseif ($queryType === "countrysPlayers") {
            if(isset($_GET['CPQ-country'])) {
                $country = $_GET['CPQ-country'];
                $table = countrysPlayersQuery($db_connection, $country);
            } 
        } elseif ($queryType === "crestImage") {
            $table = crestImageQuery($db_connection);
        } elseif ($queryType === "goalsAtStadiums") {
            $table = goalsAtStadiumQuery($db_connection);
        } elseif ($queryType === "mostWins") {
            $table = mostWinsQuery($db_connection);
        } elseif ($queryType === "countryRivalry") {
            if (isset($_GET['CRQ-country1']) && isset($_GET['CRQ-country2'])) {
                $countryA = $_GET['CRQ-country1'];
                $countryB = $_GET['CRQ-country2'];
                $table = countryRivalryQuery($db_connection, $countryA, $countryB);
            }
        }
    }

    $db_connection->close();

    $page = generatePage($table, "World Cup DB");
    echo $page;

    function specificCupQuery($db_connection, $year) {
        $table = "<tr><th>Country</th><th>Result</th></tr>";
        $query = "select country.name, countryparticipation.result " .
                "from countryparticipation " .
                "join country " .
                "on countryparticipation.cid = country.id " .
                "where year = ? " .
                "order by countryparticipation.result;";
        
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("s", $year);
    $stmt->execute();
    $stmt->bind_result($result_country, $result_result);

    while ($stmt->fetch()) {
        $table .= "\n\t\t\t<tr><td>" . $result_country . "</td><td>" . $result_result . "</td></tr>";
    }

    $stmt->close();
    return $table;
    }

    function specificPlayerQuery($db_connection, $player) {
        $table = "<tr>" .
                "<th>Player</th>" .
                "<th>Country</th>" .
                "<th>Year</th>" .
                "<th>Position</th>" .
                "<th>Age</th>" .
                "<th>Goals</th>" .
                "</tr>";

        $query = "select player.name as player, country.name as country, rosterspot.year, rosterspot.position, " . 
                    "floor(datediff(str_to_date(concat(firstGame.year, \" \", firstGame.date), \"%Y %d %M\"), " . 
                    "str_to_date(player.birthdate, \"%d %M %Y\")) / 365) as age, rosterspot.goals " .
                 "from rosterspot " .
                 "join player " .
                 "on rosterspot.pid = player.id " .
                 "join country " . 
                 "on rosterspot.cid = country.id " .
                 "join " .
                 "(select year, date " .
                 "from game " .
                 "where id = 1) as firstGame " .
                 "on rosterspot.year = firstGame.year " .
                 "where player.name = ? " .
                 "group by player.name, country.name, rosterspot.year, rosterspot.position, firstGame.year, firstGame.date, player.birthdate, rosterspot.goals;";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("s", $player);
        $stmt->execute();
        $stmt->bind_result($result_player, $result_country, $result_year, 
                            $result_position, $result_age, $result_goals);

        while ($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_player . "</td><td>" . $result_country . "</td><td>" . 
            $result_year . "</td><td>" . $result_position . "</td><td>" . $result_age . "</td><td>" . 
            $result_goals . "</td></tr>";
        }

        $stmt->close();
        return $table;
    }

    function superstarsQuery($db_connection) {
        $table .= "<tr>" .
                  "<th>Player</th>" .
                  "<th>Year</th>" .
                  "<th>Country</th>" .
                  "<th>Goals</th>" .
                  "</tr>";
        $query = "select player.name, rosterspot.year, country.name, rosterspot.goals" .
                 " from rosterspot" .
                 " join player on rosterspot.pid = player.id" .
                 " join country on rosterspot.cid = country.id" .
                 " join" .
                 " (select pid, count(*) as numcups" .
                 " from rosterspot" .
                 " group by pid) as playernumcups" .
                 " on playernumcups.pid = player.id" .
                 " where playernumcups.numcups > 1" .
                 " order by player.name, rosterspot.year;";

        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $stmt->bind_result($result_player, $result_year, $result_country, $result_goals);

        while($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_player . "</td><td>" . $result_year . 
                      "</td><td>" . $result_country . "</td><td>" . $result_goals . "</td></tr>";
        }

        $stmt->close();
        return $table;
    }

    function teamHistoricalQuery($db_connection, $country) {
        $table = "<tr>" .
                 "<th>Country</th>" .            
                 "<th>Number of Cups</th>" .
                 "<th>Total Wins</th>" .
                 "<th>Total Ties</th>" .
                 "<th>Total Losses</th>" .
                 "<th>Total Points</th>" .
                 "<th>Total Goals</th>" .
                 "</tr>";

        $query = "select country.name, countrynumcups.numcups, " . 
                        "coalesce(countrynumwins.totalwins, 0) as totalwins, " .
                        "coalesce(countrynumties.totalties, 0) as totalties, " . 
                        "coalesce(countrynumlosses.totallosses, 0) as totallosses, " . 
                        "(3*coalesce(totalwins, 0) + coalesce(totalties, 0)) as totalpoints, " .
                        "(coalesce(countrywingoals.totWGoals, 0) + coalesce(countrylosegoals.totLGoals, 0)) as totalgoals " . 
                 "from country " . 
                 "left join " .
                 "(select cid, count(*) as numcups " .
                 "from countryparticipation " .
                 "group by cid) as countrynumcups " .
                 "on countrynumcups.cid = country.id " .
                 "left join " .
                 "(select winnerCID, count(*) as totalwins " .
                 "from game " .
                 "where wGoals > lGoals or pkScore != \"\" " .
                 "group by winnerCID) as countrynumwins " .
                 "on countrynumwins.winnerCID = country.id " .
                 "left join (select country.id, count(*) as totalties " .
                 "from country " .
                 "join game " .
                 "on (game.winnerCID = country.id or game.loserCID = country.id) " .
                 "where game.wGoals = game.lGoals and pkScore = \"\" " .
                 "group by country.id, country.name) as countrynumties " .
                 "on countrynumties.id = country.id " .
                 "left join " .
                 "(select loserCID, count(*) as totallosses " .
                 "from game " .
                 "where lGoals < wGoals or pkScore != \"\" " .
                 "group by loserCID) as countrynumlosses " .
                 "on countrynumlosses.loserCID = country.id " .
                 "left join " .
                 "(select country.id, sum(game.wGoals) as totWGoals " .
                 "from game join country on game.winnerCID = country.id " .
                 "group by country.id) as countrywingoals " .
                 "on countrywingoals.id = country.id " .
                 "left join " .
                 "(select country.id, sum(game.lGoals) as totLGoals " .
                 "from game " .
                 "join country " .
                 "on game.loserCID = country.id " .
                 "group by country.id) as countrylosegoals " .
                 "on countrylosegoals.id = country.id " .
                 "where country.name = ? " .
                 "group by country.id, country.name, countrynumcups.numcups, countrynumwins.totalwins, countrynumties.totalties, countrynumlosses.totallosses " .
                 "order by country.id;";

        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("s", $country);
        $stmt->execute();
        $stmt->bind_result($result_country, $result_numCups, $result_wins, 
                            $result_ties, $result_losses, $result_points, $result_goals);  

        if($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_country . "</td><td>" . $result_numCups . 
                      "</td><td>" . $result_wins . "</td><td>" . $result_ties . "</td><td>" . 
                      $result_losses . "</td><td>" . $result_points . "</td><td>" . $result_goals . "</td></tr>";
        } 

        $stmt->close();
        return $table;
    }

    function countrysPlayersQuery($db_connection, $country) {
        $table = "<tr><th>Name</th><th>Country</th><th>Total Goals</th><th>Total PKs</th></tr>";
        $query = "select result2.name, result2.country, result2.gls, result2.pks " . 
                 "from " .
                 "(select player.name, country.name as country, result1.gls, result1.pks " .
                 "from " .
                 "(select rosterspot.pid, rosterspot.cid, sum(rosterspot.goals) as gls, " .
                         "sum(rosterspot.pkScores) as pks " .
                 "from rosterspot " .
                 "group by rosterspot.pid) as result1 " .
                 "join player " .
                 "on player.id = result1.pid " .
                 "join country " .
                 "on result1.cid = country.id " .
                 "where result1.gls > 0 ) as result2 " .
                 "where result2.country like ? " .
                 "order by result2.gls desc;";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("s", $country);
        $stmt->execute();
        $stmt->bind_result($result_player, $result_country, $result_goals, $result_pks); 

        while($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_player . "</td><td>" . $result_country . 
                      "</td><td>" . $result_goals . "</td><td>" . $result_pks . "</td></tr>";
        }

        $stmt->close();
        return $table;
    }

    function crestImageQuery($db_connection) {
        $table = "<tr><th>Country</th><th>Crest</th></tr>";
        $query = "select name, crestURL from country;";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $stmt->bind_result($result_country, $result_crestURL);

        while($stmt->fetch()) {
            $crestElem = "<img src=\"" . $result_crestURL . "\" />";
            $table .= "\n\t\t\t<tr><td>" . $result_country . "</td><td>" . $crestElem . "</td></tr>";
        }

        $stmt->close();
        return $table;
    }

    function goalsAtStadiumQuery($db_connection) {
        $table = "<tr><th>Stadium</th><th>City</th><th>Country</th>" .
                 "<th>Total Goals</th><th>Goals per Game</th></tr>";
        $query = "select result1.name, result1.city, country.name as country, " .
                 "result1.totGoals, result1.GoalsPerGame " .
                 "from " .
                 "(select stadium.name, stadium.city, stadium.cid, " .
                          "IDGoals.totGoals, " .
                          "IDGoals.totGoals / IDGoals.totGames as GoalsPerGame " .
                  "from " .
                    "(select game.stadID, sum(game.wGoals + game.lGoals) as totGoals, count(*) as totGames " .
                    " from game " .
                    " group by game.stadID) as IDGoals " .
                  "right join stadium " .
                  "on IDGoals.stadID = stadium.id) as result1 " .
                  "left join country " .
                  "on country.id = result1.cid " .
                  "order by result1.totGoals desc;";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $stmt->bind_result($result_stadium, $result_city, $result_country, $result_goals, $result_gpg);

        while($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_stadium . "</td><td>" . 
                      $result_city . "</td><td>" . $result_country . "</td><td>" . 
                      $result_goals . "</td><td>" . $result_gpg . "</td></tr>";
        }

        $stmt->close();
        return $table;
    }

    function mostWinsQuery($db_connection) {
        $table = "<tr><th>Country</th><th>Total Wins</th></tr>";
        $query = "select country.name, result1.totWins " .
                 "from " .
                 "(select countryparticipation.cid, count(*) as totWins " .
                 "from countryparticipation " .
                 "where result = 1 " .
                 "group by countryparticipation.cid) as result1 " .
                 "join country " .
                 "on country.id = result1.cid " .
                 "order by result1.totWins desc;";
        $stmt = $db_connection->prepare($query);
        $stmt->execute();
        $stmt->bind_result($result_country, $result_wins);

        while($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_country . 
                      "</td><td>" . $result_wins;
        }

        $stmt->close();
        return $table;
    }

    function countryRivalryQuery($db_connection, $countryA, $countryB) {
        $table = "<tr><th>Year</th><th>Round</th><th>Stadium</th>" .
            "<th>Winner</th><th>Score</th><th>Loser</th>" .
            "<th>PK Score</th></tr>";
        $query = "select game.year, game.round, stadium.name as sname, " .
                 "winningCountry.name as winner, concat(concat(game.wGoals,'-'),game.lGoals) as score, " .
                 "losingCountry.name as loser, game.pkScore " .
                 "from game " .
                 "left join stadium on stadium.id = game.stadID " .
                 "left join ( " .
                     "select id, name " .
                     "from country " .
                 ") as winningCountry on winningCountry.id = game.winnerCID " .
                 "left join ( " .
                     "select id, name " .
                     "from country " .
                 ") as losingCountry on losingCountry.id = game.loserCID " .
                 "where (winningCountry.name = ? and losingCountry.name = ?) or (winningCountry.name = ? and losingCountry.name = ?) " .
                 "order by game.year, game.id;";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("ssss", $countryA, $countryB, $countryB, $countryA);
        $stmt->execute();
        $stmt->bind_result($result_year, $result_round, $result_stadium, 
                             $result_winner, $result_score, $result_loser,
                             $result_pkScore); 

        while($stmt->fetch()) {
            $table .= "\n\t\t\t<tr><td>" . $result_year . "</td><td>" . $result_round . 
                     "</td><td>" . $result_stadium . "</td><td>" . $result_winner . 
                     "</td><td>" . $result_score . "</td><td>" . $result_loser . 
                     "</td><td>" . $result_pkScore . "</td></tr>";
        }

        $stmt->close();
        return $table;

      }

      function generatePage($body, $title) {
          $page = <<<EOPAGE
<!doctype html>
<html>
    <head>
      <meta charset="utf-8" />
      <title>$title</title>
      <link rel="stylesheet" href="query_response.css" type="text/css" />
    </head>

    <body>
      <h1> Mondial DB: A World Cup Database </h1>
      <form action="index.html">
        <table>
          $body
        </table>
        <input type="submit" value="Return Home" />
      </form>
    </body>
</html>
EOPAGE;

        return $page;
      }
?>