<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * throughtheagesmobilereadability implementation : © Gregory Isabelli <gisabelli@boardgamearena.com> & Romain Fromi <romain.fromi@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * throughtheagesmobilereadability.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */


require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');


class throughtheagesmobilereadability extends Table
{
    function __construct()
    {


        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        self::initGameStateLabels(array(
            'firstPlayer' => 10,
            'currentAge' => 11,        // From 0 (=A) to 4 (= IV, end phase of Complete version)
            'turnNumber' => 14,
            'currentCardEffectType' => 15,
            'cardBuilt' => 18,
            'activePlayerBeforeEffect' => 19,
            'sacrificedInf' => 20,
            'sacrificedCav' => 21,
            'sacrificedArty' => 22,
            'saved_player' => 23,
            'sacrificedAir' => 24,
            'completeLastTurn' => 25,
            'civilCardInitial' => 26,
            'sacrificedTot' => 27,
            'undoSavedPlayer' => 28,
            'colonization_strength' => 30,

            'game_version' => 100,   // 1 = simple, 2 = advanced, 3 = complete
            'peaceful_variant' => 101   // 1 = aggressive (normal), 2 = peaceful
        ));

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName()
    {
        return "throughtheagesmobilereadability";
    }

    /*
        setupNewGame:

        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = array())
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $default_colors = array("ff0000", "008000", "0000ff", "ffa500", "773300");

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, array("ff0000", "008000", "0000ff", "ffa500", "773300"));
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        $players = self::loadPlayersBasicInfos();

        self::setGameStateInitialValue('currentAge', 0);    // Age A
        self::setGameStateInitialValue('turnNumber', 0);
        self::setGameStateInitialValue('currentCardEffectType', 0);
        self::setGameStateInitialValue('cardBuilt', 0);
        self::setGameStateInitialValue('activePlayerBeforeEffect', 0);

        self::setGameStateInitialValue('colonization_strength', 0);
        self::setGameStateInitialValue('sacrificedInf', 0);
        self::setGameStateInitialValue('sacrificedCav', 0);
        self::setGameStateInitialValue('sacrificedArty', 0);
        self::setGameStateInitialValue('sacrificedAir', 0);
        self::setGameStateInitialValue('sacrificedTot', 0);

        self::setGameStateInitialValue('saved_player', 0);
        self::setGameStateInitialValue('completeLastTurn', 0);
        self::setGameStateInitialValue('undoSavedPlayer', 0);

        // Card creation ///////////////////////////////////////////////
        self::setupAge('A');

        // Set a player as First Player
        $firstPlayer = self::activeNextPlayer();
        self::setGameStateInitialValue('firstPlayer', $firstPlayer);

        /// Init tokens
        $cards = $this->cards->getCardsInLocation('tableau'); // Get all cards in tableau to associate tokens with cards

        $sql = "INSERT INTO token (token_player_id,token_type,token_card_id) VALUES ";
        $sql_values = array();

        foreach ($players as $player_id => $player) {
            // 18 yellow token on bank
            for ($i = 0; $i < 18; $i++) {
                $sql_values[] = "('$player_id','yellow',NULL)";
            }
            //  16 blue token on bank
            for ($i = 0; $i < 16; $i++) {
                $sql_values[] = "('$player_id','blue',NULL)";
            }

            // 1 yellow token in reserve
            $sql_values[] = "('$player_id','yellow','0')";  // Note: 0 = reserve

            // Yellow tokens on initial cards
            $initial_cards_to_token = array('Philosophy' => 1, 'Warriors' => 1, 'Bronze' => 2, 'Agriculture' => 2);
            foreach ($cards as $card) {
                if ($card['location_arg'] == $player_id && isset($initial_cards_to_token[$this->card_types[$card['type']]['name']])) {
                    $token_nbr = $initial_cards_to_token[$this->card_types[$card['type']]['name']];
                    for ($i = 0; $i < $token_nbr; $i++) {
                        $sql_values[] = "('$player_id','yellow','" . $card['id'] . "')";
                    }
                }
            }
        }

        $sql .= implode(',', $sql_values);
        self::DbQuery($sql);

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        self::initStat('table', 'turns_number', 0);    // Init a table statistics
        self::initStat('player', 'points_culture_progression', 0);
        self::initStat('player', 'points_cards_effects', 0);
        self::initStat('player', 'points_final_scoring', 0);
        self::initStat('player', 'points_hunger', 0);
        self::initStat('player', 'points_events', 0);
        self::initStat('player', 'score_end_of_I', 0);
        self::initStat('player', 'score_end_of_II', 0);
        self::initStat('player', 'tech_discovered', 0);
        self::initStat('player', 'wonder_achieved', 0);
        self::initStat('player', 'govt_change', 0);
        self::initStat('player', 'event_placed', 0);
        self::initStat('player', 'tactic_played', 0);
        self::initStat('player', 'special_played', 0);
        self::initStat('player', 'aggression_played', 0);
        self::initStat('player', 'aggression_targeted', 0);
        self::initStat('player', 'aggression_won', 0);
        self::initStat('player', 'pacts', 0);
        self::initStat('player', 'territory', 0);
        self::initStat('player', 'uprising', 0);
        self::initStat('player', 'pick_card_action', 0);
        self::initStat('player', 'military_cards_picked', 0);
        self::initStat('player', 'build_action', 0);
        self::initStat('player', 'build_military_action', 0);
        self::initStat('player', 'action_card', 0);
        self::initStat('player', 'final_pop', 0);
        self::initStat('player', 'final_strength', 0);
        self::initStat('player', 'total_science_prod', 0);
        self::initStat('player', 'total_food_prod', 0);
        self::initStat('player', 'total_resource_prod', 0);
        self::initStat('player', 'total_corruption_lost', 0);
        self::initStat('player', 'total_food_consumption', 0);

        self::refillCardRow($firstPlayer);


        /************ End of the game initialization *****/
    }

    function setupAge($age)
    {
        $nb_players = self::getUniqueValueFromDB("SELECT COUNT(*) FROM player WHERE player_eliminated = 0");
        $game_version = self::getGameStateValue('game_version');
        $peaceful_variant = self::getGameStateValue('peaceful_variant');
        $players = self::loadPlayersBasicInfos();

        $deck_card_set = array(
            'playerInit' => array(),
            'civil' => array(),
            'military' => array()
        );

        foreach ($this->card_types as $type_id => $card_type) {
            if ($card_type['age'] == $age) {
                // Organize cards in decks by Age, with distinction between civil and military
                $deck_name = self::isCardCategoryCivil($card_type['category']) ? 'civil' : 'military';

                if (isset($card_type['init'])) {
                    $deck_name = 'playerInit';  // + 6 Initial technology / players
                    $nbr = 1;
                } else {
                    // Adapt cards depending on number of players
                    $nbr = $card_type['qt' . $nb_players];
                }

                if ($game_version == 1 || $peaceful_variant == 2) {
                    // Handbook version or Peaceful variant: no wars, no aggressions, no pacts
                    if ($card_type['type'] == 'War' || $card_type['type'] == 'Aggression' || $card_type['type'] == 'Pact')
                        $nbr = 0;
                } else if ($nb_players == 2 && $card_type['type'] == 'Pact') {
                    $nbr = 0;
                }

                if ($nbr > 0)
                    $deck_card_set[$deck_name][$type_id] = array('type' => $type_id, 'type_arg' => 0, 'nbr' => $nbr);
            }
        }

        foreach ($deck_card_set as $deck_name => $card_list) {
            if ($deck_name == 'playerInit') {
                foreach ($players as $player_id => $player) {
                    $this->cards->createCards($card_list, 'tableau', $player_id);
                }
            } else if ($deck_name == 'military' && $age == 'A') {
                // Take only nb_player+2 cards from this deck
                // Shuffle deck and let only $nb_players+2 cards on it
                $this->cards->createCards($card_list, 'events');
                $this->cards->shuffle('events');
                $nbr_to_keep = $nb_players + 2;
                $nbr_to_remove = 10 - $nbr_to_keep;
                $this->cards->pickCardsForLocation($nbr_to_remove, 'events', 'removed');
            } else if ($this->cards->countCardInLocation($deck_name . $age) == 0) {
                $this->cards->createCards($card_list, $deck_name . $age);
            }

            $this->cards->shuffle($deck_name . $age);
        }

        self::setGameStateInitialValue('civilCardInitial', $this->cards->countCardInLocation('civil' . $age));
    }

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array('players' => array());

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score,
                player_science_points science_points, player_science science, player_culture culture,
                player_strength strength, player_happy happy, player_discontent discontent, player_tactic tactic
                FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // Gather all information about current game situation (visible by player $current_player_id).

        $result['card_types'] = $this->card_types;

        $result['card_row'] = $this->cards->getCardsInLocation('card_row');
        foreach ($result['card_row'] as &$card) {
            if ($this->canPickCard($current_player_id, $card)) {
                $card['costToPick'] = $this->getCostToPickCard($current_player_id, $card);
            } else {
                $card['costToPick'] = 'X';
            }
        }

        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);
        $result['tableau'] = $this->cards->getCardsInLocation('tableau'); // Note all tableau
        $result['under_card'] = $this->cards->getCardsInLocation('under_card');
        $result['common_tactics'] = $this->cards->getCardsInLocation('common_tactics');

        foreach ($result['players'] as $player_id => &$player) {
            $player['colonizationModifier'] = self::getPlayerMaxBid($player_id, true);
            $food_production = self::getFoodNetProduction($player_id);
            if ($food_production >= 0) {
                $player['foodProduction'] = "+" . $food_production;
            } else {
                $player['foodProduction'] = "" . $food_production;
            }
            $resource_production = self::getResourceProduction($player_id);
            if ($resource_production >= 0) {
                $player['resourceProduction'] = "+" . $resource_production;
            } else {
                $player['resourceProduction'] = "" . $resource_production;
            }
            $tacticsInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Tactics');
            if (count($tacticsInPlay) == 0 && $player['tactic'] != 0) {
                $tacticCopy = $this->cards->getCard($player['tactic']);
                $tacticCopy['location'] = 'tableau';
                $tacticCopy['location_arg'] = strval($player_id);
                array_push($result['tableau'], $tacticCopy);
            }
        }

        $result['tokens'] = self::getObjectListFromDb("SELECT token_id id, token_player_id player, token_type type, token_card_id card_id from token");

        $currentAge = self::getGameStateValue('currentAge');
        $current_age_char = self::ageNumToChar($currentAge);

        $result['age'] = $current_age_char;
        $result['remaining_cards'] = $this->cards->countCardInLocation('civil' . $current_age_char);
        $result['remaining_cards_mil'] = $this->cards->countCardInLocation('military' . $current_age_char);

        $result['game_version'] = self::getGameStateValue('game_version');

        $result['war'] = self::getObjectListFromDB("SELECT war_card_id id, war_attacker attacker, war_defender defender, card_type war_type
                FROM war
                INNER JOIN card ON card_id=war_card_id
                WHERE war_attacker='$current_player_id' OR war_defender='$current_player_id'");

        $result['events'] = self::getEventDecksComposition();
        $result['adv_events'] = $this->cards->getCardsInLocation('advanced_events');

        // Count all cards in hand
        $result['cards_in_hand'] = self::getCardsInHand();

        // Clevus
        $result['civil_cards_in_hand'] = self::getCivilCardsInHand();

        return $result;
    }


    /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with the "updateGameProgression" property set to true
        (see states.inc.php)
    */
    function getGameProgression()
    {
        $current_age = self::getGameStateValue('currentAge');
        if ($current_age == 0) {
            return 0;
        }
        $game_version = self::getGameStateValue('game_version');

        if ($game_version == 1 && $current_age > 2 || $current_age > 3) {
            return 100;
        }

        $current_age_char = self::ageNumToChar($current_age);

        $remaining = $this->cards->countCardInLocation('civil' . $current_age_char);
        $initial = self::getGameStateValue('civilCardInitial');

        if ($game_version == 1) {
            $maxAge = 2;
        } else {
            $maxAge = 3;
        }

        $fractionProgress = ($current_age - 1) / $maxAge + (1 - $remaining / $initial) * 1 / $maxAge;

        return round($fractionProgress * 100);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    function isCardCategoryCivil($card_type)
    {
        if ($card_type == 'Production'
            || $card_type == 'Urban'
            || $card_type == 'Military'
            || $card_type == 'Wonder'
            || $card_type == 'Leader'
            || $card_type == 'Govt'
            || $card_type == 'Special'
            || $card_type == 'Action') {
            return true;
        } else
            return false;
    }

    function ageNumToChar($ageNum)
    {
        if ($ageNum == 0)
            return 'A';
        else if ($ageNum == 1)
            return 'I';
        else if ($ageNum == 2)
            return 'II';
        else if ($ageNum == 3)
            return 'III';
        else if ($ageNum == 4)
            return 'IV';
        else
            throw new feException("Wrong numeric age");
    }

    function ageCharToNum($ageChar)
    {
        if ($ageChar == 'A')
            return 0;
        else if ($ageChar == 'I')
            return 1;
        else if ($ageChar == 'II')
            return 2;
        else if ($ageChar == 'III')
            return 3;
        else if ($ageChar == 'IV')
            return 4;
        else
            throw new feException("Wrong character age");
    }

    function getCardsOfTypeFromTableau($player_id, $card_type)
    {
        $result = array();
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['type'] == $card_type)
                $result[] = $card;
        }
        return $result;
    }

    function getCardsOfCategoryFromTableau($player_id, $card_category)
    {
        $result = array();
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == $card_category)
                $result[] = $card;
        }
        return $result;
    }

    function isCardInTableau($player_id, $card_type_id)
    {
        $cards = $this->cards->getCardsOfTypeInLocation($card_type_id, null, 'tableau', $player_id);

        if (count($cards) == 0)
            return false;

        $card = reset($cards);

        if ($this->card_types[$card_type_id]['category'] == 'Wonder') {
            // Must check if build
            if ($card['type_arg'] != 0)
                return false;
        }

        // Okay, card is there
        return true;
    }

    function hasBasilica($player_id)
    {
        return self::isCardInTableau($player_id, 95);
    }

    function getCardsOfCategoryFromHand($player_id, $card_category)
    {
        $result = array();
        $cards = $this->cards->getCardsInLocation('hand', $player_id);
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == $card_category)
                $result[] = $card;
        }
        return $result;
    }

    function getIncreasePopulationCost($player_id)
    {
        // Get number of yellow token on board
        $yellow_tokens = self::getUniqueValueFromDB("SELECT COUNT( token_id )FROM token
            WHERE token_type='yellow' AND token_card_id IS NULL AND token_player_id='$player_id'");

        if ($yellow_tokens > 30)
            $cost = 0;
        else
            $cost = $this->population_cost[$yellow_tokens];

        // Cards that have effect on this cost: Moses
        if (self::isCardInTableau($player_id, 26))
            $cost--;

        return max(0, $cost);
    }

    function getBlueTokenTotalValue($player_id, $type)
    {
        if ($type == 'food') {
            // Card types with food
            $card_types = array(3, 69, 172, 228);   // Farm, Irrigation, Selective breeding, Mechanized Agriculture
        } else if ($type == 'ress') {
            // Card types with resources
            $card_types = array(6, 38, 68, 171, 120, 196, 239); //   Bronze, Iron, Coal, Oil  + Labs
        } else
            throw new feException("token type error: " . $type);

        // Get available cards on player tableau
        $cards_type_to_id = self::getCollectionFromDB("SELECT card_type, card_id
            FROM card
            WHERE card_location='tableau' AND card_location_arg='$player_id'
            AND card_type IN ('" . implode("','", $card_types) . "')", true);

        $cards_id_to_type = array_flip($cards_type_to_id);

        // Get blue tokens on these cards
        $blue_tokens = self::getObjectListFromDB("SELECT token_id, token_card_id
            FROM token
            WHERE token_type='blue' AND token_card_id IN ('" . implode("','", $cards_type_to_id) . "')");

        $total_items = 0;
        $item_value_to_token_list = array(
            1 => array(), 2 => array(), 3 => array(), 5 => array()
        );

        foreach ($blue_tokens as $blue_token) {
            $food_per_token = $this->card_types[$cards_id_to_type[$blue_token['token_card_id']]][$type];

            if ($this->card_types[$cards_id_to_type[$blue_token['token_card_id']]]['type'] == 'Lab')
                $food_per_token = self::ageCharToNum($this->card_types[$cards_id_to_type[$blue_token['token_card_id']]]['age']);

            $item_value_to_token_list[$food_per_token][] = $blue_token['token_id'];
            $total_items += $food_per_token;
        }
        return $total_items;
    }

    // Spend given number of blue tokens (food or resources),
    // and optimize the number of tokens spent
    function spendBlueTokens($player_id, $type, $nbr, $bDontFailIfNotEnough = false)
    {
        // Trade Routes Agreement
        if ($type == 'food') {
            if (self::isCustomEffectActive(1101, 0)) {
                // Use 1 resource insteand
                if (self::spendBlueTokens($player_id, 'ress', 1, true) == 1) {
                    // success !
                    self::notifyAllPlayers("simpleNote", clienttranslate('Using ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[1101]['name']
                    ));
                    self::DbQuery("UPDATE currenteffects SET effect_arg='1' WHERE effect_card_type='1101'");
                    $nbr--;
                    if ($nbr == 0)
                        return;
                }
            }
        } else if ($type == 'ress') {
            if (self::isCustomEffectActive(101, 0)) {
                // Use 1 resource insteand
                if (self::spendBlueTokens($player_id, 'food', 1, true) == 1) {
                    // success !
                    self::notifyAllPlayers("simpleNote", clienttranslate('Using ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[101]['name']
                    ));
                    self::DbQuery("UPDATE currenteffects SET effect_arg='1' WHERE effect_card_type='101'");
                    $nbr--;
                    if ($nbr == 0)
                        return;
                }
            }
        }

        if ($type == 'food') {
            // Card types with food
            $card_types = array(3, 69, 172, 228);   // Farm, Irrigation, Selective breeding, Mechanized Agriculture
        } else if ($type == 'ress') {
            // Card types with resources
            $card_types = array(6, 38, 68, 171, 120, 196, 239); //   Bronze, Iron, Coal, Oil + Labs
        } else
            throw new feException("token type error: " . $type);

        // Get available cards on player tableau
        $cards_type_to_id = self::getCollectionFromDB("SELECT card_type, card_id
            FROM card
            WHERE card_location='tableau' AND card_location_arg='$player_id'
            AND card_type IN ('" . implode("','", $card_types) . "')", true);

        $cards_id_to_type = array_flip($cards_type_to_id);

        // Get blue tokens on these cards
        $blue_tokens = self::getObjectListFromDB("SELECT token_id, token_card_id
            FROM token
            WHERE token_type='blue' AND token_card_id IN ('" . implode("','", $cards_type_to_id) . "')");

        $total_items = 0;
        $item_value_to_token_list = array(
            1 => array(), 2 => array(), 3 => array(), 5 => array()
        );

        foreach ($blue_tokens as $blue_token) {
            $food_per_token = $this->card_types[$cards_id_to_type[$blue_token['token_card_id']]][$type];

            if ($this->card_types[$cards_id_to_type[$blue_token['token_card_id']]]['type'] == 'Lab')
                $food_per_token = self::ageCharToNum($this->card_types[$cards_id_to_type[$blue_token['token_card_id']]]['age']);

            $item_value_to_token_list[$food_per_token][] = $blue_token['token_id'];
            $total_items += $food_per_token;
        }

        if ($nbr > $total_items) {
            if ($bDontFailIfNotEnough) {
                $nbr = $total_items;
            } else {
                if ($type == 'food')
                    throw new feException(self::_("You don't have enough food to do this"), true);
                else
                    throw new feException(self::_("You don't have enough resources to do this"), true);
            }
        }

        // Okay, there are enough items. Now, consume lower tokens in priority.

        $moveTokens = array();

        $rest_to_pay = $nbr;
        $limit = 99;
        while ($rest_to_pay > 0) {
            foreach ($item_value_to_token_list as $item_value => $token_ids)    // Starts with tokens on lower values
            {
                while (count($item_value_to_token_list[$item_value]) > 0 && $rest_to_pay > 0) {   // There are tokens with this value

                    // ... take the first one
                    $token_id = array_pop($item_value_to_token_list[$item_value]);

                    $rest_to_pay -= $item_value;
                    $moveTokens[] = array('id' => $token_id, 'card_id' => null, 'player' => $player_id, 'type' => 'blue');
                    self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_id='$token_id'");
                }
            }

            $limit--;
            if ($limit < 0)
                throw new feException("infinite loop during spendBlueTokens");
        }

        self::notifyAllPlayers('moveTokens', '', array('tokens' => $moveTokens));

        $waste = abs($rest_to_pay);
        if ($waste > 0) {
            // Make "change" to credit again resources if possible
            self::getBlueTokens($player_id, $type, $waste);
        }

        return $nbr; // Number of resources indeed consumed.
    }

    // Get some blue tokens (food or resources)
    function getBlueTokens($player_id, $type, $nbr, $notification = '', $notifArgs = array())
    {
        $hasBillGates = false;

        if ($type == 'food') {
            // Card types with food
            $card_types = array(228, 172, 69, 3);   // Farm, Irrigation, Selective breeding, Mechanized Agriculture
        } else if ($type == 'ress') {
            // Card types with resources
            $card_types = array(239, 120, 68, 6); //   Bronze, Iron, Coal, Oil

            if (self::isCardInTableau($player_id, 238))  // Bill Gates
            {
                $hasBillGates = true;
                $card_types = array(239, 196, 120, 171, 68, 38, 6);
            }
        } else
            throw new feException("token type error: " . $type);

        // We must find the best manner to add the blue token according to player's available technologies

        // Get available cards on player tableau
        $cards_type_to_id = self::getCollectionFromDB("SELECT card_type, card_id
            FROM card
            WHERE card_location='tableau' AND card_location_arg='$player_id'
            AND card_type IN ('" . implode("','", $card_types) . "')", true);

        array_flip($cards_type_to_id);

        // Get available blue tokens
        $available_blue = self::getObjectListFromDb("SELECT token_id FROM token
                WHERE token_player_id='$player_id'
                AND token_card_id IS NULL
                AND token_type='blue' ", true);

        $tokens_to_move_datas = array();

        // Best manner = always place the item on the most upper card according to remaining to add
        $rest_to_get = $nbr;
        $limit = 99;
        $bFirstBlueLimitation = false;
        while ($rest_to_get > 0) {
            foreach ($card_types as $card_type) {
                if (isset($cards_type_to_id[$card_type])) {
                    $card_item_value = $this->card_types[$card_type][$type];

                    if ($hasBillGates && $this->card_types[$card_type]['type'] == 'Lab')
                        $card_item_value = self::ageCharToNum($this->card_types[$card_type]['age']);

                    if ($card_item_value > $rest_to_get) {
                        // This is too much for this card => can't do anything
                    } else {
                        while ($rest_to_get >= $card_item_value) {
                            $rest_to_get -= $card_item_value;

                            // Add a token on this card
                            if (count($available_blue) > 0) {
                                $token_id = array_pop($available_blue);
                                $tokens_to_move_datas[] = array(
                                    'id' => $token_id,
                                    'player' => $player_id,
                                    'type' => 'blue',
                                    'card_id' => $cards_type_to_id[$card_type]
                                );

                                $sql = "UPDATE token SET token_card_id='" . $cards_type_to_id[$card_type] . "'
                                        WHERE token_id = '$token_id'";
                                self::DbQuery($sql);
                            } else {
                                if (!$bFirstBlueLimitation) {
                                    $bFirstBlueLimitation = true;
                                    self::notifyAllPlayers('notEnoughBlue', clienttranslate("There are not enough blue tokens to produce all your resource/food"), array());
                                }
                            }
                        }
                    }
                }
            }

            $limit--;
            if ($limit < 0)
                throw new feException("infinite loop during getBlueTokens");
        }

        if (count($tokens_to_move_datas) > 0) {
            $notifArgs['tokens'] = $tokens_to_move_datas;
            self::notifyAllPlayers('moveTokens', $notification, $notifArgs);
        }
    }

    function spendCivilActions($player_id, $quantity)
    {
        $actionsLeft = self::getUniqueValueFromDB("SELECT player_civil_actions FROM player WHERE player_id = '$player_id'");
        if (self::isEffectActive(1020)) {
            try {
                self::spendMilitaryActions($player_id, 1);
                self::removeEffect(1020);
                $quantity--;
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} uses a military action as a civil action using Hammurabi.'), array('player_name' => self::getActivePlayerName()));
            } catch (feException $ex) {
                // Ignore and try to spend only civil actions when no military actions left.
            }
        }
        if ($quantity > $actionsLeft) {
            throw new feException(sprintf(self::_("You need %s Civil Actions to do this"), $quantity), true);
        }
        self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used + $quantity, player_civil_actions = player_civil_actions - $quantity WHERE player_id = '$player_id'");
    }

    function spendMilitaryActions($player_id, $quantity)
    {
        $actionsLeft = self::getUniqueValueFromDB("SELECT player_military_actions FROM player WHERE player_id = '$player_id'");
        if ($quantity > $actionsLeft) {
            throw new feException(sprintf(self::_("You need %s Military Actions to do this"), $quantity), true);
        }
        self::dbQuery("UPDATE player SET player_military_actions_used = player_military_actions_used + $quantity, player_military_actions = player_military_actions - $quantity WHERE player_id = '$player_id'");
    }

    // Adjust players indicators to current states of buildings
    function adjustPlayerIndicators($player_id)
    {
        $indicators = array('strength' => 0, 'culture' => 0, 'science' => 0, 'happy' => 0);

        $workers = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

        $bBasilica = self::hasBasilica($player_id);

        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        $cardsUnder = $this->cards->getCardsInLocation('under_card');
        $homer = reset($cardsUnder);

        $indicators['strength'] += self::getPlayerStrengthIndicator($player_id, $cards, $workers);
        $indicators['culture'] += self::getPlayerIndicator($player_id, 'culture', $cards, $workers, $homer, $bBasilica);
        $indicators['science'] += self::getPlayerIndicator($player_id, 'science', $cards, $workers, $homer, $bBasilica);
        $indicators['happy'] += self::getPlayerIndicator($player_id, 'happy', $cards, $workers, $homer, $bBasilica);
        $indicators['colonizationModifier'] = self::getPlayerMaxBid($player_id, true);

        $food_production = self::getFoodNetProduction($player_id);
        if ($food_production >= 0) {
            $indicators['foodProduction'] = "+" . $food_production;
        } else {
            $indicators['foodProduction'] = "" . $food_production;
        }
        $resource_production = self::getResourceProduction($player_id);
        if ($resource_production >= 0) {
            $indicators['resourceProduction'] = "+" . $resource_production;
        } else {
            $indicators['resourceProduction'] = "" . $resource_production;
        }

        $indicators['discontent'] = self::countDiscontent($player_id, $indicators['happy']);

        $sql = "UPDATE player SET
                player_strength='" . $indicators['strength'] . "',
                player_culture='" . $indicators['culture'] . "',
                player_science='" . $indicators['science'] . "',
                player_happy='" . $indicators['happy'] . "',
                player_discontent='" . $indicators['discontent'] . "'
                WHERE player_id='$player_id'";
        self::DbQuery($sql);

        self::notifyAllPlayers('adjustIndicators', '', array('player_id' => $player_id, 'indicators' => $indicators));
    }

    function getPlayerStrengthIndicator($player_id, $cards, $workers)
    {
        $strength = self::getPlayerIndicator($player_id, 'strength', $cards, $workers);
        $player_tactic = self::getUniqueValueFromDB("SELECT player_tactic FROM player WHERE player_id = '$player_id'");
        if ($player_tactic !== null) {
            $armies = self::countArmyNumber($workers, $this->cards->getCard($player_tactic)['type'], $player_id);
            $strength += $armies['points'];
        }
        return $strength;
    }

    function getPlayerIndicator($player_id, $indicator_id, $cards, $workers, $homer = null, $bBasilica = false)
    {
        $result = self::getWorkersIndicator($workers, $indicator_id, $bBasilica) + self::getCardsIndicator($cards, $indicator_id, $player_id, $workers, $homer, $bBasilica);
        return max(0, $result);
    }

    // Update indicators with workers
    function getWorkersIndicator($workers, $indicator_id, $bBasilica = false)
    {
        $result = 0;
        foreach ($workers as $worker) {
            $card_type = $this->card_types[$worker['card_type']];
            $result += $card_type[$indicator_id];
            if ($card_type[$indicator_id] > 0 && $indicator_id == 'happy' && $bBasilica)
                $result++;
        }
        return $result;
    }

    // Update indicators with other cards
    function getCardsIndicator($cards, $indicator_id, $player_id, $workers, $homer = null, $bBasilica = false)
    {
        $result = 0;
        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];

            if (($card_type['category'] == 'Wonder' && $card['type_arg'] != 1)    // finished Wonder
                || $card_type['category'] == 'Leader'
                || $card_type['category'] == 'Special'
                || $card_type['category'] == 'Govt'
                || $card_type['type'] == 'Territory'
                || $card_type['type'] == 'Pact'
            ) {
                $card_indicator = isset($card_type[$indicator_id]) ? $card_type[$indicator_id] : 0;
                if ($card_type['category'] == 'Wonder' && $card['type_arg'] == 2) { // Ravaged Wonder
                    $card_indicator = $indicator_id == 'culture' ? 2 : 0;
                }
                if ($card_indicator === "?") {
                    $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'get' . $indicator_id);
                    $delta = $this->$method_name($player_id, $workers, $cards);
                } else {
                    $delta = $card_indicator;
                }
                if ($homer && $homer['location_arg'] == $card['id'] && $indicator_id == 'happy') {
                    $delta++;
                }
                if ($indicator_id == 'happy' && $bBasilica && $delta > 0 && $card['type'] != 95) {
                    $delta++;
                }
                $result += $delta;
            }
        }
        return $result;
    }

    function countDiscontent($player_id, $happy)
    {
        // Count number of yellow in bank
        $yellow_bank = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token
            WHERE token_player_id='$player_id' AND token_type='yellow' AND token_card_id IS NULL");

        $happiness_req = self::getHappinessRequirement($yellow_bank);

        if ($happiness_req > $happy)
            return $happiness_req - $happy;
        else
            return 0;
    }

    function getHappinessRequirement($yellow_bank)
    {
        if (isset($this->population_to_happiness_requirement[$yellow_bank]))
            return $this->population_to_happiness_requirement[$yellow_bank];
        else
            return 0;
    }

    function countArmyNumber($buildings, $tactic_card_id, $player_id, $bForSacrifice = false)
    {
        $tactic_type = $this->card_types[$tactic_card_id];
        $army_types = array('inf' => 0, 'cav' => 0, 'arty' => 0, 'air' => 0);
        $army_types_correctage = array('inf' => 0, 'cav' => 0, 'arty' => 0, 'air' => 0);

        $tactic_age = self::ageCharToNum($tactic_type['age']);

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            $card_age_num = self::ageCharToNum($card_type['age']);
            $bTooOld = ($tactic_age - $card_age_num) > 1;

            if ($card_type['type'] == 'Infantry') {
                $army_types['inf']++;
                if (!$bTooOld)
                    $army_types_correctage['inf']++;
            } else if ($card_type['type'] == 'Cavalry') {
                $army_types['cav']++;
                if (!$bTooOld)
                    $army_types_correctage['cav']++;
            } else if ($card_type['type'] == 'Artillery') {
                $army_types['arty']++;
                if (!$bTooOld)
                    $army_types_correctage['arty']++;
            } else if ($card_type['type'] == 'Air Force') {
                $army_types['air']++;
                if (!$bTooOld)
                    $army_types_correctage['air']++;
            }
        }

        return self::countArmyNumberInArray($army_types, $army_types_correctage, $tactic_type, $player_id, $bForSacrifice);
    }

    function countArmyNumberInArray($army_types, $army_types_correctage, $tactic_type, $player_id, $bForSacrifice = false)
    {
        $result = array('army' => 0, 'army_old' => 0, 'points' => 0);

        $armyType = 'army';

        $hasGenghis = self::isCardInTableau($player_id, 60); // Infantry can count as Cavalry

        while ($armyType) {
            foreach (array('inf', 'cav', 'arty') as $type) {
                if ($army_types_correctage[$type] >= $tactic_type[$type]) {
                    // enough units from the correct age for this
                    $army_types_correctage[$type] -= $tactic_type[$type];
                    $army_types[$type] -= $tactic_type[$type];
                } else if ($hasGenghis && $type == 'cav' && $army_types_correctage['cav'] + $army_types_correctage['inf'] >= $tactic_type['cav']) {
                    // infantry units from the correct age used as cavalry with Genghis Khan
                    $army_types_correctage['inf'] -= $tactic_type['cav'] - $army_types_correctage['cav'];
                    $army_types['inf'] -= $tactic_type['cav'] - $army_types_correctage['cav'];
                    $army_types['cav'] -= $army_types_correctage['cav'];
                    $army_types_correctage['cav'] = 0;
                } else {
                    $armyType = 'army_old';
                    if ($army_types[$type] >= $tactic_type[$type]) {
                        $army_types[$type] -= $tactic_type[$type];
                    } else if ($hasGenghis && $type == 'cav' && $army_types['cav'] + $army_types['inf'] >= $tactic_type['cav']) {
                        $army_types['inf'] -= $tactic_type['cav'] - $army_types['cav'];
                        $army_types['cav'] -= 0;
                    } else {
                        $armyType = null; // Can't form any more army
                        break;
                    }
                }
            }
            if ($armyType) {
                $result[$armyType]++;
            }
        }

        $result['army_types'] = $army_types;
        $result['army_types_correctage'] = $army_types_correctage;

        $result['points'] = $result['army'] * $tactic_type['artybon'] + $result['army_old'] * $tactic_type['artybonplus'];

        // Air force: double each army
        $double_normal = 0;
        $double_old = 0;

        $double_normal = min($army_types['air'], $result['army']);
        $double_old = min($army_types['air'] - $double_normal, $result['army_old']);
        $result['points'] += $double_normal * $tactic_type['artybon'];
        $result['points'] += $double_old * $tactic_type['artybonplus'];

        return $result;
    }

    function getGovernmentLimits($player_id)
    {
        $result = array();

        $cards = $this->cards->getCardsInLocation('tableau', $player_id);

        $CA = 0;
        $MA = 0;
        $extra_C_inhand = 0;
        $extra_M_inhand = 0;
        $gov = null;
        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];

            if ($card_type['type'] == 'Govt')
                $gov = $card;   // Found the government card

            if ($card_type['type'] != 'Wonder' || $card['type_arg'] == 0) {
                if (isset($card_type['CA'])) {
                    if ($card_type['CA'] === '?') {
                        die('special type CA to develop:' . $card_type['name']);
                    } else
                        $CA += $card_type['CA'];
                }

                if (isset($card_type['MA']) && $card_type['category'] != 'War')    // Note: MA for War are COST
                {
                    if ($card_type['MA'] === '?') {
                        die('special type MA to develop:' . $card_type['name']);
                    } else
                        $MA += $card_type['MA'];
                }

                // Special card types affecting maximum number of cards in hand
                if ($card_type['name'] == 'Library of Alexandria') {
                    $extra_C_inhand++;
                    $extra_M_inhand++;
                }
            }
        }


        $result['urbanLimit'] = $this->card_types[$gov['type']]['buildinglimit'];
        $result['civilActions'] = $CA;
        $result['militaryActions'] = $MA;
        $result['civilCardLimit'] = $CA + $extra_C_inhand;
        $result['militaryCardLimit'] = $MA + $extra_M_inhand;

        return $result;
    }

    function getAgeName($age, $bTranslated = true)
    {
        if ($bTranslated) {
            if ($age == 'A') {
                return self::_('Antiquity');
            } else if ($age == 'I') {
                return self::_('Middle Ages');
            } else if ($age == 'II') {
                return self::_('Age of Exploration');
            } else if ($age == 'III') {
                return self::_('Modern Age');
            }
        } else {
            if ($age == 'A') {
                return clienttranslate('Antiquity');
            } else if ($age == 'I') {
                return clienttranslate('Middle Ages');
            } else if ($age == 'II') {
                return clienttranslate('Age of Exploration');
            } else if ($age == 'III') {
                return clienttranslate('Modern Age');
            }
        }
    }


    // Get leader with some indicator (ex: strength)
    //  if bSmallest=true, return the weakest
    //  if nbr=2, return the two weakest/strongest (except if we are at 2 players => return only 1)
    //  if nbr=999, return all players (ordered)
    function getLeaderIn($indicator, $bSmallest = false, $nbr = 1)
    {
        $player_to_score = self::getCollectionFromDB("SELECT player_id, player_$indicator FROM player WHERE player_eliminated = 0 AND player_zombie = 0", true);

        $players = self::loadPlayersBasicInfos();
        $player_id = self::getActivePlayerId();
        $current_player_id = $player_id;
        $nextPlayer = self::createNextPlayerTable(array_keys($players));

        $result_nbr = $nbr;
        if (count($player_to_score) == 2 && $nbr != 999)
            $result_nbr = 1;   // At 2 players, force "1" mode

        if ($nbr == 999)
            $result_nbr = count($player_to_score);

        $tie_breaker = 9;

        do {
            if (array_key_exists($player_id, $player_to_score)) { // Player is not eliminated
                $player_to_score[$player_id] = 10 * $player_to_score[$player_id] + $tie_breaker;
                $tie_breaker--;
            }
            $player_id = $nextPlayer[$player_id];
        } while ($player_id != $current_player_id);

        if ($nbr == 1)
            return getKeyWithMaximum($player_to_score, !$bSmallest);
        else {
            $result = array();
            for ($i = 0; $i < $result_nbr; $i++) {
                $player_id = getKeyWithMaximum($player_to_score, !$bSmallest);
                $result[] = $player_id;
                unset($player_to_score[$player_id]);
            }
            return $result;
        }
    }

    function getPlayerMaxBid($player_id, $bOnlyPermanentBonus = false)
    {
        // Get maximum strength player can bid during territory bidding
        // = strength from units
        // + strength from army (tactics cards)
        // + colonization bonus from tableau cards
        // + colonization bonus from cards in hand

        // From cards
        $bonus_from_tableau = 0;
        $indicator_id = 'colonize';
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);

        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

        foreach ($cards as $card) {
            $bonus_card_type = $this->card_types[$card['type']];

            if (($bonus_card_type['category'] == 'Wonder' && $card['type_arg'] == 0)    // finished Wonder
                || $bonus_card_type['category'] == 'Leader'
                || $bonus_card_type['category'] == 'Special'
                || $bonus_card_type['category'] == 'Govt'
            ) {
                if (isset($bonus_card_type[$indicator_id])) {
                    if ($bonus_card_type[$indicator_id] === "?") {
                        $method_name = self::getCardEffectMethod($bonus_card_type['name'], $bonus_card_type['age'], 'get' . $indicator_id);

                        $bonus_from_tableau += $this->$method_name($player_id, $buildings, $cards);
                    } else {
                        $bonus_from_tableau += $bonus_card_type[$indicator_id];
                    }
                }
            }
        }

        if ($bOnlyPermanentBonus)
            return $bonus_from_tableau;

        // Strength from units + urban buildings
        $strength_from_units = 0;
        $indicator_id = 'strength';

        foreach ($buildings as $building) {
            $bonus_card_type = $this->card_types[$building['card_type']];

            if ($bonus_card_type[$indicator_id] === "?") {
                // Special indicator bonus
                throw new feException('to dev: special indicator bonus: ' . $bonus_card_type['name'] . '/' . $indicator_id);
            } else {
                if ($bonus_card_type['category'] == 'Military')
                    $strength_from_units += $bonus_card_type[$indicator_id];

            }
        }


        $strength_from_tactics = 0;
        $player_tactic = self::getUniqueValueFromDB("SELECT player_tactic FROM player WHERE player_id = '$player_id'");
        if ($player_tactic !== null) {
            // From tactics
            $armies = self::countArmyNumber($buildings, $this->cards->getCard($player_tactic)['type'], $player_id, true);
            $strength_from_tactics += $armies['points'];
        }


        // Bonus card in hand
        $bonus_from_hand = 0;
        $non_bonus_cards = 0;
        $bonus = $this->cards->getCardsInLocation('hand', $player_id);
        foreach ($bonus as $bonus_card) {
            $bonus_card_type = $this->card_types[$bonus_card['type']];
            if ($bonus_card_type['category'] == 'Bonus') {
                $bonus_from_hand += $bonus_card_type['colo'];
            } else if (!self::isCardCategoryCivil($bonus_card_type['category'])) {
                $non_bonus_cards++;
            }
        }
        if (self::isCardInTableau($player_id, 143)) { // James Cook
            $bonus_from_hand += min(2, $non_bonus_cards);
        }

        if ($strength_from_units == 0)
            return 0;   // Must sacrifice at least 1 army to colonize

        return ($strength_from_units + $bonus_from_tableau + $bonus_from_hand + $strength_from_tactics);
    }


    function getMilitaryCardsToDiscardNbr()
    {
        // Count military cards in hand
        $player_id = self::getActivePlayerId();
        $cards = $this->cards->getCardsInLocation('hand', $player_id);
        $nbInHand = 0;

        foreach ($cards as $card) {
            if (!self::isCardCategoryCivil($this->card_types[$card['type']]['category']))
                $nbInHand++;
        }

        $currentEffect = self::getGameStateValue('currentCardEffectType'); // May be Politics of Strength
        if ($currentEffect == 169) {
            return min(3, $nbInHand);
        }

        $govlimits = self::getGovernmentLimits($player_id);
        if ($nbInHand > $govlimits['militaryCardLimit'])
            return $nbInHand - $govlimits['militaryCardLimit'];
        else
            return 0;
    }


    function pickMilitaryCardsForPlayer($player_id, $nbr)
    {
        $players = self::loadPlayersBasicInfos();

        if ($players[$player_id]['player_eliminated'] == 1 || $players[$player_id]['player_zombie'] == 1)
            return;

        $currentAge = self::getGameStateValue('currentAge');
        $current_age_char = self::ageNumToChar($currentAge);

        if ($currentAge == 0) {
            // Exception: we draw military cards even if we are not in the Middle Ages, because we are
            //  going to enter the Middle Ages at the end of the turn
            $currentAge = 1;
            $current_age_char = 'I';
        }

        // Pick some military card to player's hand
        $cards = $this->cards->pickCardsForLocation($nbr, 'military' . $current_age_char, 'hand', $player_id);

        self::notifyPlayer($player_id, 'pickCardsSecret', '', array(
            'cards' => $cards
        ));

        $picked = count($cards);

        if ($picked < $nbr && $current_age_char != 'IV') {
            // Reform the deck from the discard
            $this->cards->moveAllCardsInLocation('discardMilitary' . $current_age_char, 'military' . $current_age_char);
            $this->cards->shuffle('military' . $current_age_char);

            $cards = $this->cards->pickCardsForLocation($nbr - $picked, 'military' . $current_age_char, 'hand', $player_id);

            self::notifyAllPlayers('simpleNote', clienttranslate('No more card in Military deck ${age}: the discard pile is used to form a new deck'), array('age' => $current_age_char));

            self::notifyPlayer($player_id, 'pickCardsSecret', '', array(
                'cards' => $cards
            ));

            $picked += count($cards);
        }

        $notifText = clienttranslate('${player_name} picks ${nbr} military cards');

        if ($picked < $nbr && $current_age_char != 'IV') {


            $notifText = clienttranslate('${player_name} picks ${nbr} military cards (no more card to pick in military deck ${age})');
        }

        self::notifyAllPlayers('simpleNote', $notifText, array(
            'player_id' => $player_id,
            'player_name' => $players[$player_id]['player_name'],
            'nbr' => $picked,
            'age' => $current_age_char
        ));
    }

    function removeCardApplyConsequences($player_id, $card_type_id, $adjustActions = true)
    {
        // Removing a card from this player tableau

        $card_type = $this->card_types[$card_type_id];
        // ==> apply consequences in term of token gain/loose

        if (isset($card_type['tokendelta'])) {
            if ($card_type['tokendelta']['blue'] > 0)
                self::loseToken($player_id, 'blue', $card_type['tokendelta']['blue']);
            else if ($card_type['tokendelta']['blue'] < 0)
                self::gainToken($player_id, 'blue', -$card_type['tokendelta']['blue']);
            if ($card_type['tokendelta']['yellow'] > 0)
                self::loseToken($player_id, 'yellow', $card_type['tokendelta']['yellow']);
            else if ($card_type['tokendelta']['yellow'] < 0)
                self::gainToken($player_id, 'yellow', -$card_type['tokendelta']['blue']);
        }

        if ($adjustActions) {
            self::adjustPlayerActions($player_id);
        }

        if (isset($card_type['effectonremoved'])) { // Homer, Bill Gates
            self::setGameStateValue('currentCardEffectType', $card_type_id);
            return self::cardRemoved($player_id, $card_type, $card_type_id);
        }
    }

    function removeBlueTokensOnCard($player_id, $card_id)
    {
        // ===> if there are blue token on the card, return them to the player's bank

        // Remove all blue tokens => move them to the bank
        $tokens_on_wonder = self::getObjectListFromDB("SELECT token_id FROM token WHERE token_card_id='$card_id'", true);


        $moveTokens = array();
        foreach ($tokens_on_wonder as $token_id) {
            $moveTokens[] = array('id' => $token_id, 'card_id' => null, 'player' => $player_id, 'type' => 'blue');
        }

        self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_card_id='$card_id'");

        self::notifyAllPlayers('moveTokens', '', array(
            'player_id' => $player_id,
            'tokens' => $moveTokens
        ));
    }

    function getEventDecksComposition()
    {
        $result = array();
        foreach (array('events', 'future_events') as $deck) {
            $cards = $this->cards->getCardsInLocation($deck);
            $compo = array('A' => 0, 'I' => 0, 'II' => 0, 'III' => 0);
            foreach ($cards as $card) {
                $compo[$this->card_types[$card['type']]['age']]++;
            }

            $result[$deck] = $compo;
        }
        return $result;
    }

    function notifyEventDeckChange()
    {
        self::notifyAllPlayers('eventDeckChange', '', array('decks' => self::getEventDecksComposition()));
    }

    // All cards from the given age that must be obsolete => removed from the game
    function setObsolete($age)
    {
        // Remove:
        // _ cards in hand
        // _ leaders
        // _ wonders in buildings (blue tokens => blue bank)
        // _ pacts

        // Cards in hand
        $cards = $this->cards->getCardsInLocation('hand');
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['age'] == $age) {
                $this->cards->moveCard($card['id'], 'removed');

                self::notifyPlayer($card['location_arg'], 'discardFromHand', '', array('card_id' => $card['id']));
            }
        }

        // Cards in tableau
        $cards = $this->cards->getCardsInLocation('tableau');
        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];

            if ($card_type['age'] == $age) {
                if (($card_type['category'] == 'Wonder' && $card['type_arg'] == 1)
                    || $card_type['category'] == 'Pact'
                    || $card_type['category'] == 'Leader') {
                    // If there are token on the Wonder => get token back to bank
                    if ($card_type['category'] == 'Wonder') {
                        $card_id = $card['id'];
                        $player_id = $card['location_arg'];
                        self::removeBlueTokensOnCard($player_id, $card_id);
                    }

                    if ($card_type['category'] == 'Pact') {
                        // If still in tableau (we already removed some...)
                        if (self::getUniqueValueFromDB("SELECT card_location FROM card WHERE card_id='" . $card['id'] . "'") == 'tableau')
                            self::doCancelPact($card['location_arg'], $card['id']);
                    }

                    $this->cards->moveCard($card['id'], 'removed');

                    self::removeCardApplyConsequences($card['location_arg'], $card['type']);

                    self::notifyAllPlayers('discardFromTableau', '', array(
                        'player_id' => $card['location_arg'],
                        'card_id' => $card['id']
                    ));

                }
            }
        }

        self::notifyAllPlayers('simpleNote', clienttranslate('Some cards from Age ${age} are discarded'), array('age' => $age));
    }

    function endOfAgeRemoveTokens()
    {
        $players = self::loadPlayersBasicInfos();

        // Remove 2 tokens in banks for each players
        foreach ($players as $player_id => $player) {
            $moveTokens = array();

            $tokens = self::getObjectListFromDB("SELECT token_id FROM token
                WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NULL LIMIT 0,2", true);

            if (count($tokens) > 0) {
                foreach ($tokens as $token_id) {
                    // Remove a token from the bank
                    self::DbQuery("DELETE FROM token WHERE token_id='$token_id'");
                    $moveTokens[] = array('id' => $token_id, 'card_id' => 9999, 'player' => $player_id, 'type' => 'yellow');
                }

                self::notifyAllPlayers('moveTokens', '', array(
                    'player_id' => $player_id,
                    'tokens' => $moveTokens
                ));
            }
        }

        self::notifyAllPlayers('simpleNote', clienttranslate('End of age: each player loses 2 yellow tokens (from bank)'), array());
    }

    function getCardsInHand()
    {
        $hands = $this->cards->getCardsInLocation('hand');
        $cards_in_hand = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards_in_hand[$player_id] = array('Ac' => 0, 'Am' => 0, 'Ic' => 0, 'Im' => 0, 'IIc' => 0, 'IIm' => 0, 'IIIc' => 0, 'IIIm' => 0);
        }
        foreach ($hands as $card) {
            $card_type = $this->card_types[$card['type']];
            if (self::isCardCategoryCivil($card_type['category']))
                $suffix = 'c';
            else
                $suffix = 'm';

            $cards_in_hand[$card['location_arg']][$card_type['age'] . $suffix]++;
        }

        return $cards_in_hand;
    }

    function getCivilCardsInHand() // Clevus
    {
        $hands = $this->cards->getCardsInLocation('hand');
        $civil_cards_in_hand = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $civil_cards_in_hand[$player_id] = array();
        }
        foreach ($hands as $card) {
            $card_type = $this->card_types[$card['type']];
            if (self::isCardCategoryCivil($card_type['category']))
                array_push($civil_cards_in_hand[$card['location_arg']], $card);
        }

        return $civil_cards_in_hand;
    }

    function updateCardsInHand()
    {
        self::notifyAllPlayers('updateCardsInHand', '', array('cards_in_hand' => self::getCardsInHand()));
    }

    //Clevus
    function updateCivilCardsInHand()
    {
        self::notifyAllPlayers('updateCivilCardsInHand', '', array('civil_cards_in_hand' => self::getCivilCardsInHand()));
    }

    function hasWonderUnderConstruction($player_id)
    {
        $wonders = self::getCardsOfCategoryFromTableau($player_id, 'Wonder');
        foreach ($wonders as $wonder) {
            if ($wonder['type_arg'] == 1)
                return true;
        }
        return false;
    }

    function canPickCard($player_id, $card)
    {
        $card_type = $this->card_types[$card['type']];
        switch ($card_type['category']) {
            case 'Leader':
                $leaderpicked_field = 'player_pickedleader_' . $card_type['age'];
                return self::getUniqueValueFromDB("SELECT $leaderpicked_field FROM player WHERE player_id='$player_id'") != 1;
            case 'Wonder':
                return !$this->hasWonderUnderConstruction($player_id);
            case 'Military':
            case 'Urban':
            case 'Production':
            case 'Govt':
            case 'Special':
                // _ can't pick a technology card if already have the same card in hand / in play
                return self::getUniqueValueFromDB("SELECT card_id FROM card
                        WHERE card_type='" . $card['type'] . "'
                        AND card_location IN ('tableau','hand')
                        AND card_location_arg='$player_id'") == null;
                break;
        }
        return true;
    }

    function getCostToPickCard($player_id, $card)
    {
        // Cost of a card in the card row for a player.
        $cost = 1;
        if ($card['location_arg'] > 5)
            $cost = 2;
        if ($card['location_arg'] > 9)
            $cost = 3;
        $card_type = $this->card_types[$card['type']];
        switch ($card_type['category']) {
            case 'Wonder':
                // Michelangelo: do not pay extra actions for the wonders you have
                if (!self::isCardInTableau($player_id, 77))
                    $cost += sizeof(self::getCardsOfCategoryFromTableau($player_id, 'Wonder'));
                if ($card['type'] == 98 && self::isEffectActive(98)) // Taj Mahal
                    $cost -= 2;
                break;
            case 'Leader':
                if (self::isCardInTableau($player_id, 20)) {
                    // Hammurabi: leaders costs 1 less action to take
                    $cost--;
                }
        }
        return max($cost, 0);
    }

    function notifyCardRowCosts($player_id, $cards = null)
    {
        if ($cards == null)
            $cards = $this->cards->getCardsInLocation('card_row', null, 'location_arg');
        foreach ($cards as &$card) {
            if ($this->canPickCard($player_id, $card)) {
                $card['costToPick'] = $this->getCostToPickCard($player_id, $card);
            } else {
                $card['costToPick'] = 'X';
            }
        }
        self::notifyPlayer($player_id, 'card_row_costs_update', '', $cards);
    }

    function getFoodNetProduction($player_id)
    {
        return self::getFoodProduction($player_id) - self::getFoodConsumption($player_id);
    }

    function getFoodProduction($player_id)
    {
        $production = 0;
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        $card_to_yellow_token = self::getCollectionFromDb("SELECT token_card_id, COUNT( token_id ) FROM token
                        WHERE token_card_id IS NOT NULL AND token_type='yellow' AND token_player_id='$player_id'
                        GROUP BY token_card_id ", true);
        foreach ($cards as $card) {
            $cardType = $this->card_types[$card['type']];
            if (isset($cardType['food']) && isset($card_to_yellow_token[$card['id']])) {
                $production += $cardType['food'] * $card_to_yellow_token[$card['id']];
            }
            if ($card['type'] == 1141) {
                $production++;
            }
        }
        return $production;
    }

    function getFoodConsumption($player_id)
    {
        $available_yellow = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token
                WHERE token_player_id='$player_id'
                AND token_card_id IS NULL
                AND token_type='yellow' ");
        return $available_yellow > 30 ? 0 : -$this->food_consumption[$available_yellow];
    }

    function getResourceProduction($player_id, $mineOnly = false)
    {
        $production = 0;
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        $card_to_yellow_token = self::getCollectionFromDb("SELECT token_card_id, COUNT( token_id ) FROM token
                        WHERE token_card_id IS NOT NULL AND token_type='yellow' AND token_player_id='$player_id'
                        GROUP BY token_card_id ", true);
        $hasBillGates = false;
        $hasTranscontinentalRailroad = false;
        $totalLabsLevel = 0;
        $bestMineProduction = 0;
        foreach ($cards as $card) {
            $cardType = $this->card_types[$card['type']];
            if (isset($cardType['ress']) && isset($card_to_yellow_token[$card['id']])) {
                $production += $cardType['ress'] * $card_to_yellow_token[$card['id']];
            }
            if (!$mineOnly && $cardType['type'] == 'Lab' && isset($card_to_yellow_token[$card['id']])) {
                $totalLabsLevel += self::ageCharToNum($cardType['age']) * $card_to_yellow_token[$card['id']];
            }
            if ($cardType['type'] == 'Mine' && isset($card_to_yellow_token[$card['id']])) {
                $bestMineProduction = $cardType['ress'];
            }
            if ($card['type'] == 238) {
                $hasBillGates = true;
            } else if (!$mineOnly && $card['type'] == 109 || $card['type'] == 141) {
                $production += 1; // Pacts
            } else if (!$mineOnly && $card['type'] == 1109) {
                $production -= 1; // Pacts
            } else if ($card['type'] == 178 && $card['type_arg'] == 0) {
                $hasTranscontinentalRailroad = true;
            }
        }
        if ($hasBillGates) {
            $production += $totalLabsLevel;
        }
        if ($hasTranscontinentalRailroad) {
            $production += $bestMineProduction;
        }
        return max($production, 0);
    }

    function getNextEvent()
    {
        // Pick the first event from the earliest age
        $cards = $this->cards->getCardsInLocation('events', null, 'location_arg');
        foreach (array('A', 'I', 'II', 'III') as $age) {
            foreach ($cards as $card) {
                if ($this->card_types[$card['type']]['age'] == $age)
                    return $card;
            }
        }
        return null;
    }

    function getLeader($player_id)
    {
        $leadersInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Leader');
        return count($leadersInPlay) > 0 ? reset($leadersInPlay) : null;
    }

    function getWonderUnderConstruction($player_id)
    {
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == 'Wonder' && $card['type_arg'] == 1) {
                return $card;
            }
        }
        return null;
    }

    function countWorkersType($player_id, $type)
    {
        $cards = self::getCardsOfTypeFromTableau($player_id, $type);
        if (empty($cards)) {
            return 0;
        }
        $card_ids = array();
        foreach ($cards as $card) {
            $card_ids[] = $card['id'];
        }
        return self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token WHERE token_type='yellow' AND token_card_id IN ('" . implode("','", $card_ids) . "')");
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in throughtheagesmobilereadability.action.php)
    */

    function pickCard($card_id)
    {
        self::checkAction('pickCard');

        $player_id = self::getActivePlayerId();

        $card = $this->cards->getCard($card_id);
        if (!$card)
            throw new feException("This card does not exists");
        if ($card['location'] != 'card_row')
            throw new feException("This card is not on card row");

        $card_type = $this->card_types[$card['type']];

        $bSpecialPickCard = self::checkAction('pickCardsSpecial', false);

        // Check pick cards restrictions:
        switch ($card_type['category']) {
            case 'Leader':
                $leaderpicked_field = 'player_pickedleader_' . $card_type['age'];
                if (self::getUniqueValueFromDB("SELECT $leaderpicked_field FROM player WHERE player_id='$player_id'") == 1)
                    throw new feException(self::_("You already have a Leader from the same Age"), true);
                self::DbQuery("UPDATE player SET $leaderpicked_field='1' WHERE player_id='$player_id'");
                break;
            case 'Wonder':
                $wonders = self::getCardsOfCategoryFromTableau($player_id, 'Wonder');
                foreach ($wonders as $wonder) {
                    if ($wonder['type_arg'] == 1)
                        throw new feException(self::_("You already have a Wonder under construction"), true);
                }
                break;
            case 'Military':
            case 'Urban':
            case 'Production':
            case 'Govt':
            case 'Special':
                // _ can't pick a technology card if already have the same card in hand / in play
                $existing_card_id = self::getUniqueValueFromDB("SELECT card_id FROM card
                        WHERE card_type='" . $card['type'] . "'
                        AND card_location IN ('tableau','hand')
                        AND card_location_arg='$player_id'");
                if ($existing_card_id !== null)
                    throw new feException(self::_("You already have such a card"), true);
                break;
        }
        if ($card_type['category'] != 'Wonder') {
            // Maximum number of cards in hand
            $govlimits = self::getGovernmentLimits($player_id);

            $civilCardsInHand = 0;
            $handCards = $this->cards->getCardsInLocation('hand', $player_id);
            foreach ($handCards as $handCard) {
                if (self::isCardCategoryCivil($this->card_types[$handCard['type']]['category']))
                    $civilCardsInHand++;
            }

            if ($civilCardsInHand >= $govlimits['civilCardLimit'])
                throw new feException(sprintf(self::_("According to your current government system, you can have a maximum of %s civil cards in your hand"), $govlimits['civilCardLimit']), true);
        }

        $card_cost = $this->getCostToPickCard($player_id, $card);

        if (!$bSpecialPickCard)
            self::spendCivilActions($player_id, $card_cost);
        else {
            // Note: International Agreement
            $avail = self::getGameStateValue('saved_player');
            if ($card_cost > $avail)
                throw new feException(self::_("Not enough points to take this card"), true);

            $remaining_points = self::incGameStateValue('saved_player', -$card_cost);


        }


        $notifText = clienttranslate('${player_name} picks ${card_name} from card row for ${actions} civil actions');

        if ($card_type['category'] != 'Wonder') {
            // Let's pick the card


            $this->cards->moveCard($card_id, 'hand', $player_id);

            // Notify all players about the card played
            self::notifyAllPlayers("pickCard", $notifText, array(
                'i18n' => array('card_name'),
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->card_types[$card['type']]['name'],
                'card' => $card,
                'actions' => $card_cost
            ));

            self::updateCardsInHand();
            //Clevus
            self::updateCivilCardsInHand();
        } else {
            // This is a Wonder
            // => mark it as "in building"  ( => 1 )
            self::DbQuery("UPDATE card SET card_type_arg='1' WHERE card_id='$card_id'");
            $card['type_arg'] = 1;

            // Place it directly in play
            $this->cards->moveCard($card_id, 'tableau', $player_id);

            self::notifyAllPlayers("pickCardToTableau", $notifText, array(
                'i18n' => array('card_name'),
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->card_types[$card['type']]['name'],
                'card' => $card,
                'actions' => $card_cost
            ));
        }

        self::incStat($card_cost, 'pick_card_action', $player_id);

        if ($card_type['category'] == 'Action' && $this->gamestate->state()['name'] != 'pickCardsFromRow') {
            // Mark this card as "can't be played at this turn" ( => -1 ). Does not apply for International Agreement
            self::DbQuery("UPDATE card SET card_type_arg='-1' WHERE card_id='$card_id'");
        }

        if ($card_type['category'] == 'Military'
            || $card_type['category'] == 'Urban'
            || $card_type['category'] == 'Production'
            || $card_type['category'] == 'Govt'
            || $card_type['category'] == 'Special') {
            // Pick a technology card
            self::ttaEvent('pickTechnologyCard', $player_id);
        }

        $this->gamestate->nextState('endAction');
    }

    function pass($bConfirm = false)
    {
        // Go to next player
        self::checkAction('pass');
        $this->gamestate->nextState('endOfTurn');
    }

    function doNothing()
    {
        self::checkAction('donothing');
        if ($this->gamestate->state()['type'] == 'multipleactiveplayer') {
            $this->gamestate->setPlayerNonMultiactive(self::getCurrentPlayerId(), 'endEvent');
        } else {
            $this->gamestate->nextState('donothing');
        }
    }

    function concedeGame()
    {
        self::checkAction('concedeGame');

        if (self::getGameStateValue('currentAge') == 4) {
            throw new feException(self::_("You cannot resign in Age IV"), true);
        }

        $player_id = self::getActivePlayerId();
        $players = self::loadPlayersBasicInfos();

        // Set score to minus number of already eliminated players
        $nbr_elim = self::getUniqueValueFromDB("SELECT COUNT( player_id ) FROM player WHERE player_eliminated = 1 OR player_zombie = 1");
        $points = $nbr_elim - count($players);
        $remainingPlayers = count($players) - $nbr_elim - 1;

        self::notifyAllPlayers("concedeGame", clienttranslate('${player_name} chooses to leave the game honorably'), array(
            'player_name' => self::getActivePlayerName(), 'remainingPlayers' => $remainingPlayers
        ));

        self::DbQuery("UPDATE player SET player_score='$points' WHERE player_id='$player_id'");

        // Stop all ongoing war involving this player
        self::cancelAllWarsInvolving($player_id);

        // Cancel all pacts involving this player
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];
            if ($card_type['category'] == 'Pact') {
                self::doCancelPact($card['location_arg'], $card['id']);
            }
        }

        foreach ($this->cards->getCardsInLocation('hand', $player_id) as $card) {
            $card_type = $this->card_types[$card['type']];
            if (self::isCardCategoryCivil($card_type['category'])) {
                $this->cards->moveCard($card['id'], 'removed');
            } else {
                $this->cards->moveCard($card['id'], 'discardMilitary' . $card_type['age']);
            }
            self::notifyPlayer($player_id, 'discardFromHand', '', array('card_id' => $card['id']));
        }

        if ($remainingPlayers <= 1) {
            // Only one player remaining => end the game
            $this->gamestate->nextState('endGameConcede');
        } else {
            // Automatically go to next player
            $this->gamestate->nextState('concedeGame');
            self::eliminatePlayer($player_id);
        }
    }

    function cancelAllWarsInvolving($player_id)
    {
        $wars = self::getObjectListFromDB("SELECT war_card_id id, war_defender defender, war_attacker attacker FROM war
        			WHERE war_attacker='$player_id' OR war_defender='$player_id'");

        $players = self::loadPlayersBasicInfos();

        foreach ($wars as $war) {
            $war_id = $war['id'];
            $card = $this->cards->getCard($war_id);
            $card_type = $this->card_types[$card['type']];
            self::DbQuery("DELETE FROM war WHERE war_card_id='$war_id'");

            // Discard War card from tableau
            $this->cards->moveCard($war_id, 'discardMilitary' . $this->card_types[$card['type']]['age']);
            self::notifyAllPlayers('discardFromTableau', '', array(
                'player_id' => $war['attacker'],
                'card_id' => $war_id
            ));


            self::notifyAllPlayers("warOver", '', array(
                'id' => $war_id
            ));

            $attacker = $war['attacker'];
            self::DbQuery("UPDATE player SET player_score=player_score+7 WHERE player_id='$attacker'");
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'),
                array('i18n' => array('card_name'),
                    'card_name' => $card_type['name'],
                    'player_id' => $attacker,
                    'player_name' => $players[$attacker]['player_name'],
                    'culture' => 7, 'science' => 0));
        }

    }

    function doNotUseEffect()
    {
        self::checkAction('doNotUseEffect');

        $player_id = self::getActivePlayerId();
        self::notifyAllPlayers('cancelAction', clienttranslate('${player_name} chooses not to use the power of ${card_name}'), array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => self::getCurrentEffectCardName()
        ));

        if (self::getGameStateValue('currentCardEffectType') == 22) { // Homer
            // Get back a civil action only if you do not use Homer's power
            self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used - 1, player_civil_actions = player_civil_actions + 1 WHERE player_id = '$player_id'");
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} gets back a Civil Action'), array('player_name' => self::getActivePlayerName()));
        }

        $this->gamestate->nextState('cancel');
    }

    function cancel()
    {
        self::checkAction('cancel');

        $currentCardEffectType = self::getGameStateValue('currentCardEffectType');
        if ($currentCardEffectType) {
            $card_type = $this->card_types[$currentCardEffectType];
            if ($card_type['name'] == 'Christopher Columbus') {
                self::removeEffect($currentCardEffectType);
            } else {
                self::notifyAllPlayers('cancelAction', clienttranslate('${player_name} chooses not to use the power of ${card_name}'), array(
                    'i18n' => array('card_name'),
                    'player_id' => self::getActivePlayerId(),
                    'player_name' => self::getActivePlayerName(),
                    'card_name' => self::getCurrentEffectCardName()
                ));
            }

            // If this is an action card => take back the card in hand
            if ($card_type['category'] == 'Action') {
                $last_removed = $this->cards->getCardsInLocation('last_removed');

                if (count($last_removed) == 1) {
                    $player_id = self::getActivePlayerId();
                    $card_type = reset($last_removed);
                    $this->cards->moveCard($card_type['id'], 'hand', self::getActivePlayerId());

                    self::notifyPlayer($player_id, 'pickCardsSecret', '', array(
                        'cards' => $last_removed
                    ));

                    // Get back a civil action
                    $actionsUsed = self::getUniqueValueFromDB("SELECT player_civil_actions_used FROM player WHERE player_id = '$player_id'");
                    if ($actionsUsed > 0) { // With Hammurabi, action cards could be play using a military action.
                        $player_id = self::getCurrentPlayerId();
                        self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used - 1, player_civil_actions = player_civil_actions + 1 WHERE player_id = '$player_id'");
                    } else if (self::isCardInTableau($player_id, 20)) {
                        // Assume it was played with Hammurabi’s power.
                        self::addEffect(1020, 'endTurn');
                        self::dbQuery("UPDATE player SET player_military_actions_used = player_military_actions_used - 1, player_military_actions = player_military_actions + 1 WHERE player_id = '$player_id'");
                    }

                    self::incStat(-1, 'action_card', $player_id);

                    self::updateCardsInHand();
                    // Clevus
                    self::updateCivilCardsInHand();
                }
            }
        }

        $this->gamestate->nextState('cancel');
    }

    function increasePopulation()
    {
        self::checkAction('increasePopulation');

        $player_id = self::getCurrentPlayerId();
        $bForFree = false;
        $discount = 0;

        if (self::getGameStateValue('currentCardEffectType') == 27) { // Development of Civilization
            $bForFree = true;
            $discount = 1;
        }

        self::doIncreasePop($player_id, $bForFree, $discount);

        $state = $this->gamestate->state();
        if ($state['type'] == 'multipleactiveplayer') {
            $this->gamestate->setPlayerNonMultiactive($player_id, 'endEvent');
        } else {
            $this->gamestate->nextState('endAction');
        }
    }

    function doIncreasePop($player_id = null, $bForFree = false, $discount = 0, $bActionIsFree = false)
    {
        if ($player_id === null)
            $player_id = self::getCurrentPlayerId();

        $players = self::loadPlayersBasicInfos();

        $cost = self::getIncreasePopulationCost($player_id);
        $actioncost = 0;

        if ($bForFree) {
            if ($discount > 0) {
                $cost = max($cost - $discount, 0);
                self::spendBlueTokens($player_id, 'food', $cost);
            } else
                $cost = 0;
        } else {
            if ($discount > 0) {
                $cost = max($cost - $discount, 0);
            }

            self::spendBlueTokens($player_id, 'food', $cost);

            if (!$bActionIsFree) {
                self::spendCivilActions($player_id, 1);
                $actioncost = 1;
            }
        }

        // Increase population in bank by 1
        // (one yellow token => to the bank)
        $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NULL LIMIT 0,1");

        if ($token_id === null) {
            if ($bForFree)
                return false;
            else
                throw new feException(self::_("No more yellow tokens available"), true);
        }

        self::DbQuery("UPDATE token SET token_card_id='0' WHERE token_id='$token_id'");

        self::notifyAllPlayers('moveTokens', clienttranslate('${player_name} increases his population for ${action} civil action and ${cost} food'), array(
            'player_id' => $player_id,
            'player_name' => $players[$player_id]['player_name'],
            'cost' => $cost,
            'tokens' => array(array('id' => $token_id, 'type' => 'yellow', 'player' => $player_id, 'card_id' => 0)),
            'action' => $actioncost
        ));

        return true;
    }

    function buildChoice($choice_id, $from)
    {
        self::checkAction('buildChoice');

        if ($choice_id == 0) {
            // Normal build
            self::build(self::getGameStateValue('cardBuilt'), true);
        } else if ($choice_id == 1) {
            // Destroy
            self::doDestroy(self::getGameStateValue('cardBuilt'), self::getActivePlayerId());
            $this->gamestate->nextState('endAction');
        } else if ($choice_id == 2) {
            // Upgrade from X
            self::upgrade(self::getGameStateValue('cardBuilt'), $from);
        }
    }

    function upgrade($card_id, $from_card_id)
    {
        self::checkAction('upgrade');

        $player_id = self::getActivePlayerId();

        $choices = self::getAvailableChoiceFor($card_id);

        $from_card_type_id = null;

        foreach ($choices['upgrade'] as $type => $id) {
            if ($id == $from_card_id)
                $from_card_type_id = $type;
        }

        if ($from_card_type_id === null)
            throw new feException("You cannot upgrade from this card");

        // Okay, we can upgrade from this card !

        // At first, do a destroy on the "from" card
        self::doDestroy($from_card_id, $player_id, true, false, true);

        // Then, we can do a standard "build" with a discount price,

        self::doBuild(self::getGameStateValue('cardBuilt'), $player_id, false, $from_card_type_id);


        $this->gamestate->nextState('endAction');
    }

    function lossBuilding($card_id)
    {
        self::checkAction('lossBuilding');
        $card = $this->cards->getCard($card_id);
        $card_type = $this->card_types[$card['type']];
        if ($card_type['category'] == 'Military') {
            throw new feException(self::_("You must choose a building"), true);
        }

        self::doDestroy($card_id, self::getActivePlayerId(), true);
        $this->gamestate->nextState('lossBuilding');
    }

    function lossPopulation($card_id)
    {
        self::checkAction('lossPopulation');

        $player_id = self::getActivePlayerId();

        self::doDestroy($card_id, $player_id, true);

        $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id='0' LIMIT 0,1");

        if ($token_id === null) {
            throw new feException("Cannot find yellow token after building destruction");
        } else {
            // Remove a token from the worker pool
            self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_id='$token_id'");

            self::notifyAllPlayers('moveTokens', clienttranslate('${card_name}: ${player_name} loses a population'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[self::getGameStateValue('currentCardEffectType')]['name'],
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'tokens' => array(
                    array('id' => $token_id, 'card_id' => null, 'player' => $player_id, 'type' => 'yellow')
                )
            ));

            $this->gamestate->nextState('endEvent');
        }
    }

    function gainToken($player_id, $token_type, $nbr, $notifArgs = null)
    {
        $to_gain = $nbr;
        $field = "player_" . $token_type . "_toloose";
        $to_loose = self::getUniqueValueFromDB("SELECT $field FROM player WHERE player_id='$player_id'");

        if ($to_loose > 0) {
            $reduction = min($to_gain, $to_loose);
            $to_gain -= $reduction;
            $to_loose -= $reduction;

            self::DbQuery("UPDATE player SET $field='$to_loose' WHERE player_id='$player_id'");
        }

        $moveTokens = array();
        for ($i = 0; $i < $to_gain; $i++) {
            self::DbQuery("INSERT INTO token (token_type, token_card_id, token_player_id) VALUES ('$token_type', NULL, '$player_id')");
            $moveTokens[] = array('id' => self::DbGetLastId(), 'card_id' => null, 'player' => $player_id, 'type' => $token_type);
        }

        if ($notifArgs) {
            if ($token_type == 'blue') {
                $text = clienttranslate('${card_name}: ${player_name} gets ${nbr} new blue token(s)');
            } else {
                $text = clienttranslate('${card_name}: ${player_name} gets ${nbr} new yellow token(s)');
            }
            $notifArgs['nbr'] = count($moveTokens);
        } else {
            $text = '';
            $notifArgs = array('player_id' => $player_id);
        }
        $notifArgs['tokens'] = $moveTokens;
        self::notifyAllPlayers('moveTokens', $text, $notifArgs);
    }

    // Make given player loose given type of token (blue/yellow)
    // If the number of token is not in the bank, change "player_blue/yellow_loosetoken" field in order
    //  this player get a chance to choose where to take the token
    function loseToken($player_id, $token_type, $nbr)
    {
        while ($nbr > 0) {
            // Get a token from the bank
            $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
                WHERE token_type='$token_type' AND token_player_id='$player_id' AND token_card_id IS NULL LIMIT 0,1");

            if ($token_id === null) {
                // No token => must choose a token
                $field = "player_" . $token_type . "_toloose";
                self::DbQuery("UPDATE player SET $field=$field+$nbr WHERE player_id='$player_id'");
                return;    // Work has been done => player will be asked to choose tokens
            } else {
                // Remove a token from the bank
                self::DbQuery("DELETE FROM token WHERE token_id='$token_id'");

                self::notifyAllPlayers('moveTokens', '', array(
                    'player_id' => $player_id,
                    'tokens' => array(
                        array('id' => $token_id, 'card_id' => 9999, 'player' => $player_id, 'type' => $token_type)
                    )
                ));

                $nbr--;
            }
        }
    }

    function lossBlueToken($card_id)
    {
        self::checkAction('lossBlueToken');

        self::lossTokenFromCard('blue', $card_id);
    }

    function lossYellowToken($card_id)
    {
        self::checkAction('lossYellowToken');

        self::lossTokenFromCard('yellow', $card_id);
    }

    function lossTokenFromCard($token_type, $card_id)
    {
        $player_id = self::getCurrentPlayerId();

        if ($token_type == 'yellow') {
            self::doDestroy($card_id, $player_id, true);
        } else {
            $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
            WHERE token_type='$token_type' AND token_player_id='$player_id' AND token_card_id='$card_id' LIMIT 0,1");
            if ($token_id === null) {
                throw new feException(sprintf(self::_("Cannot find any %s token on this card"), $token_type), true);
            } else {
                // Remove this token
                self::DbQuery("DELETE FROM token WHERE token_id='$token_id'");
            }
            self::notifyAllPlayers('moveTokens', '', array(
                'player_id' => $player_id,
                'tokens' => array(
                    array('id' => $token_id, 'card_id' => 9999, 'player' => $player_id, 'type' => $token_type)
                )
            ));
        }

        // Decrease player number of token to loose
        $field = "player_" . $token_type . "_toloose";
        $nbr_to_loose = self::getUniqueValueFromDB("SELECT $field FROM player WHERE player_id='$player_id'");
        $nbr_to_loose--;
        self::DbQuery("UPDATE player SET $field=$nbr_to_loose WHERE player_id='$player_id'");

        if ($nbr_to_loose == 0)
            $this->gamestate->setPlayerNonMultiactive($player_id, 'endEvent');
        else
            $this->gamestate->nextState('continue');
    }

    function chooseCard($card_id)
    {
        self::checkAction('chooseCard');
        $currentCard = $this->card_types[self::getGameStateValue('currentCardEffectType')];
        $method_name = self::getCardEffectMethod($currentCard['name'], $currentCard['age'], 'chooseCard');
        $this->$method_name($card_id);
    }

    function politicActionActive($card_id)
    {
        self::checkAction('politicAction');

        $player_id = self::getActivePlayerId();

        // Basic checks
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        if (!isset($card_type['activablepolitic']))
            throw new feException(self::_("There is no action associated with this card"), true);

        $card_name = $card_type['name'];
        $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'activepolitic');

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $card_type['name'],
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName()
        );

        $nextState = $this->$method_name($player_id, $card['type'], $card_type, $card, $notifArgs);

        if ($nextState != null) {
            self::setGameStateValue('currentCardEffectType', $card['type']);
            $this->gamestate->nextState($nextState);
        }


    }

    function build($card_id, $bForceBuild = false, $upgradeFrom = null)
    {
        self::checkAction('build');

        $player_id = self::getCurrentPlayerId();

        // Basic checks
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        if ($card_type['resscost'] === 0
            || ($card['type_arg'] == 0 && $card['type'] == 155))    // Special case: Ocean liner service can be activated AND built
        {
            // This is not a build, but a special power that must be triggered on this card
            if (!isset($card_type['activable']))
                throw new feException(self::_("There is no action associated with this card"), true);

            $card_name = $card_type['name'];
            $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'active');

            $notifArgs = array(
                'i18n' => array('card_name'),
                'card_name' => $card_type['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName()
            );

            $nextState = $this->$method_name($player_id, $card['type'], $card_type, array(), $notifArgs);

            if ($nextState != null) {
                self::setGameStateValue('currentCardEffectType', $card['type']);
                $this->gamestate->nextState($nextState);
            }

            return;
        }

        // If player have alternative choice (upgrade or destroy), give him the choice
        if (!$bForceBuild) {
            $choices = self::getAvailableChoiceFor($card_id);

            if ($choices['choicenbr'] > 1) {
                self::setGameStateValue('cardBuilt', $card_id);
                $this->gamestate->nextState('buildChoice');
                return;
            }
        }

        self::doBuild($card_id, $player_id, false, $upgradeFrom);

        $state = $this->gamestate->state();
        if ($state['type'] == 'multipleactiveplayer') {
            $this->gamestate->setPlayerNonMultiactive($player_id, 'endEvent');
        } else {
            $this->gamestate->nextState('endAction');
        }
    }

    function upgradeChoices($card_id)
    {
        self::checkAction('upgrade');

        $player_id = self::getActivePlayerId();

        // Basic checks
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        if ($card_type['resscost'] == 0)
            throw new feException('You cannot build something with this technology');


        $choices = self::getAvailableChoiceFor($card_id);

        self::setGameStateValue('cardBuilt', $card_id);
        self::setGameStateValue('currentCardEffectType', $card['type']);

        if ($choices['choicenbr'] > 1) {
            $this->gamestate->nextState('buildChoice');
            return;
        }

        if ($choices['choicenbr'] == 0)
            throw new feException(self::_("There is no buildings from lower technologies to upgrade"), true);

        // One choice
        $upgradeFrom = reset($choices['upgrade']);

        self::upgrade($card_id, $upgradeFrom);
    }


    function getAvailableChoiceFor($card_id)
    {
        $result = array('build' => 0, 'upgrade' => array(), 'destroy' => 0, 'choicenbr' => 0);


        if (self::checkAction('build', false)) {
            $result['build'] = 1;
            $result['choicenbr']++;
        }

        if (self::checkAction('destroy', false)) {
            $canDestroy = empty(self::getActiveEffects(array(
                23, 65, 137, 211,   // Urban Growth
                33, 93, 167        // Rich land
            )));
            if ($canDestroy) {
                // If there is already a building on it => we can destroy it
                if (self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token WHERE token_card_id='$card_id' AND token_type='yellow'") > 0) {
                    $result['destroy'] = 1;
                    $result['choicenbr']++;
                }
            }
        }

        $card = $this->cards->getCard($card_id);

        $card_type = $this->card_types[$card['type']];
        $player_id = $card['location_arg'];

        if (self::checkAction('upgrade', false)) {
            if ($card_type['type'] == 'Theater' && self::isEffectActive(145)) { // Bach
                // If there are any buildings with lower or same level => we can upgrade them
                $cards = self::getCardsOfCategoryFromTableau($player_id, 'Urban');
            } else {
                // If there are some buildings of same type with lower technology => we can upgrade them
                $cards = self::getCardsOfTypeFromTableau($player_id, $card_type['type']);
            }


            if (count($cards) > 1) {
                foreach ($cards as $other_card) {
                    if ($other_card['id'] != $card_id)   // This is another card
                    {
                        $other_card_type = $this->card_types[$other_card['type']];
                        if (self::ageCharToNum($card_type['age']) >= self::ageCharToNum($other_card_type['age'])) {
                            // The other card is from an older age => could be upgraded if there is some token on it
                            if (self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token WHERE token_card_id='" . $other_card['id'] . "' AND token_type='yellow'") > 0) {
                                $result['upgrade'][$other_card['type']] = $other_card['id'];
                                $result['choicenbr']++;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    function doBuild($card_id, $player_id, $bForFree = false, $upgradeFrom = null)
    {
        $players = self::loadPlayersBasicInfos();
        $player_name = $players[$player_id]['player_name'];

        $bSkipActionCost = false;

        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        if ($card_type['resscost'] == 0)
            throw new feException('You cannot build something with this technology');

        $bMilitary = false;
        if ($card_type['category'] == 'Military')
            $bMilitary = true;

        $bWonder = ($card_type['category'] == 'Wonder');

        // Effects that applies on building construction
        $effects = self::getActiveEffects(array(
            23, 65, 137, 211,   // Urban Growth
            33, 93, 167,        // Rich land
            28,                 // Patriotism (all ages)
            18, 53, 131, 202,   // Engineering Genius
            52, 128, 198,       // Efficient upgrade
            58,                 // Barbarossa
            22,                 // Homer
            108, 230,           // Wave of nationalism / Military Build-Up
            1861                // Churchill
        ));


        if (in_array(52, $effects)
            || in_array(128, $effects)
            || in_array(198, $effects)
        ) {
            if ($upgradeFrom !== null) {
                // Efficient upgrade: must be urban / farm / mine
                $bSkipActionCost = true;
                if ($card_type['category'] != 'Urban' && $card_type['category'] != 'Production')
                    throw new feException(self::_("You must upgrade an Urban building, a Farm or a Mine"), true);
            }
        }

        if (in_array(23, $effects)
            || in_array(65, $effects)
            || in_array(137, $effects)
            || in_array(211, $effects)
        ) {
            // Urban Growth: must be urban
            $bSkipActionCost = true;
            if ($card_type['category'] != 'Urban')
                throw new feException(self::_("You must build or upgrade an urban building"), true);
        }

        if (in_array(33, $effects)
            || in_array(93, $effects)
            || in_array(167, $effects)
        ) {
            // Rich land: must be production
            $bSkipActionCost = true;
            if ($card_type['category'] != 'Production')
                throw new feException(self::_("You must build or upgrade a Farm or a Mine"), true);
        }


        if (in_array(18, $effects)
            || in_array(53, $effects)
            || in_array(131, $effects)
            || in_array(202, $effects)
        ) {
            // Engineering Genius: must be Wonder
            $bSkipActionCost = true;
            if (!$bWonder)
                throw new feException(self::_("You must build a Wonder step"), true);
        }

        if (in_array(58, $effects)) {
            // Frederick Barbarossa: must be military
            if (!$bMilitary)
                throw new feException(self::_("You must build or upgrade a Military unit"), true);

            // Increase population for 1 less food first, and without paying a civil action
            self::doIncreasePop($player_id, false, 1, true);
        }

        $bDevelopmentOfCivilization = self::getGameStateValue('currentCardEffectType') == 27;
        if ($bDevelopmentOfCivilization) {
            if ($card_type['category'] != 'Production' && $card_type['category'] != 'Urban') {
                throw new feException(self::_("You may only build a farm, mine or urban building"), true);
            }
            $bSkipActionCost = true;
        }

        // Get special technology card (numerous effects on constructions)
        $specialCards = self::getCardsOfCategoryFromTableau($player_id, 'Special');
        $specialCardsTypes = array();
        foreach ($specialCards as $specialCard) {
            $specialCardsTypes[] = $specialCard['type'];
        }

        if (self::checkAction('wonderForFree', false))
            $bSkipActionCost = true;

        $actionCost = 0;
        if (!$bForFree) {
            if (!$bSkipActionCost) {
                if ($bMilitary)
                    self::spendMilitaryActions($player_id, 1);
                else
                    self::spendCivilActions($player_id, 1);

                $actionCost = 1;
            }
        }
        $notifArgs['actionnbr'] = $actionCost;

        if (!$bWonder) {
            if ($card_type['category'] == 'Urban') {
                // Check urban building limit:

                $govlimits = self::getGovernmentLimits($player_id);
                $urbanLimit = $govlimits['urbanLimit'];

                $building_count = self::countWorkersType($player_id, $card_type['type']);

                if ($building_count >= $urbanLimit)
                    throw new feException(sprintf(self::_("According to your current government system, you cannot build more than %s urban building of each type"), $urbanLimit), true);
            }

            // Get some available yellow population token
            $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
                WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id='0' LIMIT 0,1");
            if ($token_id === null)
                throw new feException(self::_("You don't have available population token for this. Please increase your population or destroy a building."), true);

            $cost = 0;
            if (!$bForFree) {
                // Check building cost
                $cost = $card_type['resscost'];

                if ($upgradeFrom !== null) {
                    // Upgrading from another building => get a discount
                    $cost -= $this->card_types[$upgradeFrom]['resscost'];
                }

                if (in_array(23, $effects))  // Urban Growth cost reduction
                    $cost -= 1;
                if (in_array(65, $effects))
                    $cost -= 2;
                if (in_array(137, $effects))
                    $cost -= 3;
                if (in_array(211, $effects))
                    $cost -= 4;

                if (in_array(33, $effects))  // Rich land cost reduction
                    $cost -= 1;
                if (in_array(93, $effects))
                    $cost -= 2;
                if (in_array(167, $effects))
                    $cost -= 3;

                if (in_array(58, $effects))  // Frederick Barbarossa cost reduction
                    $cost -= 1;

                if ($upgradeFrom !== null) {
                    if (in_array(52, $effects))
                        $cost -= 2;
                    if (in_array(128, $effects))
                        $cost -= 3;
                    if (in_array(198, $effects))
                        $cost -= 4;
                }

                if ($bDevelopmentOfCivilization) {
                    $cost--;
                }

                if ($upgradeFrom == null && ($card_type['type'] == 'Theater' || $card_type['type'] == 'Library')) {
                    if (self::isCardInTableau($player_id, 106)) { // William Shakespeare
                        if (self::countWorkersType($player_id, $card_type['type'] == 'Theater' ? 'Library' : 'Theater') > 0) {
                            self::notifyAllPlayers("simpleNote", clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                                'i18n' => array('card_name'),
                                'card_name' => $this->card_types[106]['name'],
                                'player_name' => $player_name,
                                'nbr' => 1
                            ));
                            $cost--;
                        }
                    }
                }

                if ($card_type['category'] == 'Urban') {
                    if (in_array(75, $specialCardsTypes))    // Masonry
                    {
                        $age_to_bonus = array('A' => 0, 'I' => 1, 'II' => 1, 'III' => 1);
                        $bonus = $age_to_bonus[$card_type['age']];

                        if ($upgradeFrom !== null) {
                            // Bonus = difference between bonus for target card age and previous card age
                            $previousBonus = $age_to_bonus[$this->card_types[$upgradeFrom]['age']];
                            $bonus -= $previousBonus;
                        }

                        if ($bonus > 0) {
                            $cost -= $bonus;
                            self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                                'i18n' => array('card_name'),
                                'card_name' => $this->card_types[75]['name'],
                                'nbr' => $bonus
                            ));
                        }
                    }
                    if (in_array(111, $specialCardsTypes))    // Architecture

                    {
                        $age_to_bonus = array('A' => 0, 'I' => 1, 'II' => 2, 'III' => 2);
                        $bonus = $age_to_bonus[$card_type['age']];

                        if ($upgradeFrom !== null) {
                            // Bonus = difference between bonus for target card age and previous card age
                            $previousBonus = $age_to_bonus[$this->card_types[$upgradeFrom]['age']];
                            $bonus -= $previousBonus;
                        }

                        if ($bonus > 0) {
                            $cost -= $bonus;
                            self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                                'i18n' => array('card_name'),
                                'card_name' => $this->card_types[111]['name'],
                                'nbr' => $bonus
                            ));
                        }
                    }
                    if (in_array(201, $specialCardsTypes))    // Engineering
                    {
                        $age_to_bonus = array('A' => 0, 'I' => 1, 'II' => 2, 'III' => 3);
                        $bonus = $age_to_bonus[$card_type['age']];

                        if ($upgradeFrom !== null) {
                            // Bonus = difference between bonus for target card age and previous card age
                            $previousBonus = $age_to_bonus[$this->card_types[$upgradeFrom]['age']];
                            $bonus -= $previousBonus;
                        }

                        if ($bonus > 0) {
                            $cost -= $bonus;
                            self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                                'i18n' => array('card_name'),
                                'card_name' => $this->card_types[201]['name'],
                                'nbr' => $bonus
                            ));
                        }
                    }
                }

                if ($bMilitary && in_array(1861, $effects)) {
                    // Winston Churchill
                    $count = 0;
                    foreach ($effects as $effect) {
                        if ($effect == 1861)
                            $count++;
                    }
                    $used = min($count, $cost);

                    self::removeEffect(1861, $used);

                    self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[186]['name'],
                        'nbr' => $used
                    ));

                    $cost -= $used;
                }

                if ($bMilitary && in_array(28, $effects)) {
                    // There is at least one Patriotism (-1 cost) effect active
                    $patriotism_count = 0;
                    foreach ($effects as $effect) {
                        if ($effect == 28)
                            $patriotism_count++;
                    }
                    $patriotism_used = min($patriotism_count, $cost);

                    self::removeEffect(28, $patriotism_used);

                    self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[28]['name'],
                        'nbr' => $patriotism_used
                    ));

                    $cost -= $patriotism_used;
                }

                if ($bMilitary && $cost > 0 && in_array(22, $effects)) {
                    // Use Homer
                    self::removeEffect(22);

                    self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[22]['name'],
                        'nbr' => 1
                    ));

                    $cost--;
                }

                if ($bMilitary && $cost > 0 && in_array(108, $effects)) {
                    $effect_nbr = 0;
                    foreach ($effects as $effect) {
                        if ($effect == 108)
                            $effect_nbr++;
                    }
                    $effect_used = min($effect_nbr, $cost);

                    // Use Wave of nationalism
                    self::removeEffect(108, $effect_used);

                    self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[108]['name'],
                        'nbr' => $effect_used
                    ));

                    $cost -= $effect_used;
                }
                if ($bMilitary && $cost > 0 && in_array(230, $effects)) {
                    $effect_nbr = 0;
                    foreach ($effects as $effect) {
                        if ($effect == 230)
                            $effect_nbr++;
                    }
                    $effect_used = min($effect_nbr, $cost);

                    // Use  Military build up
                    self::removeEffect(230, $effect_used);

                    self::notifyAllPlayers('simpleNote', clienttranslate('Using ${nbr} resource(s) from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[230]['name'],
                        'nbr' => $effect_used
                    ));

                    $cost -= $effect_used;
                }

                $cost = max(0, $cost);

                self::spendBlueTokens($player_id, 'ress', $cost);
            }

            if ($card_type['type'] == 'Theater' && $upgradeFrom && $this->card_types[$upgradeFrom]['type'] != 'Theater') {
                self::notifyAllPlayers('simpleNote', clienttranslate('Using ${card_name}...'), array(
                    'i18n' => array('card_name'),
                    'card_name' => $this->card_types[145]['name']
                ));
                self::removeEffect(145); // Using Bach ability
            }

            // Move yellow token
            self::DbQuery("UPDATE token SET token_card_id='$card_id' WHERE token_id='$token_id'");

            $from_name = '';
            $i18n = array('name');
            if ($upgradeFrom == null) {
                if ($bMilitary)
                    $notifText = clienttranslate('${player_name} builds a new ${name} unit for ${actionnbr} Military action and ${cost} resources');
                else
                    $notifText = clienttranslate('${player_name} builds a new ${name} building for ${actionnbr} Civil action and ${cost} resources');
            } else {
                if ($bMilitary)
                    $notifText = clienttranslate('${player_name} upgrades a ${fromname} unit to a ${name} unit for ${actionnbr} Military action and ${cost} resources');
                else
                    $notifText = clienttranslate('${player_name} upgrades a ${fromname} building to a ${name} building for ${actionnbr} Civil action and ${cost} resources');

                $i18n[] = 'fromname';
                $from_name = $this->card_types[$upgradeFrom]['name'];
            }

            self::notifyAllPlayers('moveTokens', $notifText, array(
                'i18n' => $i18n,
                'player_id' => $player_id,
                'player_name' => $player_name,
                'tokens' => array(array('id' => $token_id, 'type' => 'yellow', 'player' => $player_id, 'card_id' => $card_id)),
                'name' => $card_type['name'],
                'cost' => $cost,
                'actionnbr' => $actionCost,
                'fromname' => $from_name
            ));

            if ($bMilitary)
                self::incStat(1, 'build_action', $player_id);
            else
                self::incStat(1, 'build_military_action', $player_id);
        } else {
            // Wonder construction (completely different)

            if ($card['type_arg'] != 1)
                throw new feException(self::_("This wonder has been built already"), true);

            // Get current wonder step (=number of blue token on it)
            $wonder_current_step = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token WHERE token_card_id='$card_id'");

            $wonder_total_step = count($card_type['resscost']);

            $cost = 0;

            if (!$bForFree) {
                $cost = $card_type['resscost'][$wonder_current_step];

                if (in_array(18, $effects))  // Engineering genius cost reduction
                    $cost = max(0, $cost - 2);
                if (in_array(53, $effects))
                    $cost = max(0, $cost - 3);
                if (in_array(131, $effects))
                    $cost = max(0, $cost - 4);
                if (in_array(202, $effects))
                    $cost = max(0, $cost - 5);

                self::spendBlueTokens($player_id, 'ress', $cost);
            }

            // Get some available blue token
            $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
                WHERE token_type='blue' AND token_player_id='$player_id' AND token_card_id IS NULL LIMIT 0,1");
            if ($token_id === null)
                throw new feException(self::_("You don't have available blue token for this."), true);

            // Move blue token
            self::DbQuery("UPDATE token SET token_card_id='$card_id' WHERE token_id='$token_id'");

            self::notifyAllPlayers('moveTokens', clienttranslate('${player_name} builds a step of ${wonder_name} for ${actionnbr} Civil action and ${cost} resources'), array(
                'i18n' => array('wonder_name'),
                'player_id' => $player_id,
                'player_name' => $player_name,
                'tokens' => array(array('id' => $token_id, 'type' => 'blue', 'player' => $player_id, 'card_id' => $card_id)),
                'wonder_name' => $card_type['name'],
                'cost' => $cost,
                'actionnbr' => $actionCost
            ));

            if ($wonder_current_step + 1 == $wonder_total_step) {
                // Wonder has been built!

                // Remove all blue tokens => move them to the bank
                $tokens_on_wonder = self::getObjectListFromDB("SELECT token_id FROM token WHERE token_card_id='$card_id'", true);

                $moveTokens = array();
                foreach ($tokens_on_wonder as $token_id) {
                    $moveTokens[] = array('id' => $token_id, 'card_id' => null, 'player' => $player_id, 'type' => 'blue');
                }

                self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_card_id='$card_id'");
                self::DbQuery("UPDATE card SET card_type_arg='0' WHERE card_id='$card_id'");

                self::notifyAllPlayers('moveTokens', clienttranslate('${player_name} achieved to build ${wonder_name}'), array(
                    'i18n' => array('wonder_name'),
                    'player_id' => $player_id,
                    'player_name' => $player_name,
                    'wonder_name' => $card_type['name'],
                    'tokens' => $moveTokens
                ));

                self::notifyAllPlayers('buildWonder', '', array('card_id' => $card_id));

                self::incStat(1, 'wonder_achieved', $player_id);

                if (isset($card_type['effectonplayed'])) {
                    self::actionCard($card_id, $card_type, $card['type']);
                }

                // Yellow/blue token to gain
                self::applyTokenDeltaToPlayer($player_id, $card_type, array(
                    'i18n' => array('card_name'),
                    'card_name' => $card_type['name'],
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $player_id
                ));
            } else {
                if (!self::checkAction('wonderForFree', false)) {
                    // If there is Masonry/Architecture/Engineering, we must jump to the state where we can do
                    // several build

                    if (in_array(201, $specialCardsTypes)) {
                        self::setGameStateValue('currentCardEffectType', 131);
                        self::setGameStateValue('cardBuilt', $card_id);
                        $this->gamestate->nextState('3wonderForFree');
                    } else if (in_array(111, $specialCardsTypes)) {
                        self::setGameStateValue('currentCardEffectType', 111);
                        self::setGameStateValue('cardBuilt', $card_id);
                        $this->gamestate->nextState('2wonderForFree');
                    } else if (in_array(75, $specialCardsTypes)) {
                        self::setGameStateValue('currentCardEffectType', 75);
                        self::setGameStateValue('cardBuilt', $card_id);
                        $this->gamestate->nextState('1wonderForFree');
                    }
                }
            }
        }
    }

    function wonderForFree()
    {
        self::checkAction('wonderForFree');

        $player_id = self::getActivePlayerId();
        $card_id = self::getGameStateValue('cardBuilt');

        self::doBuild($card_id, $player_id);

        $this->gamestate->nextState('wonderForFree');
    }

    // Remove a yellow token from this card ID (for 1 civil/military action)
    function doDestroy($card_id, $player_id, $bNoActionCost = false, $bSacrifice = false, $bUpgrade = false, $dontFailedIfNoToken = false)
    {
        $players = self::loadPlayersBasicInfos();
        $player_name = $players[$player_id]['player_name'];

        $card = $this->cards->getCard($card_id);

        if (!$card)
            throw new feException("This card does not exist");

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        // Get 1 yellow token on the card
        $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token WHERE token_card_id='$card_id' AND token_type='yellow' LIMIT 0,1");

        if (!$token_id) {
            if ($dontFailedIfNoToken)
                return false;
            else
                throw new feException(self::_("There is no yellow token on this card"), true);
        }

        $bMilitary = false;
        if ($card_type['category'] == 'Military')
            $bMilitary = true;


        if ($bSacrifice && !$bMilitary)
            throw new feException(self::_("You must sacrify a military unit"), true);

        $actionCost = 0;
        if (!$bNoActionCost) {

            if ($bMilitary)
                self::spendMilitaryActions($player_id, 1);
            else
                self::spendCivilActions($player_id, 1);

            $actionCost = 1;
        }

        // Okay, let's remove this token from this card and place it in worker pool
        if (!$bSacrifice)
            self::DbQuery("UPDATE token SET token_card_id='0' WHERE token_id='$token_id'");
        else
            self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_id='$token_id'");  // Sacrifice => yellow bank

        if ($bNoActionCost) {
            if ($bUpgrade)
                $notifText = '';
            else {
                if ($bMilitary)
                    $notifText = clienttranslate('${player_name} disbands a ${name} unit');
                else
                    $notifText = clienttranslate('${player_name} destroys a ${name} building');
            }
        } else {
            if ($bMilitary)
                $notifText = clienttranslate('${player_name} disbands a ${name} unit for ${actionnbr} Military action');
            else
                $notifText = clienttranslate('${player_name} destroys a ${name} building for ${actionnbr} Civil action');
        }

        self::notifyAllPlayers('moveTokens', $notifText, array(
            'i18n' => array('name'),
            'player_id' => $player_id,
            'player_name' => $player_name,
            'name' => $card_type['name'],
            'actionnbr' => $actionCost,
            'tokens' => array(
                array('id' => $token_id, 'card_id' => ($bSacrifice ? null : 0), 'player' => $player_id, 'type' => 'yellow')
            )
        ));

        return true;
    }

    function playMilitaryCard($card_id, $bAsPolicAction = true, $target_id = null, $me_as_a = null)
    {
        if ($bAsPolicAction)
            self::checkAction('politicAction');
        else
            self::checkAction('playCard');

        $card = $this->cards->getCard($card_id);
        $player_id = self::getActivePlayerId();

        if ($card['location'] != 'hand' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your hand");
        }

        $card_type = $this->card_types[$card['type']];

        if (self::isCardCategoryCivil($card_type['category']))
            throw new feException(self::_("This card is a Civil card that must be played with a Civil action"), true);

        $players = self::loadPlayersBasicInfos();

        // Check target player
        if ($target_id !== null) {
            if ($target_id == $player_id)
                throw new feException("Can't target yourself");
            if (!isset($players[$player_id]))
                throw new feException("This player does not exists");
        }

        // Effects that applies on playing card
        $effects = self::getActiveEffects(array(
            46
        ));

        if (in_array(46, $effects)) {
            // Christopher Columbus special effect
            $card_name = self::getCurrentEffectCardName();

            $notifArgs = array(
                'i18n' => array('card_name'),
                'card_name' => $card_type['name'],
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName()
            );

            $method_name = self::getCardEffectMethod($card_name, self::getCurrentEffectCardAge(), 'playMilitaryCard');
            if (method_exists($this, $method_name)) {
                $this->$method_name($player_id, $card_id, $card_type, $card, $notifArgs);
            } else {
                $this->warn("Impossible to trigger effect for card $card_name (card_id=$card_id) method name $method_name");
                throw new feException("Impossible to trigger effect for card $card_name. Please create a bug report about it and write card_id=$card_id");
            }
        } else {
            // Standard case

            if ($card_type['category'] == 'Event' && $bAsPolicAction) {
                // Place an event in "Future" event pile, and trigger a new event
                $this->cards->moveCard($card_id, 'future_events');

                self::notifyPlayer($player_id, 'discardFromHand', '', array('card_id' => $card_id));

                $points = self::ageCharToNum($card_type['age']);

                self::DbQuery("UPDATE player SET player_score=player_score+$points WHERE player_id='$player_id'");

                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} places a card in Future Events deck and scores ${culture} points.'), array(
                    'player_id' => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'science' => 0,
                    'culture' => $points
                ));

                self::incStat($points, 'points_events', $player_id);
                self::incStat(1, 'event_placed', $player_id);

                // Then, trigger the next event on events pile
                $this->gamestate->nextState('applyEvent');
            } else if ($card_type['category'] == 'Aggression' && $bAsPolicAction) {
                // Aggression

                if ($target_id === null)
                    throw new feException("You must choose an opponent");

                if (self::isCardInTableau($player_id, 208)) {
                    throw new feException(self::_("You can't play Aggression/War card with Gandhi"), true);
                }

                if ($players[$target_id]['player_eliminated'] == 1)
                    throw new feException(self::_("This player has been eliminated"), true);

                if (isset($card_type['prerequisite'])) {
                    $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'prerequisite');
                    $this->$method_name($player_id, $target_id);
                }

                // Pacts ///////////////////////////
                $strength_bonus = 0;
                $pacts = self::getPactsBetween($player_id, $target_id);
                foreach ($pacts as $pact) {
                    if ($pact['card_type'] == 81)  // Open border agreement
                    {
                        $strength_bonus = 2;
                        self::notifyAllPlayers('simpleNote', clienttranslate('Using +2 bonus from ${card_name}...'), array(
                            'i18n' => array('card_name'),
                            'card_name' => $this->card_types[$pact['card_type']]['name']
                        ));
                    } else if ($pact['card_type'] == 109 || $pact['card_type'] == 227 || $pact['card_type'] == 241) // Acceptance of supremacy / Loss of Sovereignity / Peace treaty
                    {
                        throw new feException(sprintf(self::_("You cannot attack this player because of %s"), $this->card_types[$pact['card_type']]['nametr']), true);
                    } else if ($pact['card_type'] == 161 || $pact['card_type'] == 231) // Promise of Military Protection / military pact
                    {
                        self::doCancelPact($player_id, $pact['pact_card_id']);
                    }
                }

                // Cost in military actions ///////////////
                $cost = $card_type['MA'];

                if ($cost == '?') {
                    $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'getMaCost');
                    $cost = $this->$method_name($player_id, $target_id);
                }

                if (self::isCardInTableau($target_id, 208)) {
                    // Gandhi in target player tableau => double the cost
                    $cost *= 2;
                }

                self::spendMilitaryActions($player_id, $cost);

                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} plays ${card_name} against ${target_name} for ${cost} Military action'), array(
                    'i18n' => array('card_name'),
                    'player_id' => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'target_name' => $players[$target_id]['player_name'],
                    'cost' => $cost,
                    'card' => $card,
                    'card_name' => $card_type['name']
                ));

                // Mark aggression card as current card
                self::setGameStateValue('activePlayerBeforeEffect', self::getActivePlayerId());
                self::setGameStateValue('currentCardEffectType', $card['type']);
                self::setGameStateValue('cardBuilt', $card['id']);
                self::DbQuery("UPDATE player SET player_bid=player_strength WHERE 1");    // Player base strength without sacrifice / bonus

                if ($strength_bonus > 0)
                    self::DbQuery("UPDATE player SET player_bid=player_bid+$strength_bonus WHERE player_id='$player_id'");

                $playerStrength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$player_id'");
                $opponentStrength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$target_id'");

                if ($playerStrength <= $opponentStrength) {
                    throw new feException(self::_("You cannot attack a player whose strength equals or exceeds yours."), true);
                }

                self::setGameStateValue('saved_player', $target_id);

                self::incStat(1, 'aggression_played', $player_id);
                self::incStat(1, 'aggression_targeted', $target_id);

                $this->gamestate->nextState('aggressionOpponentMaySacrifice');
            } else if ($card_type['category'] == 'War' && $bAsPolicAction) {
                // War

                if (self::getGameStateValue('completeLastTurn') == 1)
                    throw new feException(self::_("You cannot declare War during the last turn"), true);

                if ($target_id === null)
                    throw new feException("You must choose an opponent");

                if (self::isCardInTableau($player_id, 208)) {
                    throw new feException(self::_("You can't play Aggression/War card with Gandhi"), true);
                }

                if ($players[$target_id]['player_eliminated'] == 1)
                    throw new feException(self::_("This player has been eliminated"), true);


                // Pacts ///////////////////////////
                $pacts = self::getPactsBetween($player_id, $target_id);
                foreach ($pacts as $pact) {
                    if ($pact['card_type'] == 109 || $pact['card_type'] == 227 || $pact['card_type'] == 241) // Acceptance of supremacy / Loss of Sovereignity / Peace treaty
                    {
                        throw new feException(sprintf(self::_("You cannot attack this player because of %s"), $this->card_types[$pact['card_type']]['nametr']), true);
                    } else if ($pact['card_type'] == 161 || $pact['card_type'] == 231) // Promise of Military Protection / military pact
                    {
                        self::doCancelPact($player_id, $pact['pact_card_id']);
                    }

                }
                // If target have Loss of Sovereignity B => no one can declare War
                if (self::isCardInTableau($target_id, 1227))
                    throw new feException(sprintf(self::_("You cannot attack this player because of %s"), $this->card_types[1227]['nametr']), true);


                // Cost in military actions ///////////////
                $cost = $card_type['MA'];

                if ($cost == '?') {
                    $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'getMaCost');
                    $cost = $this->$method_name($player_id, $target_id);
                }

                if (self::isCardInTableau($target_id, 208)) {
                    // Gandhi in target player tableau => double the cost
                    $cost *= 2;
                }

                self::spendMilitaryActions($player_id, $cost);

                self::notifyAllPlayers('war', clienttranslate('${player_name} plays ${card_name} against ${target_name} for ${cost} Military action'), array(
                    'i18n' => array('card_name'),
                    'player_id' => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'target_name' => $players[$target_id]['player_name'],
                    'target_id' => $target_id,
                    'cost' => $cost,
                    'card_id' => $card['id'],
                    'card_name' => $card_type['name'],
                    'card_type' => $card['type']
                ));

                // Place the War card in play
                $this->cards->moveCard($card_id, 'tableau', $player_id);

                self::notifyAllPlayers('playCard', '', array(
                    'player_id' => $player_id,
                    'card' => $card
                ));

                self::DbQuery("INSERT INTO war (war_card_id,war_attacker,war_defender) VALUES ('$card_id','$player_id','$target_id')");

                self::incStat(1, 'aggression_played', $player_id);
                self::incStat(1, 'aggression_targeted', $target_id);

                $this->gamestate->nextState('endAction');
            } else if ($card_type['category'] == 'Pact' && $bAsPolicAction) {
                if ($target_id === null)
                    throw new feException("You must choose an opponent");

                if ($me_as_a) {
                    $a_player = $player_id;
                    $b_player = $target_id;
                } else {
                    $a_player = $target_id;
                    $b_player = $player_id;
                }

                // Mark pact card as current card
                self::setGameStateValue('activePlayerBeforeEffect', self::getActivePlayerId());
                self::setGameStateValue('currentCardEffectType', $card['type']);
                self::setGameStateValue('cardBuilt', $card['id']);

                // Introduce pact
                self::DbQuery("INSERT INTO pact (pact_card_id,pact_a,pact_b)
                    VALUES ('" . $card['id'] . "','$a_player','$b_player')");

                // Propose pact to target player
                self::setGameStateValue('saved_player', $target_id);

                $this->gamestate->nextState('pactMayAccept');
            } else if ($card_type['category'] == 'Tactics' && !$bAsPolicAction) {
                // Put a tactic card in play

                self::spendMilitaryActions($player_id, 1);

                // => Check that player did not already play a tactic this turn
                $tacticsInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Tactics');
                if (count($tacticsInPlay) > 0) {
                    throw new feException(self::_("You can play or copy a tactic only once per turn."), true);
                }

                // Remove previous tactic from UI if any
                $previousTactic = self::getUniqueValueFromDB("SELECT player_tactic FROM player WHERE player_id = '$player_id'");
                if ($previousTactic != null) {
                    self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $player_id, 'card_id' => $previousTactic));
                }

                // Then, place the new tactic in play
                $this->cards->moveCard($card_id, 'tableau', $player_id);
                self::DbQuery("UPDATE player SET player_tactic = $card_id WHERE player_id = '$player_id'");

                self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${card_name} for ${cost} Military action'), array(
                    'i18n' => array('card_name'),
                    'player_id' => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'cost' => 1,
                    'card' => $card,
                    'card_name' => $card_type['name']
                ));

                self::adjustPlayerIndicators($player_id);

                self::incStat(1, 'tactic_played', $player_id);

                $this->gamestate->nextState('endAction');
            } else {
                // Can't play this card at this phase

                if ($bAsPolicAction)
                    throw new feException(self::_("You must play this card using a Military action"), true);
                else
                    throw new feException(self::_("You must play this card using a Politic action"), true);
            }

            self::updateCardsInHand();
        }
    }

    function getPactsBetween($player1, $player2)
    {
        $list = self::getObjectListFromDB("SELECT pact_card_id, card_type, pact_a, pact_b FROM pact
            INNER JOIN card ON card_id=pact_card_id
            WHERE pact_a IN ('$player1','$player2')
            AND pact_b IN ('$player1','$player2')");

        return $list;
    }

    function getPactsOf($player1)
    {
        $list = self::getObjectListFromDB("SELECT pact_card_id, card_type, pact_a, pact_b FROM pact
            INNER JOIN card ON card_id=pact_card_id
            WHERE pact_a='$player1'
            OR pact_b='$player1'");

        $result = array();

        foreach ($list as $item) {
            if ($item['pact_a'] == $player1)
                $item['partner'] = $item['pact_b'];
            else
                $item['partner'] = $item['pact_a'];
            $result[] = $item;
        }

        return $result;
    }

    function playCard($card_id, $specialMode = null)
    {
        self::checkAction('playCard');

        $card = $this->cards->getCard($card_id);
        $player_id = self::getCurrentPlayerId();
        $nextState = null;

        if ($card['location'] != 'hand' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your hand");
        }

        $card_type = $this->card_types[$card['type']];

        $bSkipActionCost = false;

        // Effects that applies on playing card
        $effects = self::getActiveEffects(array(
            44, 115,    // Breakthrough
        ));

        $bBreakThrough = false;
        $bGovSkipAction = false;

        if (in_array(44, $effects)
            || in_array(115, $effects)
        ) {
            // Breakthrough: must be Technology
            $bBreakThrough = true;
            $bSkipActionCost = true;
            if ($card_type['techcost'] == 0)
                throw new feException(self::_("You must play a Technology card"), true);
        }

        $bDevelopmentOfCivilization = self::getGameStateValue('currentCardEffectType') == 27;
        if ($bDevelopmentOfCivilization) { // Development of Civilization
            if ($card_type['techcost'] == 0) {
                throw new feException(self::_("You must play a Technology card"), true);
            }
            $bSkipActionCost = true;
        }

        if (!self::isCardCategoryCivil($card_type['category'])) {
            if ($card_type['category'] == 'Tactics') {
                // Tactic card: can be played
                self::playMilitaryCard($card_id, false);
                return;
            } else
                throw new feException(self::_("This card is a Military card that must be played with a Political action"), true);
        }

        if ($card_type['category'] == 'Govt' && !$bSkipActionCost) {
            $bSkipActionCost = true;    // Action cost for government change are managed specifically
            $bGovSkipAction = true;
        }

        if (!$bSkipActionCost)
            self::spendCivilActions($player_id, 1);

        // Place a card in play

        if ($card_type['category'] == 'Leader') {
            // Place a leader in play
            // => remove the old one if any
            $leadersInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Leader');
            if (count($leadersInPlay) > 0) {
                $leader = reset($leadersInPlay);
                $this->cards->moveCard($leader['id'], 'removed');
                $leader_type = $this->card_types[$leader['type']];
                self::notifyAllPlayers('discardFromTableau', clienttranslate('${player_name} discards ${leader_name}'), array(
                    'i18n' => array('leader_name'),
                    'player_id' => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'leader_name' => $leader_type['name'],
                    'card_id' => $leader['id']
                ));
                $nextState = self::removeCardApplyConsequences($player_id, $leader['type']);

                self::addEffect(98, 'endTurn'); // Taj Mahal gets a 2 actions discount for that turn

                if (!$nextState) { // Not for Homer
                    // Get back a civil action
                    $actionsUsed = self::getUniqueValueFromDB("SELECT player_civil_actions_used FROM player WHERE player_id = '$player_id'");
                    if ($actionsUsed > 0) { // With Hammurabi, leader could be played using a military action.
                        self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used - 1, player_civil_actions = player_civil_actions + 1 WHERE player_id = '$player_id'");
                        self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} gets back a Civil Action'), array('player_name' => self::getActivePlayerName()));
                    }
                }
            }
        } else if ($card_type['category'] == 'Special') {
            // Place a special tech
            // => remove the old one with same type if any
            $specialInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Special');
            foreach ($specialInPlay as $special) {
                if ($this->card_types[$special['type']]['type'] == $card_type['type']) {
                    $this->cards->moveCard($special['id'], 'removed');
                    self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $player_id, 'card_id' => $special['id']));

                    self::removeCardApplyConsequences($player_id, $special['type'], false);
                }
            }

            self::incStat(1, 'special_played', $player_id);
        } else if ($card_type['category'] == 'Action') {
            // Check if has been picked this turn
            if ($card['type_arg'] == -1)
                throw new feException(self::_("You can't play an action card the turn you picked it"), true);

            self::incStat(1, 'action_card', $player_id);
        } else if ($card_type['category'] == 'Govt') {
            // Governement card
            if ($specialMode === null) {
                throw new feException("When playing a government technology, it must be specify whether it is a peaceful change or a revolution");
            } else if ($specialMode == 'revolution') {
                if (self::isCardInTableau($player_id, 148)) { // Robespierre (revolution costs all the military actions)
                    $militaryActionsUsed = self::getUniqueValueFromDB("SELECT player_military_actions_used FROM player WHERE player_id = '$player_id'");
                    if ($militaryActionsUsed != 0) {
                        throw new feException(self::_("You need all your military actions to declare a revolution with Robespierre"), true);
                    }
                } else {
                    $civilActionsUsed = self::getUniqueValueFromDB("SELECT player_civil_actions_used FROM player WHERE player_id = '$player_id'");
                    if ($civilActionsUsed != 0 && ($civilActionsUsed != 1 || !$bBreakThrough)) {
                        throw new feException(self::_("You need all your civil actions to declare a revolution"), true);
                    }
                }
            }

            // Remove current Govt card
            $govtsInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Govt');
            if (count($govtsInPlay) > 0) {
                $govt = reset($govtsInPlay);
                $this->cards->moveCard($govt['id'], 'removed');
                self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $player_id, 'card_id' => $govt['id']));
            }
        }

        if ($bSkipActionCost)
            $notifText = clienttranslate('${player_name} plays ${card_name} for 0 Civil action');
        else
            $notifText = clienttranslate('${player_name} plays ${card_name} for 1 Civil action');

        $cost = 0;
        if ($card_type['techcost'] > 0) {

            // This is a technology with a cost in science
            $cost = $card_type['techcost'];

            if ($bSkipActionCost && !$bGovSkipAction)
                $notifText = clienttranslate('${player_name} plays ${card_name} for 0 Civil action and ${cost} science points');
            else
                $notifText = clienttranslate('${player_name} plays ${card_name} for 1 Civil action and ${cost} science points');

            if ($specialMode === 'revolution') {
                // Cost is reduced
                $cost = $card_type['techcostbon'];
                $notifText = clienttranslate('${player_name} plays ${card_name} (Revolution!) for all Civil actions and ${cost} science points');
            }

            if ($card_type['type'] == 'Theater' || $card_type['type'] == 'Library') {
                if (self::isCardInTableau($player_id, 106)) { // William Shakespeare
                    if (self::countWorkersType($player_id, $card_type['type'] == 'Theater' ? 'Library' : 'Theater') > 0) {
                        self::notifyAllPlayers("simpleNote", clienttranslate('Using ${nbr} science from ${card_name}...'), array(
                            'i18n' => array('card_name'),
                            'card_name' => $this->card_types[106]['name'],
                            'player_name' => self::getActivePlayerName(),
                            'nbr' => 1
                        ));
                        $cost--;
                    }
                }
            }

            if ($card_type['type'] == 'Theater') {
                // JS Bach => -2 cost
                if (self::isCardInTableau($player_id, 145)) {
                    self::notifyAllPlayers("simpleNote", clienttranslate('Using ${nbr} science from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[145]['name'],
                        'player_name' => self::getActivePlayerName(),
                        'nbr' => 2
                    ));
                    $cost -= 2;
                }
            }
            if ($card_type['category'] == 'Military' && self::isEffectActive(1860)) {
                // Churchill => -3 cost
                $cost -= 3;
                self::notifyAllPlayers("simpleNote", clienttranslate('Using ${nbr} science from ${card_name}...'), array(
                    'i18n' => array('card_name'),
                    'card_name' => $this->card_types[186]['name'],
                    'player_name' => self::getActivePlayerName(),
                    'nbr' => 3
                ));
                self::removeEffect(1860);
            }

            // Scientific cooperation
            $cost = self::getCostAfterScientificCooperation($player_id, $cost);

            if ($bDevelopmentOfCivilization) { // Development of Civilization
                $cost--;
            }

            $cost = max(0, $cost);

            $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
            if ($currentSciencePoints < $cost)
                throw new feException(self::_("You don't have enough science points to discover this technology"), true);

            self::DbQuery("UPDATE player SET player_science_points=player_science_points-$cost WHERE player_id='$player_id'");

            $currentSciencePoints -= $cost;
            self::notifyAllPlayers('updateSciencePoints', '', array('player_id' => $player_id, 'points' => ($currentSciencePoints)));

            self::incStat(1, 'tech_discovered', $player_id);
        }

        if ($card_type['category'] != 'Action') {
            // This card goes in player tableau
            $this->cards->moveCard($card_id, 'tableau', $player_id);

            self::notifyAllPlayers('playCard', $notifText, array(
                'i18n' => array('card_name'),
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'cost' => $cost,
                'card' => $card,
                'card_name' => $card_type['name']
            ));

            if (in_array(44, $effects)
                || in_array(115, $effects)
            ) {
                // Breakthrough:

                if (in_array(44, $effects))
                    $bonus = 2;
                else
                    $bonus = 3;

                self::DbQuery("UPDATE player SET player_science_points=player_science_points+$bonus WHERE player_id='$player_id'");
                self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), array(
                    'i18n' => array('card_name'),
                    'player_id' => $player_id,
                    'points' => $currentSciencePoints + $bonus,
                    'player_name' => self::getActivePlayerName(),
                    'science' => $bonus,
                    'card_name' => $this->card_types[44]['name']
                ));
            }

            if ($card_type['category'] == 'Govt') {
                if ($specialMode == 'revolution') {
                    if (self::isCardInTableau($player_id, 148)) {
                        // Robespierre => use military action instead
                        self::dbQuery("UPDATE player SET player_military_actions_used = player_military_actions_used + player_military_actions, player_military_actions = 0 WHERE player_id = '$player_id'");

                        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: uses all Military actions instead'), array(
                            'i18n' => array('card_name'),
                            'card_name' => $this->card_types[148]['name']
                        ));

                        // Robespierre => score 3 culture points
                        self::DbQuery("UPDATE player SET player_score=player_score+3 WHERE player_id='$player_id'");
                        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'),
                            array('i18n' => array('card_name'),
                                'card_name' => $this->card_types[148]['name'],
                                'player_id' => $player_id,
                                'player_name' => self::getCurrentPlayerName(),
                                'culture' => 3, 'science' => 0));
                        self::incStat(3, 'points_cards_effects', $player_id);
                    } else {
                        // Spend all remaining civil actions
                        self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used + player_civil_actions, player_civil_actions = 0 WHERE player_id = '$player_id'");
                    }
                    self::adjustPlayerActions($player_id, true);
                } else {
                    if (!$bBreakThrough && !$bDevelopmentOfCivilization)
                        self::spendCivilActions($player_id, 1);
                }

                self::incStat(1, 'govt_change', $player_id);
            }


            // Yellow/blue token to gain
            self::applyTokenDeltaToPlayer($player_id, $card_type, array(
                'i18n' => array('card_name'),
                'card_name' => $card_type['name'],
                'player_name' => self::getActivePlayerName(),
                'player_id' => $player_id
            ));


            if ($card_type['category'] == 'Military'
                || $card_type['category'] == 'Urban'
                || $card_type['category'] == 'Production'
                || $card_type['category'] == 'Govt'
                || $card_type['category'] == 'Special') {
                // Play a technology card
                self::ttaEvent('playTechnologyCard', $player_id);
            }

            if (isset($card_type['effectonplayed'])) {
                if ($nextState == null) {
                    $nextState = self::actionCard($card_id, $card_type, $card['type']);
                    self::setGameStateValue('currentCardEffectType', $card['type']);
                } else {
                    self::actionCard($card_id, $card_type, $card['type']);
                }
            }
            $state = $this->gamestate->state();
            if ($state['type'] == 'multipleactiveplayer') {
                $this->gamestate->setPlayerNonMultiactive($player_id, 'endEvent');
            } else if ($nextState) {
                $this->gamestate->nextState($nextState);
            } else {
                $this->gamestate->nextState('endAction');
            }
        } else {
            // Action card => is discarded directly
            $this->cards->moveAllCardsInLocation('last_removed', 'removed');
            $this->cards->moveCard($card_id, 'last_removed');

            self::notifyAllPlayers('removeFromHand', $notifText, array(
                'i18n' => array('card_name'),
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'cost' => $cost,
                'card' => $card,
                'card_name' => $card_type['name']
            ));

            $nextState = self::actionCard($card_id, $card_type, $card['type']);

            if ($nextState) {
                self::setGameStateValue('currentCardEffectType', $card['type']);
                $this->gamestate->nextState($nextState);
            } else {
                $this->gamestate->nextState('endAction');
            }
        }

        self::updateCardsInHand();
        // Clevus
        self::updateCivilCardsInHand();
    }

    // Return cost after having applied scientific cooperation
    function getCostAfterScientificCooperation($player_id, $cost, $bApplyCost = true)
    {
        if (self::isCardInTableau($player_id, 170) || self::isCardInTableau($player_id, 1170)) {
            // Get partner
            $pacts = self::getPactsOf($player_id);
            foreach ($pacts as $pact) {
                if ($pact['card_type'] == 170 || $pact['card_type'] == 1170) {
                    $partner = $pact['partner'];

                    $cost = max($cost - 2, 0);

                    $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$partner'");
                    if ($currentSciencePoints < 1)
                        throw new feException(self::_("Your Scientific Cooperation partner don't have enough science points"), true);


                    if ($bApplyCost) {
                        self::notifyAllPlayers("simpleNote", clienttranslate('Using ${nbr} science from ${card_name}...'), array(
                            'i18n' => array('card_name'),
                            'card_name' => $this->card_types[170]['name'],
                            'nbr' => 2
                        ));


                        self::DbQuery("UPDATE player SET player_science_points=player_science_points-1 WHERE player_id='$partner'");

                        $currentSciencePoints -= 1;
                        self::notifyAllPlayers('updateSciencePoints', '', array('player_id' => $partner, 'points' => ($currentSciencePoints)));
                    }
                }
            }
        }

        return $cost;
    }

    function choice($choice)
    {
        self::checkAction('dualChoice');

        $card_name = self::getCurrentEffectCardName();
        $method_name = self::getCardEffectMethod($card_name, self::getCurrentEffectCardAge(), 'dualChoice');
        $this->$method_name($choice);
    }

    function chooseOpponentTableauCard($card_id)
    {
        self::checkAction('chooseOpponentTableauCard');

        $card_name = self::getCurrentEffectCardName();
        $method_name = self::getCardEffectMethod($card_name, self::getCurrentEffectCardAge(), 'chooseOpponentTableauCard');
        $this->$method_name($card_id);
    }

    function bidTerritory($bid)
    {
        self::checkAction('bidTerritory');

        $player_id = self::getActivePlayerId();

        if ($bid == 0) {
            // Player pass
            self::DbQuery("UPDATE player SET player_bid='0' WHERE player_id='$player_id'");

            self::notifyAllPlayers("bidTerritory", clienttranslate('${player_name} passes'), array(
                'player_id' => $player_id,
                'bid' => $bid,
                'player_name' => self::getActivePlayerName()
            ));

            $this->gamestate->nextState('bidTerritory');
        } else {
            // Player bid
            self::DbQuery("UPDATE player SET player_bid='$bid' WHERE player_id='$player_id'");

            self::notifyAllPlayers("bidTerritory", clienttranslate('${player_name} bids ${bid}'), array(
                'player_id' => $player_id,
                'bid' => $bid,
                'player_name' => self::getActivePlayerName()
            ));

            $this->gamestate->nextState('bidTerritory');
        }
    }

    function sacrifice($card_id)
    {
        self::checkAction('sacrifice');

        $player_id = self::getActivePlayerId();

        $card = $this->cards->getCard($card_id);

        // Sacrifice an army on this card, in order to respect bidding for colonization (or to increase strength)

        self::doDestroy($card_id, $player_id, true, true);

        $card_type = $this->card_types[$card['type']];

        $strength_points_sacrified = $card_type['strength'];

        // TACTICS:
        //   if we detect that the number of army is now < to the previous number of army,
        //   add the army bonus strength to the number of sacrified points.
        //

        $player_tactic = self::getUniqueValueFromDB("SELECT player_tactic FROM player WHERE player_id = '$player_id'");
        if ($player_tactic != null) {
            $tactic = $this->card_types[$this->cards->getCard($player_tactic)['type']];

            // Is this army "obsolete" for current tactic card?
            $tactic_age = self::ageCharToNum($tactic['age']);
            $card_age_num = self::ageCharToNum($card_type['age']);

            $bTooOld = ($tactic_age - $card_age_num) > 1;

            $points_to_add = $bTooOld ? 1 : 101;    // Note: hack: 100 points per unit from the correct age

            if ($card_type['type'] == 'Infantry')
                self::incGameStateValue('sacrificedInf', $points_to_add);
            else if ($card_type['type'] == 'Cavalry')
                self::incGameStateValue('sacrificedCav', $points_to_add);
            else if ($card_type['type'] == 'Artillery')
                self::incGameStateValue('sacrificedArty', $points_to_add);
            else if ($card_type['type'] == 'Air Force')
                self::incGameStateValue('sacrificedAir', $points_to_add);

            // Now, check if we have some army


            $inf = self::getGameStateValue('sacrificedInf');
            $cav = self::getGameStateValue('sacrificedCav');
            $arty = self::getGameStateValue('sacrificedArty');
            $air = self::getGameStateValue('sacrificedAir');
            $armySacrifiedPoints = self::getGameStateValue('sacrificedTot');  // Current number of "army" points sacrified

            $army_types_correctage = array('inf' => floor($inf / 100), 'cav' => floor($cav / 100), 'arty' => floor($arty / 100), 'air' => floor($air / 100));
            $army_types = array('inf' => $inf % 100, 'cav' => $cav % 100, 'arty' => $arty % 100, 'air' => $air % 100);

            $armies = self::countArmyNumberInArray($army_types, $army_types_correctage, $tactic, $player_id, true);

            if ($armies['points'] > 0 && $armies['points'] != $armySacrifiedPoints) // If the bonus has changed
            {
                // A whole army have been sacrified
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} sacrified at least 1 whole army and gets an additional ${strength}'), array(
                    'player_name' => self::getActivePlayerName(),
                    'strength' => $armies['points']
                ));

                $new_points = ($armies['points'] - $armySacrifiedPoints);

                $strength_points_sacrified += $new_points;
                self::incGameStateValue('sacrificedTot', $new_points);
            }
        }

        self::adjustPlayerIndicators($player_id);
        self::addColonizationPoints($strength_points_sacrified);
    }

    function useBonus($card_id)
    {
        // Use a bonus instead of an army to sacrifice

        // Check that an army have been sacrificed before.
        if (!self::checkAction('useBonus', false)) {
            if (self::checkAction('aggressorCannotUseBonus', false))
                throw new feException(self::_("Aggressor cannot use bonus card"), true);
            else
                throw new feException(self::_("You must sacrifice at least one army first"), true);
        }

        // Check this is a bonus card in player hand
        $card = $this->cards->getCard($card_id);
        $player_id = self::getActivePlayerId();

        if ($card['location'] != 'hand' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your hand");
        }

        $card_type = $this->card_types[$card['type']];

        $jamesCookEffect = self::isEffectActive(143); // James Cook

        if ($jamesCookEffect) {
            if (self::isCardCategoryCivil($card_type['category'])) {
                throw new feException(sprintf(self::_('%s is a civil card'), $card_type['nametr']), true);
            }
        } else if ($card_type['category'] != 'Bonus') {
            throw new feException(self::_("The only cards you can use are Bonus cards"), true);
        }

        if ($card_type['category'] == 'Bonus') {
            $value = $card_type['colo'];
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} uses a Bonus card: +${value}'), array(
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'value' => $value
            ));
            self::addColonizationPoints($value);
        } else {
            self::removeEffect(143, 1);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} discards a military card to get +1 colonization'), array(
                'i18n' => array('card_name'),
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->card_types[143]['name']
            ));
            self::addColonizationPoints(1);
        }

        // Discard this card
        $this->cards->moveCard($card_id, 'discardMilitary' . $card_type['age']);

        self::updateCardsInHand();

        self::notifyPlayer($player_id, 'discardFromHand', '', array('card_id' => $card_id));
    }

    function addColonizationPoints($strength_points_sacrified)
    {
        $player_id = self::getActivePlayerId();
        $colonizationStrength = self::getGameStateValue('colonization_strength') + $strength_points_sacrified;
        $playerBid = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$player_id'");

        if ($colonizationStrength >= $playerBid) {
            // Okay, we're done => can colonize the card

            $card = $this->cards->getCard(self::getGameStateValue('cardBuilt'));
            $card_type = $this->card_types[$card['type']];

            $this->cards->moveCard($card['id'], 'tableau', $player_id);

            self::notifyAllPlayers('playCard', clienttranslate('${player_name} colonizes ${card_name}'), array(
                'i18n' => array('card_name'),
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card' => $card,
                'card_name' => $card_type['name']
            ));

            $card_name = $card_type['name'];
            $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'colonized');

            $notifArgs = array(
                'i18n' => array('card_name'),
                'card_name' => $card_type['name'],
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName()
            );

            self::incStat(1, 'territory', $player_id);

            // Yellow/blue token to gain
            self::applyTokenDeltaToPlayer($player_id, $card_type, $notifArgs);


            // Trigger this card effect on play
            $this->$method_name($player_id, $card['type'], $card_type, array(), $notifArgs);


            $this->gamestate->nextState('end');
        } else {
            // We must continue this!
            self::setGameStateValue('colonization_strength', $colonizationStrength);
            $this->gamestate->nextState('continue');
        }
    }

    function applyTokenDeltaToPlayer($player_id, $card_type, $notifArgs)
    {
        if (isset($card_type['tokendelta'])) {
            if ($card_type['tokendelta']['blue'] > 0) {
                self::gainToken($player_id, 'blue', $card_type['tokendelta']['blue'], $notifArgs);
            } else if ($card_type['tokendelta']['blue'] < 0) {
                self::loseToken($player_id, 'blue', -$card_type['tokendelta']['blue']);
            }
            if ($card_type['tokendelta']['yellow'] > 0) {
                self::gainToken($player_id, 'yellow', $card_type['tokendelta']['yellow'], $notifArgs);
            } else if ($card_type['tokendelta']['yellow'] < 0) {
                self::loseToken($player_id, 'blue', -$card_type['tokendelta']['blue']);
            }
        }
    }

    function acceptPact($choice)
    {
        self::checkAction('acceptPact');

        $pact_id = self::getGameStateValue('cardBuilt');

        $pact = $this->cards->getCard($pact_id);
        $pact_type = $this->card_types[$pact['type']];
        $players = self::loadPlayersBasicInfos();

        $target_id = self::getActivePlayerId();

        if ($choice == 0) {
            // Refuse pact

            self::DbQuery("DELETE FROM pact WHERE pact_card_id='$pact_id'");

            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} refuses ${pact_name}'), array(
                'i18n' => array('card_name'),
                'player_id' => $target_id,
                'player_name' => self::getActivePlayerName(),
                'pact_name' => $pact_type['name']
            ));

            $this->gamestate->nextState('acceptPact');
        } else {
            // Accept pact

            $pactdetails = self::getObjectFromDB("SELECT pact_a,pact_b FROM pact WHERE pact_card_id='$pact_id'");
            if ($pactdetails['pact_a'] == $target_id) {
                $player_id = $pactdetails['pact_b'];
                $a_player = $target_id;
                $b_player = $player_id;
            } else {
                $player_id = $pactdetails['pact_a'];
                $b_player = $target_id;
                $a_player = $player_id;
            }

            self::incStat(1, 'pacts', $player_id);
            self::incStat(1, 'pacts', $target_id);

            // If the proposing player already have a pact, it must be discarded
            $existing_pacts = self::getObjectListFromDB("SELECT pact_card_id FROM pact
                WHERE pact_card_id!='$pact_id' AND ( pact_a='$player_id' OR pact_b='$player_id' )");

            foreach ($existing_pacts as $existing_pact) {
                $card = $this->cards->getCard($existing_pact['pact_card_id']);

                if ($card['location_arg'] == $player_id) {
                    // The existing pact main card is in the tableau of player_id
                    if ($card['type_arg'] == 10)  // .. and if he was the proposing player ...
                        self::doCancelPact($player_id, $existing_pact['pact_card_id'], $player_id); /// ... cancel it!
                } else {
                    // The existing pact main card is on player_id partner tableau
                    if ($card['type_arg'] == 11)  // If this partner was not the proposing player, then it was player_id !
                        self::doCancelPact($player_id, $existing_pact['pact_card_id'], $player_id); /// ... cancel it!
                }

            }

            self::notifyAllPlayers('simpleNote', clienttranslate('${target_name} accepts the Pact ${card_name} with ${player_name}'), array(
                'i18n' => array('card_name'),
                'card_name' => $pact_type['name'],
                'player_name' => $players[$player_id]['player_name'],
                'target_name' => $players[$target_id]['player_name']
            ));

            if ($player_id != $a_player) {
                // In this case, the card we are going to place in proposing player tableau
                // won't be the same than the original Pact card
                // => we must ensure the card is removed from player's hand before
                self::notifyPlayer($player_id, 'discardFromHand', '', array('card_id' => $pact_id));
            }

            // On proposing player side:
            //  _ place a Pact card with type_arg = 10
            //  _ type of card is "original Pact card" if player is A, "duplicata" if player is B
            // On partner player side:
            //  _ place a Pact card with type_arg = 11
            //  _ type of card is "original Pact card" if player is A, "duplicata" if player is B


            $this->cards->moveCard($pact_id, 'tableau', $a_player);
            $pact['type_arg'] = ($a_player == $player_id ? 10 : 11);

            self::DbQuery("UPDATE card SET card_type_arg='" . $pact['type_arg'] . "' WHERE card_id='$pact_id'");

            self::notifyAllPlayers('playCard', '', array(
                'player_id' => $a_player,
                'card' => $pact,
            ));

            self::updateCardsInHand();

            // Place the Pact (bis) in target player tableau
            $pact['type_arg'] = ($b_player == $player_id ? 10 : 11);
            $this->cards->createCards(array(
                array('type' => 1000 + $pact['type'], 'type_arg' => $pact['type_arg'], 'nbr' => 1)
            ), 'tableau', $b_player);
            $pacts = $this->cards->getCardsOfTypeInLocation(1000 + $pact['type'], null, 'tableau', $b_player);
            $pact = reset($pacts);

            self::notifyAllPlayers('playCard', '', array(
                'player_id' => $b_player,
                'card' => $pact
            ));

            self::adjustPlayerIndicators($player_id);
            self::adjustPlayerIndicators($target_id);

            $this->gamestate->nextState('acceptPact');
        }
    }

    function cancelPact($card_id)
    {
        self::checkAction('politicAction');

        $player_id = self::getActivePlayerId();
        self::doCancelPact($player_id, $card_id);

        $this->gamestate->nextState("endAction");
    }

    function doCancelPact($player_id, $card_id, $force_active_player_id = null)
    {
        $players = self::loadPlayersBasicInfos();

        if ($force_active_player_id === null)
            $active_player = self::getActivePlayerId();
        else
            $active_player = $force_active_player_id;


        // Basic checks
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau')
            throw new feException("This card is not in your tableau");


        if ($card['location_arg'] != $player_id) {
            // Original card may be on partner's tableau
            // => we must find the pact card in current player's tableau
            $other_card_type = $card['type'] > 1000 ? ($card['type'] - 1000) : ($card['type'] + 1000);
            $other_cards = $this->cards->getCardsOfTypeInLocation($other_card_type, null, 'tableau', $player_id);

            if (count($other_cards) == 0)
                throw new feException("This card is not in your tableau");

            $other_card = reset($other_cards);
            $card_id = $other_card['id'];
            $card = $other_card;
        }

        $card_type = $this->card_types[$card['type']];

        if ($card_type['category'] != 'Pact')
            throw new feException("This is not a pact");

        $card_name = $card_type['name'];

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $card_type['name'],
            'player_id' => $player_id,
            'player_name' => $players[$player_id]['player_name']
        );


        // Cancel the pact by removing all cards of this type
        $other_card_type_id = $card['type'] > 1000 ? ($card['type'] - 1000) : ($card['type'] + 1000);
        $other_cards = $this->cards->getCardsOfTypeInLocation($other_card_type_id, null, 'tableau');
        if (count($other_cards) != 1)
            throw new feException("Can't find corresponding pact card");
        $other_card = reset($other_cards);
        $other_card_id = $other_card['id'];

        $pact = self::getObjectFromDB("SELECT pact_card_id, pact_a,pact_b FROM pact WHERE pact_card_id IN ('$card_id','$other_card_id')");
        $pact_id = $pact['pact_card_id'];

        self::DbQuery("DELETE FROM pact WHERE pact_card_id='$pact_id'");

        // Remove these cards from tableau
        if ($card['type'] < 1000) {
            $this->cards->moveCard($card_id, 'discardMilitary' . $card_type['age']);
            $this->cards->moveCard($other_card['id'], 'removed');
        } else {
            $this->cards->moveCard($card_id, 'removed');
            $this->cards->moveCard($other_card['id'], 'discardMilitary' . $card_type['age']);
        }

        self::removeCardApplyConsequences($player_id, $card['type']);
        self::adjustPlayerIndicators($player_id);
        self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $player_id, 'card_id' => $card_id));

        $target_id = $other_card['location_arg'];
        self::removeCardApplyConsequences($target_id, $other_card['type']);
        self::adjustPlayerIndicators($target_id);
        self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $target_id, 'card_id' => $other_card['id']));

        self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} cancels Pact ${pact_name} with ${player_name2}'), array(
            'i18n' => array('pact_name'),
            'player_id' => $player_id,
            'player_name' => $players[$active_player]['player_name'],
            'pact_name' => $card_type['name'],
            'player_name2' => $players[$target_id]['player_name']
        ));

    }

    function copyTactic($card_id)
    {
        self::checkAction('copyTactic');
        $player_id = self::getActivePlayerId();

        // => Check that player did not already play a tactic this turn
        $tacticsInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Tactics');
        if (count($tacticsInPlay) > 0) {
            throw new feException(self::_("You can play or copy a tactic only once per turn."), true);
        }

        $card = $this->cards->getCard($card_id);
        if ($card['location'] != 'common_tactics') {
            throw new feException("This tactic is not in the common tactics area");
        }

        $card_type = $this->card_types[$card['type']];

        self::spendMilitaryActions($player_id, 2);

        $previousTactic = self::getUniqueValueFromDB("SELECT player_tactic FROM player WHERE player_id = '$player_id'");
        if ($previousTactic != null) {
            if ($this->cards->getCard($previousTactic)['type'] == $card['type']) {
                throw new feException(self::_("This is already your tactic"), true);
            }
            self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $player_id, 'card_id' => $previousTactic));
        }

        self::DbQuery("UPDATE player SET player_tactic = $card_id WHERE player_id = '$player_id'");
        self::notifyAllPlayers('copyTactic', clienttranslate('${player_name} copies ${card_name} for ${cost} Military action'), array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'cost' => 2,
            'card' => $card,
            'card_name' => $card_type['name']
        ));
        self::adjustPlayerIndicators($player_id);
        $this->gamestate->nextState('endAction');
    }

    function undo()
    {
        self::checkAction('undo');

        self::undoRestorePoint();
    }

    function discardMilitaryCards($card_ids)
    {
        self::checkAction('discardMilitaryCards');

        $player_id = self::getActivePlayerId();

        $nb_to_discard = self::getMilitaryCardsToDiscardNbr($player_id);

        $discardToDefend = self::checkAction('discardToDefend', false);

        if (!$discardToDefend && count($card_ids) != $nb_to_discard)
            throw new feException(sprintf(self::_("You must discard exactly %s military card"), $nb_to_discard), true);

        $militaryActions = self::getGovernmentLimits($player_id)['militaryActions'];
        if ($discardToDefend && count($card_ids) > $militaryActions)
            throw new feException(sprintf(self::_("You cannot discard more than %s military card"), $militaryActions), true);

        // Let's have a look at these cards

        $cards = $this->cards->getCards($card_ids);
        $strength_bonus = 0;

        foreach ($cards as $card) {
            if ($card['location'] != 'hand' || $card['location_arg'] != $player_id)
                throw new feException("This card is not in your hand");


            $card_type = $this->card_types[$card['type']];

            if (self::isCardCategoryCivil($card_type['category']))
                throw new feException(sprintf(self::_('%s is a civil card'), $card_type['nametr']), true);

            $strength_bonus += $card_type['category'] != 'Bonus' ? 1 : $card_type['def'];

            // okay, card is valid to be discarded in its discard deck
            $this->cards->moveCard($card['id'], 'discardMilitary' . $card_type['age']);

            self::notifyPlayer($player_id, 'discardFromHand', '', array('card_id' => $card['id']));
        }

        self::updateCardsInHand();

        if ($discardToDefend) {
            $player_id = self::getActivePlayerId();
            $player_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$player_id'");
            $aggressor_id = self::getGameStateValue('activePlayerBeforeEffect');
            $aggressor_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$aggressor_id'");

            if ($player_strength + $strength_bonus < $aggressor_strength) {
                throw new feException(sprintf(self::_("You did not discard enough (%s strength missing)"), $aggressor_strength - $player_strength - $strength_bonus), true);
            }
            self::DbQuery("UPDATE player SET player_bid=player_bid+$strength_bonus WHERE player_id='$player_id'");
            foreach ($cards as $card) {
                $card_type = $this->card_types[$card['type']];
                if ($card_type['category'] == 'Bonus') {
                    self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} discards a bonus card from Age ${age} and gets +${strength} strength'), array(
                        'player_id' => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'age' => $card_type['age'],
                        'strength' => $card_type['def']
                    ));
                } else {
                    self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} discards a military card from Age ${age} and gets +1 strength'), array(
                        'player_id' => $player_id,
                        'player_name' => self::getActivePlayerName(),
                        'age' => $card_type['age']
                    ));
                }
            }
            $this->gamestate->nextState('aggressionResolve');
        } else if (self::getGameStateValue('currentCardEffectType') == 169) { // Politics of Strength
            $this->gamestate->nextState('endEvent');
        } else {
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} discards ${nbr} military cards'), array(
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'nbr' => $nb_to_discard
            ));
            $this->gamestate->nextState('discardMilitaryCards');
        }
    }




//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */


    function argPlayerTurn()
    {
        $player_id = self::getActivePlayerId();
        $args = self::getObjectFromDB("SELECT player_civil_actions civil, player_military_actions military FROM player WHERE player_id = '$player_id'");
        $args['increasePopulationCost'] = self::getIncreasePopulationCost($player_id);
        $args['hammurabi'] = self::isEffectActive(20);
        return $args;
    }


    function getCurrentEffectCardName()
    {
        $currentEffect = self::getGameStateValue('currentCardEffectType');
        return $this->card_types[$currentEffect]['name'];
    }

    function getCurrentEffectCardAge()
    {
        $currentEffect = self::getGameStateValue('currentCardEffectType');
        return $this->card_types[$currentEffect]['age'];
    }

    function argDestroyBuilding()
    {
        $result = self::argCurrentEffectCard();
        // Note: also used for "annex" and "infiltrate"
        $players = self::loadPlayersBasicInfos();
        $result['player_name'] = $players[self::getGameStateValue('saved_player')]['player_name'];

        return $result;
    }

    function argLossTokens()
    {
        return array(
            'nbrblue' => '<span id="nbrblue_toloose">X</span>',
            'nbryellow' => '<span id="nbryellow_toloose">X</span>',
            'nbr' => self::getCollectionFromDB("SELECT player_id, player_blue_toloose blue, player_yellow_toloose yellow FROM player")
        );
    }

    function argCurrentEffectCard()
    {
        return array(
            'i18n' => array('card_name'),
            'card_name' => self::getCurrentEffectCardName(),
            'card_type' => self::getGameStateValue('currentCardEffectType')
        );
    }

    function argBuildChoice()
    {
        $buildingId = self::getGameStateValue('cardBuilt');
        $building = $this->cards->getCard($buildingId);
        return array(
            'i18n' => array('card_name'),
            'card_name' => $this->card_types[$building['type']]['name'],
            'card_type' => $building['type'],
            'possible' => self::getAvailableChoiceFor(self::getGameStateValue('cardBuilt'))
        );
    }

    function argPlayerTurnPolitic()
    {
        $result = array();
        $joanOfArcList = $this->cards->getCardsOfTypeInLocation(70, null, 'tableau', self::getActivePlayerId());
        if (count($joanOfArcList) > 0) {
            $joanOfArc = reset($joanOfArcList);
            $result['joanOfArc'] = $joanOfArc['id'];
        }
        return $result;
    }

    function argBidTerritory()
    {
        $result = self::argCurrentEffectCard();

        $player_id = self::getActivePlayerId();

        $result['territory'] = $this->cards->getCard(self::getGameStateValue('cardBuilt'));

        $result['current_bids'] = self::getCollectionFromDB("SELECT player_id, player_bid FROM player", true);

        $minBid = 0;
        foreach ($result['current_bids'] as $pid => $bid) {
            if ($bid !== null && $bid !== 0)
                $minBid = max($bid, $minBid);
        }
        $result['min_bid'] = $minBid;

        $result['_private'] = array(
            'active' => array(
                'max_bid' => self::getPlayerMaxBid($player_id)
            )
        );

        return $result;
    }

    function argSacrificeTerritory()
    {
        $result = self::argCurrentEffectCard();
        $player_id = self::getActivePlayerId();
        $result['bid'] = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$player_id'");
        $result['strength'] = self::getGameStateValue('colonization_strength');
        return $result;
    }

    function argAggressionStrengthDefender()
    {
        $player_id = self::getActivePlayerId();
        $player_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$player_id'");
        $aggressor_id = self::getGameStateValue('activePlayerBeforeEffect');
        $aggressor_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$aggressor_id'");

        return array(
            'i18n' => array('card_name'),
            'card_name' => self::getCurrentEffectCardName(),
            'strength' => $aggressor_strength - $player_strength,
            'card_type' => self::getGameStateValue('currentCardEffectType'),
            'quantity' => self::getGovernmentLimits($player_id)['militaryActions']
        );
    }

    function argDiscardMilitaryCards()
    {
        return array(
            'nbr' => self::getMilitaryCardsToDiscardNbr()
        );
    }

    function argPickCardsFromRow()
    {
        return array(
            'i18n' => array('card_name'),
            'card_name' => self::getCurrentEffectCardName(),
            'card_type' => self::getGameStateValue('currentCardEffectType'),
            'left' => self::getGameStateValue('saved_player')
        );

    }

    function argWarOverResources()
    {
        $result = self::argCurrentEffectCard();

        $war_id = self::getGameStateValue('cardBuilt');

        $war = self::getObjectFromDB("SELECT war_force, war_winner, war_attacker, war_defender FROM war WHERE war_card_id='$war_id'");

        $players = self::loadPlayersBasicInfos();
        $result['nbr'] = $war['war_force'];
        $result['loser'] = ($war['war_winner'] == $war['war_attacker']) ? $war['war_defender'] : $war['war_attacker'];
        $result['loser_name'] = $players[$result['loser']]['player_name'];

        return $result;
    }

    function argPactMayAccept()
    {
        $result = self::argCurrentEffectCard();

        $pact_id = self::getGameStateValue('cardBuilt');

        $pact = self::getObjectFromDB("SELECT pact_a,pact_b FROM pact WHERE pact_card_id='$pact_id'");
        $players = self::loadPlayersBasicInfos();

        $player_id = self::getActivePlayerId();

        if ($player_id == $pact['pact_a']) {
            $result['a_or_b'] = 'A';
            $result['proposer'] = $players[$pact['pact_b']]['player_name'];
            $result['b_or_a'] = 'B';
        } else {
            $result['a_or_b'] = 'B';
            $result['proposer'] = $players[$pact['pact_a']]['player_name'];
            $result['b_or_a'] = 'A';
        }


        return $result;
    }

    function argDevelopmentOfCivilization()
    {
        $args = $this->argCurrentEffectCard();
        $args['increasePopulationCost'] = array();
        foreach (self::loadPlayersBasicInfos() as $player_id => $player) {
            $args['increasePopulationCost'][$player_id] = self::getIncreasePopulationCost($player_id) - 1;
        }
        return $args;
    }

    function argGainFoodOrResources()
    {
        $result = self::argCurrentEffectCard();
        switch ($result['card_type']) {
            case 193:
                $result['quantity'] = 4;
                break;
            case 114:
                $result['quantity'] = 3;
                break;
            default:
                $result['quantity'] = 2;
                break;
        }
        return $result;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stFirstPlayerTurn()
    {
        $currentAge = self::getGameStateValue('currentAge');
        $game_version = self::getGameStateValue('game_version');

        // Must check game end
        // Note: complete version: each player plays a final turn if Age 3 cards are not exhausted during first player turn.
        if (self::getGameStateValue('completeLastTurn') == 1) {
            // Trigger the end of game
            $this->gamestate->nextState('endGame');
            return;
        } else {
            self::incGameStateValue('turnNumber', 1);
            self::incStat(1, 'turns_number');

            if ($game_version == 1 && $currentAge == 3 || $currentAge == 4) {
                self::setGameStateValue('completeLastTurn', 1);
                self::notifyAllPlayers('lastTurn', clienttranslate("This is the last turn!"), array());
            }

            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stStartPlayerTurn()
    {
        $player_id = self::getActivePlayerId();
        $firstPlayer = self::getGameStateValue('firstPlayer');
        $turnNumber = self::getGameStateValue('turnNumber');

        if ($turnNumber == 1) {
            // First turn: exception: each player has a number of civil action = to number of players that already played
            $players = self::loadPlayersBasicInfos();
            $nextPlayer = self::createNextPlayerTable(array_keys($players));
            $nbrAction = 1;
            $previous_player = $firstPlayer;
            while ($player_id != $previous_player) {
                $nbrAction++;
                $previous_player = $nextPlayer[$previous_player];
            }
            self::dbQuery("UPDATE player SET player_civil_actions = $nbrAction WHERE player_id = '$player_id'");
            $this->gamestate->nextState('firstTurn');
            return;
        }

        // Remove from game cards at the beginning of the card set
        $players = self::loadPlayersBasicInfos();
        $nbr_non_eliminated = 0;
        foreach ($players as $thisplayer_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0)
                $nbr_non_eliminated++;
        }
        $remove_first_nbr = 5 - $nbr_non_eliminated;  // 3 with 2 players, 2 with 3 players, 1 with four players
        $cards_removed = array();
        $cards = $this->cards->getCardsInLocation('card_row', null, 'location_arg');
        foreach ($cards as $card) {
            if ($card['location_arg'] <= $remove_first_nbr) {
                // Remove this card from the card row
                $this->cards->moveCard($card['id'], 'removed');
                $cards_removed[] = $card['id'];
            }
        }
        if (count($cards_removed) > 0) {
            self::notifyAllPlayers('card_row_remove', '', array('card_ids' => $cards_removed));
        }

        self::refillCardRow($player_id);

        // Effects that occurs at the beginning of the turn
        self::ttaEvent('roundBeginning', $player_id);

        self::adjustPlayerIndicators($player_id);

        // Make Tactics Available
        $tacticsInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Tactics');
        if (count($tacticsInPlay) > 0) {
            $tactic = reset($tacticsInPlay);
            $tactic_card_type = $tactic['type'];
            $tactic_type = $this->card_types[$tactic_card_type];
            if (self::getUniqueValueFromDB("SELECT 1 FROM card WHERE card_type = '$tactic_card_type' AND card_location = 'common_tactics' LIMIT 1") == null) {
                $this->cards->moveCard($tactic['id'], 'common_tactics');
                self::notifyAllPlayers('tacticMadeAvailable', clienttranslate('${card_name} is now available for copy in the common tactics area'), array(
                    'i18n' => array('card_name'),
                    'player_id' => $player_id,
                    'card' => $tactic,
                    'card_name' => $tactic_type['name']
                ));
            } else {
                $this->cards->moveCard($tactic['id'], 'removed');
            }
        }

        if ($turnNumber == 2) {
            // Turn 2 => skip political action
            $this->gamestate->nextState('normalTurn');
            return;
        }

        // War result (complete version)
        $war = self::getObjectFromDB("SELECT war_card_id id, war_defender defender FROM war WHERE war_attacker='$player_id'");
        if ($war !== null) {
            $target_id = $war['defender'];

            // Pacts ///////////////////////////
            $strength_bonus = 0;
            $pacts = self::getPactsBetween($player_id, $target_id);
            foreach ($pacts as $pact) {
                if ($pact['card_type'] == 81)  // Open border agreement
                {
                    $strength_bonus = 2;
                    self::notifyAllPlayers('simpleNote', clienttranslate('Using +2 bonus from ${card_name}...'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[$pact['card_type']]['name']
                    ));
                }
            }


            // Mark war card as current card
            $card = $this->cards->getCard($war['id']);
            self::setGameStateValue('activePlayerBeforeEffect', self::getActivePlayerId());
            self::setGameStateValue('currentCardEffectType', $card['type']);
            self::setGameStateValue('cardBuilt', $war['id']);
            self::DbQuery("UPDATE player SET player_bid=player_strength WHERE 1");    // Player base strength without sacrifice / bonus

            if ($strength_bonus > 0)
                self::DbQuery("UPDATE player SET player_bid=player_bid+$strength_bonus WHERE player_id='$player_id'");


            self::setGameStateValue('sacrificedInf', 0);
            self::setGameStateValue('sacrificedCav', 0);
            self::setGameStateValue('sacrificedArty', 0);
            self::setGameStateValue('sacrificedAir', 0);
            self::setGameStateValue('sacrificedTot', 0);
            self::setGameStateValue('saved_player', $war['defender']);

            $this->gamestate->changeActivePlayer($war['defender']);
            $this->gamestate->nextState('resolveWar');
            return;
        }

        // Political action
        $this->gamestate->nextState('advancedTurn');
    }

    // Adjust the current number of action / used action of active player
    function stAdjustPlayerActions()
    {
        self::adjustPlayerActions(self::getActivePlayerId());
        $this->gamestate->nextState('');
    }

    function adjustPlayerActions($player_id, $didRevolution = false)
    {
        // Get government limit
        $govlimits = self::getGovernmentLimits($player_id);
        // Get player state
        $player = self::getObjectFromDB("SELECT player_civil_actions, player_civil_actions_used, player_military_actions, player_military_actions_used FROM player WHERE player_id = '$player_id'");
        $remainingCivil = $player['player_civil_actions'];
        $usedCivil = $player['player_civil_actions_used'];
        $remainingMilitary = $player['player_military_actions'];
        $usedMilitary = $player['player_military_actions_used'];

        $notifArgs = array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName()
        );

        $hasRobespierre = self::isCardInTableau($player_id, 148);

        $govlimits['militaryActions'] += $this->countEffect(82); // Patriotism cards

        $civilDelta = $govlimits['civilActions'] - $remainingCivil - $usedCivil;
        $milDelta = $govlimits['militaryActions'] - $remainingMilitary - $usedMilitary;

        if ($civilDelta > 0) {
            if (!$didRevolution || $hasRobespierre) {
                self::dbQuery("UPDATE player SET player_civil_actions = player_civil_actions + $civilDelta WHERE player_id = '$player_id'");
                $notifArgs['nbr'] = $civilDelta;
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} gains ${nbr} civil action(s)'), $notifArgs);
            } else {
                self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used + $civilDelta WHERE player_id = '$player_id'");
            }
        } else if ($civilDelta < 0) {
            if ($usedCivil >= -$civilDelta) {
                $toRemove = -$civilDelta;
                self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used - $toRemove WHERE player_id = '$player_id'");
            } else {
                $toRemove = -$civilDelta - $usedCivil;
                self::dbQuery("UPDATE player SET player_civil_actions_used = 0, player_civil_actions = player_civil_actions - $toRemove WHERE player_id = '$player_id'");
            }
            $notifArgs['nbr'] = -$civilDelta;
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} loses ${nbr} civil action(s)'), $notifArgs);
        }

        if ($milDelta > 0) {
            if (!$didRevolution || !$hasRobespierre) {
                self::dbQuery("UPDATE player SET player_military_actions = player_military_actions + $milDelta WHERE player_id = '$player_id'");
                $notifArgs['nbr'] = $milDelta;
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} gains ${nbr} military action(s)'), $notifArgs);
            } else {
                self::dbQuery("UPDATE player SET player_military_actions_used = player_military_actions_used + $milDelta WHERE player_id = '$player_id'");
            }
        } else if ($milDelta < 0) {
            if ($usedMilitary >= -$milDelta) {
                $toRemove = -$milDelta;
                self::dbQuery("UPDATE player SET player_military_actions_used = player_military_actions_used - $toRemove WHERE player_id = '$player_id'");
            } else {
                $toRemove = -$milDelta - $usedMilitary;
                self::dbQuery("UPDATE player SET player_military_actions_used = 0, player_military_actions = player_military_actions - $toRemove WHERE player_id = '$player_id'");
            }
            $notifArgs['nbr'] = -$milDelta;
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} loses ${nbr} military action(s)'), $notifArgs);
        }
    }

    function stPlayerTurn()
    {
        $player_id = self::getActivePlayerId();
        if (self::getGameStateValue('undoSavedPlayer') != $player_id) {
            self::setGameStateValue('undoSavedPlayer', $player_id);
            self::undoSavepoint();
        }
    }

    function refillCardRow($current_player_id)
    {
        $cards = $this->cards->getCardsInLocation('card_row', null, 'location_arg');
        $currentAge = self::getGameStateValue('currentAge');
        $current_age_char = self::ageNumToChar($currentAge);
        $game_version = self::getGameStateValue('game_version');
        $firstPlayer = self::getGameStateValue('firstPlayer');
        $turnNumber = self::getGameStateValue('turnNumber');

        // Slide all cards to the left
        $new_card_position = 1;
        foreach ($cards as $card) {
            // Replace at correct positon
            $this->cards->moveCard($card['id'], 'card_row', $new_card_position);
            $new_card_position++;
        }

        // Fill remaining positions
        while ($new_card_position <= 13) {
            // Pick a Civil card from current Age deck
            $new_card = $this->cards->pickCardForLocation('civil' . $current_age_char, 'card_row', $new_card_position);
            $remaining_cards = $this->cards->countCardInLocation('civil' . $current_age_char);

            if ($new_card == null || $remaining_cards == 0) {
                // if empty, trigger the End of Age, then pick a card from the next deck (if any)

                if ($currentAge == 1 || $currentAge == 2) {
                    if ($current_age_char == 'I' || $current_age_char == 'II') // Note: double check, see #730
                    {
                        // Save player scores in stats
                        $player_to_score = self::getCollectionFromDB("SELECT player_id, player_score FROM player", true);
                        foreach ($player_to_score as $player_id => $score) {
                            self::setStat($score, 'score_end_of_' . $current_age_char, $player_id);
                        }
                    }
                }


                if ($game_version == 1 && $current_age_char == 'III' || $current_age_char == 'IV') {
                    // Already the end of game => no need to take any action
                } else {
                    $current_age = self::incGameStateValue('currentAge', 1);
                    $current_age_char = self::ageNumToChar($current_age);

                    if ($game_version == 1 && $current_age_char == 'III' || $current_age_char == 'IV') {
                        // End of game
                        self::notifyAllPlayers('endGameBegins', clienttranslate('No more cards: the end of game begins!'), array());
                        if ($current_player_id == $firstPlayer) {
                            // Note: in the complete version, if Age III ends during the starting player's turn, that is the last round.
                            self::setGameStateValue('completeLastTurn', 1);
                            self::notifyAllPlayers('lastTurn', clienttranslate("This is the last turn!"), array());
                        }
                    } else {
                        self::notifyAllPlayers('newAge', clienttranslate('${age} begins!'), array(
                            'i18n' => array('age'),
                            'age' => self::getAgeName($current_age_char, false),
                            'age_char' => $current_age_char
                        ));

                        self::setupAge($current_age_char);

                        if ($new_card === null) {
                            $this->cards->pickCardForLocation('civil' . $current_age_char, 'card_row', $new_card_position);
                        }
                    }

                    if ($current_age >= 2) {
                        self::endOfAgeRemoveTokens();
                        // Cards from age N-2 are now obsolete
                        self::setObsolete(self::ageNumToChar($current_age - 2));
                        $players = self::loadPlayersBasicInfos();
                        foreach ($players as $player_id => $player) {
                            self::adjustPlayerActions($player_id);
                            self::adjustPlayerIndicators($player_id);
                        }
                    }
                }
            }

            $new_card_position++;
        }

        // Specific: after the first refill, go to Middle Ages
        if ($current_age_char == 'A' && $turnNumber > 1) {
            $current_age = self::incGameStateValue('currentAge', 1);
            $current_age_char = self::ageNumToChar($current_age);

            self::notifyAllPlayers('newAge', clienttranslate('${age} begins!'), array(
                'i18n' => array('age'),
                'age' => self::getAgeName($current_age_char, false),
                'age_char' => $current_age_char
            ));

            self::setupAge('I');
        }

        $remaining = $this->cards->countCardInLocation('civil' . $current_age_char);
        $remainingMil = $this->cards->countCardInLocation('military' . $current_age_char);

        // Finally, notify the whole card row
        $cards = $this->cards->getCardsInLocation('card_row', null, 'location_arg');
        self::notifyAllPlayers('card_row_update', '', array(
            'cards' => $cards,
            'age' => $current_age_char,
            'remaining' => $remaining,
            'remainingMil' => $remainingMil
        ));
        foreach (self::loadPlayersBasicInfos() as $player_id => $player) {
            self::notifyCardRowCosts($player_id, $cards);
        }
    }

    function stDiscardMilitaryCards()
    {
        if (self::getMilitaryCardsToDiscardNbr() <= 0) {
            $this->gamestate->nextState('endOfTurn');
        }
    }

    function stAggressionOpponentDefense()
    {
        self::setGameStateValue('sacrificedInf', 0);
        self::setGameStateValue('sacrificedCav', 0);
        self::setGameStateValue('sacrificedArty', 0);
        self::setGameStateValue('sacrificedAir', 0);
        self::setGameStateValue('sacrificedTot', 0);

        $aggressor = self::getActivePlayerId();
        $defender = self::getGameStateValue('saved_player');
        $aggressor_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$aggressor'");
        $defenderPotentialStrength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$defender'");

        $hand = $this->cards->getCardsInLocation('hand', $defender);
        foreach ($hand as $card) {
            $cardType = $this->card_types[$card['type']];
            if (!self::isCardCategoryCivil($cardType['type'])) {
                $defenderPotentialStrength += self::ageCharToNum($cardType['age']) * 2;
            }
        }

        $this->gamestate->changeActivePlayer($defender);
        if ($defenderPotentialStrength >= $aggressor_strength) {
            $this->gamestate->nextState('aggressionMaySacrifice');
        } else {
            $this->gamestate->nextState('aggressionResolve');
        }
    }

    function stPactMayAcceptNext()
    {
        $this->gamestate->changeActivePlayer(self::getGameStateValue('saved_player'));
        $this->gamestate->nextState('');
    }


    function stEndOfAction()
    {
        if (self::getUniqueValueFromDB("SELECT COUNT( player_id ) FROM player
            WHERE player_blue_toloose>0 OR player_yellow_toloose>0") > 0) {
            $this->gamestate->nextState("removeTokens");  // Must remove extra tokens first
            return;
        }

        self::resetEndActionEffects();

        self::setGameStateValue('currentCardEffectType', 0);

        $player_id = self::getActivePlayerId();
        self::adjustPlayerIndicators($player_id);

        self::notifyCardRowCosts($player_id);

        if (self::getGameStateValue('turnNumber') == 1)
            $this->gamestate->nextState('continueFirstTurn');
        else
            $this->gamestate->nextState('continue');
    }

    function stEndOfTurn()
    {
        $player_id = self::getActivePlayerId();

        // Automatically give 3 culture if Churchill was not used
        if (self::isEffectActive(186)) {
            self::winston_churchill_III_getCulture($player_id);
        }

        // Genghis Khan gives now gives 3 culture if
        if (self::isCardInTableau($player_id, 60)) {
            self::genghis_khan_I_getculture($player_id);
        }

        $player_discontent = self::getUniqueValueFromDB("SELECT player_discontent FROM player WHERE player_id='$player_id'");
        $player_avail = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id='0'");
        if ($player_discontent > $player_avail) {
            // Uprising!
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} has more Discontent Workers than Unused Worker: Uprising! No production phase.'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $player_id
            ));

            self::incStat(1, 'uprising', $player_id);
        } else {
            // Get all cards in tableau
            // Note: we order by card_type DESC in order to produce on most recent cards first
            //   (in case there is not enough blue tokens)
            $cards = $this->cards->getCardsInLocation('tableau', $player_id, 'card_type DESC');

            // Get yellow tokens on cards
            $card_to_yellow_token = self::getCollectionFromDb("SELECT token_card_id, COUNT( token_id ) FROM token
                        WHERE token_card_id IS NOT NULL AND token_type='yellow'
                        GROUP BY token_card_id ", true);

            // Advanced version: if there is an uprising, skip Production & Maintenance

            // Production //////////////////////////////

            // Progress on Culture & Science
            $progression = self::getObjectFromDB("SELECT player_culture, player_science FROM player WHERE player_id='$player_id'");
            $sql = "UPDATE player SET
                        player_score=player_score+'" . $progression['player_culture'] . "',
                        player_science_points=player_science_points+'" . $progression['player_science'] . "'
                        WHERE player_id='$player_id'";
            self::DbQuery($sql);

            self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} gains ${culture} points of culture and ${science} points of science'), array(
                'player_name' => self::getActivePlayerName(),
                'player_id' => $player_id,
                'culture' => $progression['player_culture'],
                'science' => $progression['player_science']
            ));

            self::incStat($progression['player_culture'], 'points_culture_progression', $player_id);
            self::incStat($progression['player_science'], 'total_science_prod', $player_id);

            // Advanced version: corruption
            self::applyCorruption($player_id);

            // Get available blue tokens
            $available_blue = self::getObjectListFromDb("SELECT token_id FROM token
                    WHERE token_player_id='$player_id'
                    AND token_card_id IS NULL
                    AND token_type='blue' ", true);

            self::produceFood($cards, $card_to_yellow_token, $available_blue);

            self::applyConsumption($player_id);

            // Get available blue tokens (again)
            $available_blue = self::getObjectListFromDb("SELECT token_id FROM token
                    WHERE token_player_id='$player_id'
                    AND token_card_id IS NULL
                    AND token_type='blue' ", true);
            self::produceRess($cards, $card_to_yellow_token, $available_blue);

        }

        // Pick military cards for remaining military action
        $remaining_mil = self::getUniqueValueFromDB("SELECT player_military_actions FROM player WHERE player_id = '$player_id'");

        if ($remaining_mil > 0) {
            $remaining_mil = min(3, $remaining_mil);  // Max 3 / turn
            self::pickMilitaryCardsForPlayer($player_id, $remaining_mil);

            self::incStat($remaining_mil, 'military_cards_picked', $player_id);

            self::updateCardsInHand();
        }

        // Reset player actions
        $govlimits = self::getGovernmentLimits($player_id);
        $player_civil_actions = $govlimits['civilActions'];
        $player_military_actions = $govlimits['militaryActions'];
        self::dbQuery("UPDATE player SET
                  player_civil_actions = $player_civil_actions,
                  player_civil_actions_used = 0,
                  player_military_actions = $player_military_actions,
                  player_military_actions_used = 0
                WHERE player_id = '$player_id'");

        // Reset all "action card cannot be played on first turn" markers
        self::DbQuery("UPDATE card SET card_type_arg='0' WHERE card_type_arg='-1'");

        self::nextPlayerTurn($player_id);
    }

    function stConcedeGame()
    {
        $player_id = self::getActivePlayerId();
        self::nextPlayerTurn($player_id);
    }

    function nextPlayerTurn($player_id)
    {
        // Set all "until the end of this turn effects"
        self::resetEndOfTurnEffect();

        // Is next player the first player?
        $players = self::loadPlayersBasicInfos();
        $firstPlayer = self::getGameStateValue('firstPlayer');
        $bFirstPlayerTurn = false;

        do {
            $nextPlayer = self::activeNextPlayer();

            if ($nextPlayer == $firstPlayer)
                $bFirstPlayerTurn = true;
        } while (($players[$nextPlayer]['player_eliminated'] != 0 || $players[$nextPlayer]['player_zombie'] != 0) && $nextPlayer != $player_id);

        self::giveExtraTime($nextPlayer);

        if ($bFirstPlayerTurn) {
            if ($nextPlayer != $firstPlayer) {
                // If first player has conceded, we must update the first player to prevent bugs at end of game
                self::setGameStateValue('firstPlayer', $nextPlayer);
            }
            $this->gamestate->nextState('nextFirstPlayer');
        } else {
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function produceFood($cards, $card_to_yellow_token, $available_blue)
    {
        $total_produced = array();
        $players = self::loadPlayersBasicInfos();
        $internationalTradeAgreement = null;

        // Produce food
        // => add 1 blue token for each yellow token on farm card
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];

            if (!isset($total_produced[$player_id]))
                $total_produced[$player_id] = 0;

            if (isset($this->card_types[$card['type']]['food'])) {
                if ($this->card_types[$card['type']]['food'] > 0) {
                    // This card can produce food
                    if (isset($card_to_yellow_token[$card['id']])) {
                        $food_produced = $card_to_yellow_token[$card['id']];  // Note: number of tokens produced

                        $total_produced[$player_id] += $food_produced * $this->card_types[$card['type']]['food'];

                        if ($available_blue !== null) {
                            $tokens_to_move = array();
                            $tokens_to_move_datas = array();
                            for ($i = 0; $i < $food_produced; $i++) {
                                if (count($available_blue) > 0) {
                                    $token_id = array_pop($available_blue);
                                    $tokens_to_move[] = $token_id;
                                    $tokens_to_move_datas[] = array(
                                        'id' => $token_id,
                                        'player' => $player_id,
                                        'type' => 'blue',
                                        'card_id' => $card['id']
                                    );
                                }
                            }

                            if (count($tokens_to_move) < $food_produced) {
                                self::notifyAllPlayers('notEnoughBlue', clienttranslate("There are not enough blue token to produce all the food"), array());
                            }

                            if (count($tokens_to_move) > 0) {
                                $sql = "UPDATE token SET token_card_id='" . $card['id'] . "'
                                        WHERE token_id IN ('" . implode("','", $tokens_to_move) . "')";
                                self::DbQuery($sql);

                                self::notifyAllPlayers('moveTokens', '', array(
                                    'tokens' => $tokens_to_move_datas
                                ));
                            }
                        }
                    }
                }
            }
            if ($card['type'] == 1141) { // International Trade Agreement as B player
                $internationalTradeAgreement = $player_id;
                $total_produced[$player_id]++;
            }
        }

        if ($internationalTradeAgreement && $available_blue !== null) {
            self::getBlueTokens($internationalTradeAgreement, 'food', 1, clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), array(
                'i18n' => array('card_name'),
                'player_name' => $players[$internationalTradeAgreement]['player_name'],
                'card_name' => $this->card_types[1141]['name'],
                'nbr' => 1
            ));
        }

        if ($available_blue !== null) {
            foreach ($total_produced as $player_id => $prod) {
                self::incStat($prod, 'total_food_prod', $player_id);

                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} produces ${nbr} food'), array(
                    'player_name' => $players[$player_id]['player_name'],
                    'nbr' => $prod
                ));

            }
            return $available_blue;
        } else
            return $total_produced;
    }

    function produceRess($cards, $card_to_yellow_token, $available_blue)
    {
        $total_produced = array();
        $player_to_bOneMoreBlue = array();
        $player_to_bill_gates = array();
        $players = self::loadPlayersBasicInfos();
        $internationalTradeAgreement = false;

        // Get best mine
        $player_to_bestmine = array();
        foreach ($cards as $card) {
            if (isset($card_to_yellow_token[$card['id']])) {
                $player_id = $card['location_arg'];
                if (!isset($player_to_bill_gates[$player_id]))
                    $player_to_bill_gates[$player_id] = self::isCardInTableau($player_id, 238); // Bill Gates

                $cardType = $this->card_types[$card['type']];
                if ($cardType['type'] == 'Mine') {
                    if (!isset($player_to_bestmine[$player_id]) || $cardType['ress'] > $player_to_bestmine[$player_id]['ress'])
                        $player_to_bestmine[$player_id] = $cardType;
                }
            }
            if ($card['type'] == 141) { // International Trade Agreement as B player
                $internationalTradeAgreement = $card['location_arg'];
            }
        }


        // Produce resources
        // => add 1 blue token for each yellow token on mine card
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];

            if (!isset($total_produced[$player_id])) {
                $total_produced[$player_id] = 0;
                if ($internationalTradeAgreement == $player_id) {
                    $total_produced[$player_id]++;
                }
            }

            if (!isset($player_to_bOneMoreBlue[$player_id]))
                $player_to_bOneMoreBlue[$player_id] = self::isCardInTableau($player_id, 178);   // Transcontinental railroad
            $bOneMoreBlue = $player_to_bOneMoreBlue[$player_id];

            $hasBillGates = $player_to_bill_gates[$player_id];

            $ress = 0;

            $cardType = $this->card_types[$card['type']];

            if (isset($cardType['ress'])) {
                if ($cardType['ress'] > 0) {
                    $ress = $cardType['ress'];
                }
            }

            if ($hasBillGates && $cardType['type'] == 'Lab') {
                $ress = self::ageCharToNum($cardType['age']);
            }

            if ($ress > 0) {
                // This card can produce ress
                if (isset($card_to_yellow_token[$card['id']])) {
                    $ress_produced = $card_to_yellow_token[$card['id']];  // Note: number of tokens produced

                    if ($bOneMoreBlue && $player_to_bestmine[$player_id]['name'] == $cardType['name']) {
                        $ress_produced++;
                        $bOneMoreBlue = false;
                    }

                    $total_produced[$player_id] += $ress_produced * $ress;

                    if ($available_blue !== null) {
                        $tokens_to_move = array();
                        $tokens_to_move_datas = array();
                        for ($i = 0; $i < $ress_produced; $i++) {
                            if (count($available_blue) > 0) {
                                $token_id = array_pop($available_blue);

                                $tokens_to_move[] = $token_id;
                                $tokens_to_move_datas[] = array(
                                    'id' => $token_id,
                                    'player' => $player_id,
                                    'type' => 'blue',
                                    'card_id' => $card['id']
                                );
                            }
                        }


                        if (count($tokens_to_move) < $ress_produced) {
                            self::notifyAllPlayers('notEnoughBlue', clienttranslate("There are not enough blue token to produce all the resources"), array());
                        }

                        if (count($tokens_to_move) > 0) {
                            $sql = "UPDATE token SET token_card_id='" . $card['id'] . "'
                                    WHERE token_id IN ('" . implode("','", $tokens_to_move) . "')";
                            self::DbQuery($sql);

                            self::notifyAllPlayers('moveTokens', '', array(
                                'tokens' => $tokens_to_move_datas
                            ));
                        }
                    }
                }
            }

            $player_to_bOneMoreBlue[$player_id] = $bOneMoreBlue;
        }

        if ($internationalTradeAgreement && $available_blue !== null) {
            self::getBlueTokens($player_id, 'ress', 1, clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), array(
                'i18n' => array('card_name'),
                'player_name' => $players[$player_id]['player_name'],
                'card_name' => $this->card_types[141]['name'],
                'nbr' => 1
            ));
        }

        if ($available_blue !== null) {
            foreach ($total_produced as $player_id => $prod) {
                self::incStat($prod, 'total_resource_prod', $player_id);

                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} produces ${nbr} resources'), array(
                    'player_name' => $players[$player_id]['player_name'],
                    'nbr' => $prod
                ));
            }

            return $available_blue;
        } else
            return $total_produced;
    }

    function applyConsumption($player_id)
    {
        // Consume food (or loose 4 points per missing food)

        $hunger_total = 0;
        $players = self::loadPlayersBasicInfos();
        $player_name = $players[$player_id]['player_name'];

        // Get available yellow tokens
        $available_yellow = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token
                WHERE token_player_id='$player_id'
                AND token_card_id IS NULL
                AND token_type='yellow' ");

        if ($available_yellow > 30)
            $food_consumption = 0;
        else
            $food_consumption = $this->food_consumption[$available_yellow];

        if ($food_consumption < 0) {
            $availables = self::getBlueTokenTotalValue($player_id, 'food');

            if ($availables >= abs($food_consumption)) {
                // No problem, we can spend all
                self::spendBlueTokens($player_id, 'food', -$food_consumption);
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} population consumes ${food} food'), array(
                    'player_id' => $player_id,
                    'player_name' => $player_name,
                    'food' => -$food_consumption
                ));

                self::incStat(abs($food_consumption), 'total_food_consumption', $player_id);
            } else {
                // Hunger

                // Spend all remainings
                self::spendBlueTokens($player_id, 'food', $availables);

                self::incStat(abs($availables), 'total_food_consumption', $player_id);

                // -4 points for each missing
                $delta = 4 * (abs($food_consumption) - $availables);
                self::DbQuery("UPDATE player SET player_score=GREATEST(0, player_score-$delta) WHERE player_id='$player_id'");

                self::incStat($delta, 'points_hunger', $player_id);

                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} population consumes ${food} food and is hungry: ${culture} points!'), array(
                    'player_id' => $player_id,
                    'player_name' => $player_name,
                    'culture' => -$delta,
                    'science' => 0,
                    'food' => $availables
                ));

                $hunger_total = $delta;
            }
        }

        return $hunger_total;
    }

    function applyCorruption($player_id)
    {
        // Consume resource
        $players = self::loadPlayersBasicInfos();
        $player_name = $players[$player_id]['player_name'];

        // Get available blue tokens
        $available_blue = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token
                WHERE token_player_id='$player_id'
                AND token_card_id IS NULL
                AND token_type='blue' ");

        if ($available_blue > 10)
            $corruption = 0;
        else
            $corruption = abs($this->resource_corruption[$available_blue]);

        if ($corruption > 0) {
            $resourcesLost = self::spendBlueTokens($player_id, 'ress', $corruption, true);
            self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} loss ${ress} resource(s) due to corruption'), array(
                'player_id' => $player_id,
                'player_name' => $player_name,
                'ress' => $resourcesLost
            ));

            if ($resourcesLost < $corruption) {
                // Not enough resources, food should be lost now
                self::spendBlueTokens($player_id, 'food', $corruption - $resourcesLost, true);
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} loses ${food} food due to corruption'), array(
                    'player_id' => $player_id,
                    'player_name' => $player_name,
                    'food' => $corruption - $resourcesLost
                ));

            }

            self::incStat($corruption, 'total_corruption_lost', $player_id);
        }

        return $corruption;
    }


    function stPickEvent()
    {
        // Reveal the first event of "Current Events" deck & apply it
        $event = self::getNextEvent();

        $this->cards->moveCard($event['id'], 'pastEvents');
        $player_id = self::getActivePlayerId();
        if ($this->cards->countCardInLocation('events') == 0) {
            // No more current events!

            $this->cards->moveAllCardsInLocation('future_events', 'events');
            $this->cards->shuffle('events');

            self::notifyAllPlayers("simpleNote", clienttranslate("The Future Events deck is shuffled and placed on Current Events deck"), array());
        }

        $card_type = $this->card_types[$event['type']];

        self::notifyEventDeckChange();

        if ($card_type['type'] == 'Territory') {
            // Territory: each player gets a chance to colonize this one

            self::notifyAllPlayers('revealTerritory', clienttranslate('New territory: ${event_name}'), array(
                'i18n' => array('event_name'),
                'card' => $event,
                'event_name' => $card_type['name']
            ));

            self::setGameStateValue('sacrificedInf', 0);
            self::setGameStateValue('sacrificedCav', 0);
            self::setGameStateValue('sacrificedArty', 0);
            self::setGameStateValue('sacrificedAir', 0);
            self::setGameStateValue('sacrificedTot', 0);

            self::setGameStateValue('activePlayerBeforeEffect', self::getActivePlayerId());
            self::setGameStateValue('currentCardEffectType', $event['type']);
            self::setGameStateValue('cardBuilt', $event['id']);
            self::DbQuery("UPDATE player SET player_bid=NULL WHERE 1");   // Reset all bids
            $this->gamestate->nextState('bidTerritory');
        } else {
            // Standard Event

            self::notifyAllPlayers('revealEvent', clienttranslate('New event: ${event_name}'), array(
                'i18n' => array('event_name'),
                'card' => $event,
                'event_name' => $card_type['name']
            ));

            $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'event');

            $notifArgs = array(
                'i18n' => array('card_name'),
                'card_name' => $card_type['name'],
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName()
            );

            $nextState = $this->$method_name($player_id, $event['type'], $card_type, array(), $notifArgs);

            if ($nextState === null) {
                // Ok, event has been applied

                $players = self::loadPlayersBasicInfos();

                foreach ($players as $player_id => $player) {
                    self::adjustPlayerIndicators($player_id);
                }

                $this->gamestate->nextState('endEvent');
            } else {
                self::setGameStateValue('activePlayerBeforeEffect', $player_id);
                self::setGameStateValue('currentCardEffectType', $event['type']);
                $this->gamestate->nextState($nextState);
            }
        }
    }

    // Note: is used for end of event, but also for end of aggression and war
    function stEndEvent()
    {
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            self::adjustPlayerIndicators($player_id);
        }

        $active_player_id = self::getGameStateValue('activePlayerBeforeEffect');
        $this->gamestate->changeActivePlayer($active_player_id);


        $war = self::getObjectFromDB("SELECT war_card_id id, war_defender defender FROM war WHERE war_attacker='$active_player_id'");
        if ($war !== null) {
            // Is this "endEvent" the end of the resolution of a war?
            if (self::getGameStateValue('cardBuilt') == $war['id']) {
                $war_id = $war['id'];
                self::DbQuery("DELETE FROM war WHERE war_card_id='$war_id'");

                $this->gamestate->nextState('endWar');
                return;
            }
        }

        self::setGameStateValue('currentCardEffectType', 0);

        $this->gamestate->nextState('endEvent');
    }

    function stAggressionResolve()
    {
        // Resolve aggression

        $aggression_card_id = self::getGameStateValue("cardBuilt");
        $aggression_card = $this->cards->getCard($aggression_card_id);

        $players = self::loadPlayersBasicInfos();

        $defender_id = self::getActivePlayerId();
        $aggressor_id = self::getGameStateValue("activePlayerBeforeEffect");

        // In any case, discard the aggression card
        $this->cards->moveCard($aggression_card_id, 'discardMilitary' . $this->card_types[$aggression_card['type']]['age']);
        self::notifyPlayer($aggressor_id, 'discardFromHand', '', array('card_id' => $aggression_card_id));

        // Who wins?

        $defender_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$defender_id'");
        $aggressor_strength = self::getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$aggressor_id'");
        $card_type = $this->card_types[$aggression_card['type']];

        if ($card_type['category'] == 'Aggression') {
            if ($defender_strength >= $aggressor_strength) {
                self::notifyAllPlayers("simpleNote", clienttranslate('${player_name} (strength ${strength}) repels aggression from ${player_name2} (strength ${aggstrength})'), array(
                    'player_name' => $players[$defender_id]['player_name'],
                    'strength' => $defender_strength,
                    'player_name2' => $players[$aggressor_id]['player_name'],
                    'aggstrength' => $aggressor_strength,
                ));

                self::incStat(1, 'aggression_won', $defender_id);

                $this->gamestate->changeActivePlayer($aggressor_id);

                $this->gamestate->nextState('end');
            } else {
                // Aggressor wins !


                self::notifyAllPlayers("simpleNote", clienttranslate('${player_name2} (strength ${aggstrength}) does a successful aggression with ${card_name} on ${player_name} (strength ${strength})'), array(
                    'i18n' => array('card_name'),
                    'player_name' => $players[$defender_id]['player_name'],
                    'strength' => $defender_strength,
                    'player_name2' => $players[$aggressor_id]['player_name'],
                    'aggstrength' => $aggressor_strength,
                    'card_name' => $card_type['name']
                ));

                self::incStat(1, 'aggression_won', $aggressor_id);

                $this->gamestate->changeActivePlayer($aggressor_id);

                // Now, active this card special effect
                $card_name = $card_type['name'];
                $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'aggression');

                $notifArgs = array(
                    'i18n' => array('card_name'),
                    'card_name' => $card_type['name'],
                    'player_id' => $aggressor_id,
                    'player_name' => self::getActivePlayerName()
                );

                $nextState = $this->$method_name($aggressor_id, $aggression_card['type'], $card_type, $defender_id, $notifArgs);

                if ($nextState == null)
                    $this->gamestate->nextState('end');
                else
                    $this->gamestate->nextState($nextState);
            }
        } else if ($card_type['category'] == 'War') {
            // Discard War card from tableau
            self::notifyAllPlayers('discardFromTableau', '', array(
                'player_id' => $aggressor_id,
                'card_id' => $aggression_card_id
            ));

            if ($defender_strength == $aggressor_strength) {
                self::notifyAllPlayers("warOver", clienttranslate('The ${card_name} between ${player_name} and ${player_name2} results in a tie! (strength ${strength})'), array(
                    'i18n' => array('card_name'),
                    'player_name' => $players[$aggressor_id]['player_name'],
                    'strength' => $aggressor_strength,
                    'player_name2' => $players[$defender_id]['player_name'],
                    'card_name' => $card_type['name'],
                    'id' => $aggression_card_id
                ));
                self::DbQuery("DELETE FROM war WHERE war_card_id='$aggression_card_id'");
                $this->gamestate->changeActivePlayer($aggressor_id);
                $this->gamestate->nextState('endWar');
            } else {
                if ($defender_strength > $aggressor_strength) {
                    $war_winner = $defender_id;
                    $war_winner_strength = $defender_strength;
                    $war_loser = $aggressor_id;
                    $war_loser_strength = $aggressor_strength;
                } else {
                    $war_winner = $aggressor_id;
                    $war_winner_strength = $aggressor_strength;
                    $war_loser = $defender_id;
                    $war_loser_strength = $defender_strength;
                }

                $force = abs($defender_strength - $aggressor_strength);

                self::notifyAllPlayers("warOver", clienttranslate('${player_name2} (strength ${aggstrength}) wins the ${card_name} against ${player_name} (strength ${strength})'), array(
                    'i18n' => array('card_name'),
                    'player_name' => $players[$war_loser]['player_name'],
                    'strength' => $war_loser_strength,
                    'player_name2' => $players[$war_winner]['player_name'],
                    'aggstrength' => $war_winner_strength,
                    'card_name' => $card_type['name'],
                    'id' => $aggression_card_id
                ));

                self::incStat(1, 'aggression_won', $war_winner);

                $this->gamestate->changeActivePlayer($aggressor_id);

                // Now, active this card special effect
                $card_name = $card_type['name'];
                $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'war');

                $notifArgs = array(
                    'i18n' => array('card_name'),
                    'card_name' => $card_type['name'],
                    'player_id' => $aggressor_id,
                    'player_name' => self::getActivePlayerName()
                );

                self::DbQuery("UPDATE war SET war_winner='$war_winner', war_force='$force' WHERE war_card_id='$aggression_card_id'");
                $nextState = $this->$method_name($aggressor_id, $aggression_card['type'], $card_type, $defender_id, $war_winner, $war_loser, $force, $notifArgs);

                if ($nextState == null) {
                    self::DbQuery("DELETE FROM war WHERE war_card_id='$aggression_card_id'");
                    $this->gamestate->nextState('endWar');
                } else {
                    $this->gamestate->nextState($nextState);
                }
            }
        }
    }

    function stBuildForFreeEvent()
    {
        // Activate players with a free worker
        $players = self::getObjectListFromDB("SELECT token_player_id FROM token WHERE token_type='yellow' AND token_card_id='0' GROUP BY token_player_id", true);
        $this->gamestate->setPlayersMultiactive($players, 'dualChoice', true);
    }

    function stLossPopulation()
    {
        // Is there worker in pool to lost?
        $player_id = self::getActivePlayerId();

        $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id='0' LIMIT 0,1");

        if ($token_id === null) {
            // No token => must choose a token => remains at this state

            // Exception: is there any token left ?
            $total_loser_pop = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token
                WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

            if ($total_loser_pop == 0)
                $this->gamestate->nextState('endEvent');
        } else {
            // Remove a token from the worker pool
            self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_id='$token_id'");

            self::notifyAllPlayers('moveTokens', clienttranslate('${card_name}: ${player_name} loses a population'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[self::getGameStateValue('currentCardEffectType')]['name'],
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'tokens' => array(
                    array('id' => $token_id, 'card_id' => null, 'player' => $player_id, 'type' => 'yellow')
                )
            ));

            self::adjustPlayerIndicators($player_id);

            $this->gamestate->nextState('endEvent');
        }
    }

    function stLossPopulationNext()
    {
        $player_id = self::activeNextPlayer();

        if ($player_id == self::getGameStateValue('activePlayerBeforeEffect'))
            $this->gamestate->nextState('endEvent');
        else
            $this->gamestate->nextState('next');
    }

    function stLossBlueToken()
    {
        // Active players with positive number of tokens to loose

        $to_active = self::getObjectListFromDB("SELECT player_id FROM player
            WHERE player_blue_toloose>0 ", true);

        $this->gamestate->setPlayersMultiactive($to_active, 'endEvent');
    }

    function stLossYellowToken()
    {
        // Active players with positive number of tokens to loose

        $to_active = self::getObjectListFromDB("SELECT player_id FROM player
            WHERE player_yellow_toloose>0", true);

        $this->gamestate->setPlayersMultiactive($to_active, 'endEvent');
    }

    function stLossBuilding()
    {
        // If the player has NO building, go to end event
        $player_id = self::getActivePlayerId();
        $player_workers = self::getObjectListFromDB("SELECT token_card_id FROM token WHERE token_type='yellow' AND token_card_id <> 0 AND token_player_id='$player_id'", true);
        $hasBuilding = false;
        foreach ($player_workers as $card_id) {
            $card = $this->cards->getCard($card_id);
            $card_type = $this->card_types[$card['type']];
            if ($card_type['category'] != 'Military') {
                $hasBuilding = true;
            }
        }
        if (!$hasBuilding) {
            $this->gamestate->nextState('endEvent');
        }
    }

    function stBidTerritoryNextPlayer()
    {
        $player_id = self::getActivePlayerId();
        $players = self::loadPlayersBasicInfos();
        $player_nbr = count($players);

        // Active next player who has not passed
        $player_to_bid = self::getCollectionFromDB("SELECT player_id, player_bid FROM player", true);

        $nbrWhoPassed = 0;
        $nbrPlayerWhoBid = 0;
        foreach ($player_to_bid as $pid => $bid) {
            if ($bid !== null)
                $nbrPlayerWhoBid++;
            if ($bid == 0 && $bid !== null)
                $nbrWhoPassed++;
        }

        if ($nbrWhoPassed >= ($player_nbr - 1) && $nbrPlayerWhoBid == $player_nbr) {
            // One player remains => end of the bid!

            if ($nbrWhoPassed == $player_nbr) {
                // Everyone passed !
                $this->gamestate->nextState('everyonePass');
            } else {
                $player_id = getKeyWithMaximum($player_to_bid);

                // Now, active the player and make it sacrifice his armies
                $this->gamestate->changeActivePlayer($player_id);

                self::notifyAllPlayers('bidWin', clienttranslate('${player_name} wins the bid'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name']
                ));

                // Automatically start colonization strength with the permanent bonus from cards in tableau
                $bonus_from_tableau = self::getPlayerMaxBid($player_id, true);
                self::setGameStateValue('colonization_strength', $bonus_from_tableau);

                $this->gamestate->nextState('endOfBid');
            }
        } else {
            $next_player = self::createNextPlayerTable(array_keys($players));

            for ($security = 0; $security < 8; $security++)    // infinite loop
            {
                $player_id = $next_player[$player_id];

                if ($player_to_bid[$player_id] === null || $player_to_bid[$player_id] > 0) // Note: if player did not bid already or if it did (and did not pass)
                {
                    $this->gamestate->changeActivePlayer($player_id);

                    $this->gamestate->nextState('nextPlayer');
                    return; // This player must bid
                }
            }
        }


    }

    function stSendColonizationForce()
    {
        $player_id = self::getActivePlayerId();
        if (self::isCardInTableau($player_id, 143)) { // James Cook
            self::addEffect(143, 'endTurn');
            self::addEffect(143, 'endTurn');
        }
        self::undoSavepoint();
    }

    function stWonderForFree()
    {
        // If wonder has been build already, skip the state
        $card = $this->cards->getCard(self::getGameStateValue('cardBuilt'));
        if ($card['type_arg'] != 1)
            $this->gamestate->nextState('wonderForFree');
    }


    function stPickCardsFromRowRefill()
    {
        $current_player_id = self::getGameStateValue('activePlayerBeforeEffect');
        self::refillCardRow($current_player_id);
        $this->gamestate->nextState('refillDone');
    }

    function stPlayerTurnPolitic()
    {
        $player_id = self::getActivePlayerId();
        if (self::isCustomEffectActive(140, $player_id)) {
            // Skip political action
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} skips his political action'), array(
                'i18n' => array('card_name'),
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->card_types[140]['name']
            ));

            self::removeEffect(140);

            $this->gamestate->nextState('donothing');
        }

    }

    function stPlayerTurnPoliticCaesar()
    {
        $player_id = self::getActivePlayerId();
        $canUseCaesar = false;
        if (self::isCardInTableau($player_id, 24)) { // Caesar
            $cards = $this->cards->getCardsOfTypeInLocation(24, null, 'tableau');
            $caesar = reset($cards);
            if ($caesar['type_arg'] == 0) {
                if (self::isEffectActive(24)) {
                    // Caesar effect was used, mark the card as used.
                    $caesar_id = $caesar['id'];
                    self::DbQuery("UPDATE card SET card_type_arg='5' WHERE card_id='$caesar_id'");
                } else {
                    self::addEffect(24, 'endTurn');
                    $canUseCaesar = true;
                }
            }
        }
        if (!$canUseCaesar) {
            $this->gamestate->nextState('donothing');
        }
    }

    function stDestroyBuilding()
    {
        $defender_id = self::getGameStateValue('saved_player');
        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$defender_id' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];
            if ($card_type['category'] == 'Urban') {
                $buildingAge = self::ageCharToNum($card_type['age']);
                if ($buildingAge < 2 || $buildingAge < 3 && self::isEffectActive(162) || self::isEffectActive(244)) {
                    return; // Found at least one building available for destruction.
                }
            }
        }

        // Found no building to destroy -> end event
        $raid_id = self::getGameStateValue("cardBuilt");
        $raid_card = $this->cards->getCard($raid_id);
        $effects = self::getActiveEffects(array(87, 162, 244)); // Raids I, II & III
        $player_id = self::getActivePlayerId();
        foreach ($effects as $effect) {
            self::removeEffect($effect);
        }
        $raided = $raid_card['type_arg'];
        if ($raided > 0) {
            $gain = ceil($raided / 2); // Rounded up
            self::getBlueTokens($player_id, 'ress', $gain);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), array(
                'i18n' => array('card_name'),
                'player_id ' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->card_types[$raid_card['type']]['name'],
                'nbr' => $gain
            ));
        }
        $this->gamestate->nextState('chooseOpponentTableauCard');
    }

    function stFinalScoring()
    {
        $game_version = self::getGameStateValue('game_version');

        $players = self::loadPlayersBasicInfos();

        self::notifyAllPlayers('simpleNote', clienttranslate("Final scoring!"), array());

        $billies = $this->cards->getCardsOfTypeInLocation(238, null, 'tableau');
        if (count($billies) > 0) {
            $bill_gates = reset($billies);
            $player_id = $bill_gates['location_arg'];
            if ($players[$player_id]['player_eliminated'] == 0 && $players[$player_id]['player_zombie'] == 0) {
                self::bill_gates_III_removed($bill_gates['location_arg']);
            }
        }

        if ($game_version == 1) {
            // Simple version:

            // 2 points for each level I technology
            // 4 points for each level II technology
            // Government counts twice
            // 3 for each colony
            // 6 for each completed Age II wonders
            $cards = $this->cards->getCardsInLocation('tableau');
            $player_to_tech1 = array();
            $player_to_tech2 = array();
            $player_to_govt = array();
            $player_to_colonies = array();
            $player_to_wonders2 = array();
            foreach ($cards as $card) {
                $card_type = $this->card_types[$card['type']];
                if ($card_type['techcost'] > 0 && $card_type['age'] != 'A') {
                    if ($card_type['type'] == 'Govt') {
                        $player_to_govt[$card['location_arg']] = self::ageCharToNum($card_type['age']);
                    } else if ($card_type['age'] == 'I') {
                        if (!isset($player_to_tech1[$card['location_arg']]))
                            $player_to_tech1[$card['location_arg']] = 0;
                        $player_to_tech1[$card['location_arg']]++;
                    } else if ($card_type['age'] == 'II') {
                        if (!isset($player_to_tech2[$card['location_arg']]))
                            $player_to_tech2[$card['location_arg']] = 0;
                        $player_to_tech2[$card['location_arg']]++;
                    }
                } else if ($card_type['type'] == 'Territory') {
                    if (!isset($player_to_colonies[$card['location_arg']]))
                        $player_to_colonies[$card['location_arg']] = 0;
                    $player_to_colonies[$card['location_arg']]++;
                } else if ($card_type['type'] == 'Wonder' && $card_type['age'] == 'II' && $card['type_arg'] == 0) {
                    if (!isset($player_to_wonders2[$card['location_arg']]))
                        $player_to_wonders2[$card['location_arg']] = 0;
                    $player_to_wonders2[$card['location_arg']]++;
                }
            }

            foreach ($player_to_tech1 as $player_id => $nb_tech) {
                self::DbQuery("UPDATE player SET player_score=player_score+$nb_tech WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for ${nb} level I technologies'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $nb_tech,
                    'science' => 0,
                    'nb' => $nb_tech
                ));
                self::incStat($nb_tech, 'points_final_scoring', $player_id);
            }

            foreach ($player_to_tech2 as $player_id => $nb_tech) {
                $delta = $nb_tech * 2;
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for ${nb} level II technologies'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $nb_tech
                ));
                self::incStat($delta, 'points_final_scoring', $player_id);
            }

            foreach ($player_to_govt as $player_id => $govtAge) {
                $delta = $govtAge * 2;
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for its government'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0
                ));
                self::incStat($delta, 'points_final_scoring', $player_id);
            }

            // 1 by force point
            // 1 by science per turn
            // 1 per happy face
            $player_to_inc = self::getCollectionFromDB("SELECT player_id, player_strength, player_science, player_happy FROM player");
            foreach ($player_to_inc as $player_id => $inc) {
                $delta = $inc['player_science'];
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for his science (${nb})'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $delta
                ));

                self::incStat($delta, 'points_final_scoring', $player_id);

                $delta = $inc['player_strength'];
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for his strength (${nb})'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $inc['player_strength']
                ));

                self::incStat($delta, 'points_final_scoring', $player_id);

                $delta = min(8, $inc['player_happy']);
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for his happy faces (${nb})'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $inc['player_happy']
                ));

                self::incStat($delta, 'points_final_scoring', $player_id);

            }

            /// 1 point per ress+food produced per turn
            // Get yellow tokens on cards
            $card_to_yellow_token = self::getCollectionFromDb("SELECT token_card_id, COUNT( token_id ) FROM token
                        WHERE token_card_id IS NOT NULL AND token_type='yellow'
                        GROUP BY token_card_id ", true);

            $nb_food_perturn = self::produceFood($cards, $card_to_yellow_token, null);
            $nb_ress_perturn = self::produceRess($cards, $card_to_yellow_token, null);

            foreach ($nb_food_perturn as $player_id => $nb_ress) {
                $delta = $nb_ress + $nb_ress_perturn[$player_id];
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for ${nb} food+resources produced each turn'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $delta
                ));
                self::incStat($delta, 'points_final_scoring', $player_id);
            }

            foreach ($player_to_colonies as $player_id => $number) {
                $delta = $number * 3;
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for ${nb} colonies'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $number
                ));
                self::incStat($delta, 'points_final_scoring', $player_id);
            }

            foreach ($player_to_wonders2 as $player_id => $number) {
                $delta = $number * 6;
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                self::notifyAllPlayers('scoreProgress', clienttranslate('${player_name} scores ${culture} points for ${nb} completed Age II wonders'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'culture' => $delta,
                    'science' => 0,
                    'nb' => $number
                ));
                self::incStat($delta, 'points_final_scoring', $player_id);
            }

        } else {
            // Complete version:
            // use the points shows on the remaining events in piles "current events" and "future events"

            $this->cards->moveAllCardsInLocation('future_events', 'events');
            $cards = $this->cards->getCardsInLocation('events');
            $show_cards = array();

            $player = reset($players);

            foreach ($cards as $card) {
                $card_type = $this->card_types[$card['type']];
                if ($card_type['category'] == 'Event' && $card_type['age'] == 'III') {
                    $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'event');

                    $notifArgs = array(
                        'i18n' => array('card_name'),
                        'card_name' => $card_type['name'],
                        'player_id' => $player['player_id'],
                        'player_name' => $player['player_name']
                    );

                    $this->$method_name($player['player_id'], $card['type'], $card_type, array(), $notifArgs);

                    $show_cards[] = $card;
                }
            }

            self::notifyAllPlayers('finalScoring', '', array('cards' => $show_cards));

        }

        // Final statistics
        $player_to_worker = self::getCollectionFromDB("SELECT token_player_id, COUNT( token_id )
            FROM `token` WHERE token_type='yellow' AND token_card_id IS NOT NULL
            GROUP BY token_player_id", true);
        foreach ($player_to_worker as $player_id => $pop) {
            self::setStat($pop, 'final_pop', $player_id);
        }
        $player_to_strength = self::getCollectionFromDB("SELECT player_id, player_strength FROM player", true);
        foreach ($player_to_strength as $player_id => $str) {
            self::setStat($str, 'final_strength', $player_id);
        }

        $this->gamestate->nextState('');
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($statename == 'playerTurn') {
            $this->gamestate->nextState("endOfTurn");
        } else if ($statename == 'playerTurnPolitic') {
            if (self::getGameStateValue('currentAge') != 4) {
                self::concedeGame();
            } else {
                self::doNothing();
            }
        } else if ($statename == 'bidTerritory') {
            $this->bidTerritory(0);   // pass
        } else if ($statename == 'pactMayAccept') {
            $this->acceptPact(0);   // refuse pact
        } else {

            if ($state['type'] == "activeplayer") {
                switch ($statename) {
                    default:
                        $this->gamestate->nextState("zombiePass");
                        break;
                }

                return;
            }

            if ($state['type'] == "multipleactiveplayer") {

                $this->gamestate->setPlayerNonMultiactive($active_player, 'zombiePass');

                return;
            }

            throw new feException("Zombie mode not supported at this game state: " . $statename);
        }
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////
////////////
////////////
////////////
////////////
////////////
//////////// TTA cards power management
////////////
////////////
////////////
////////////
////////////
////////////
////////////
////////////
////////////
////////////

    function getCardEffectMethod($card_name, $card_age, $effect)
    {
        $card_name_normalized = strtolower(str_replace('’', '_', str_replace(' ', '_', $card_name)));
        $card_name_normalized = str_replace("'", '_', str_replace('.', '_', $card_name_normalized));
        $card_name_normalized = str_replace("-", '_', str_replace('.', '_', $card_name_normalized));
        $card_name_normalized = str_replace("&", '_', str_replace('.', '_', $card_name_normalized));
        return $card_name_normalized . '_' . $card_age . '_' . $effect;
    }

    /// Effects management
    function addEffect($card_type_id, $effect_duration, $arg = 0)
    {
        $sql = "INSERT INTO currenteffects (effect_card_type,effect_duration,effect_arg)
                VALUES ('$card_type_id','$effect_duration','$arg')";
        self::DbQuery($sql);
    }

    function removeEffect($card_type_id, $nbr = null)
    {
        $sql = "DELETE FROM currenteffects WHERE effect_card_type='$card_type_id' ";

        if ($nbr !== null)
            $sql .= " LIMIT $nbr";
        self::DbQuery($sql);
    }

    function countEffect($card_type_id)
    {
        return count(self::getObjectListFromDB("SELECT effect_card_type FROM currenteffects WHERE effect_card_type='$card_type_id'", true));
    }

    function isEffectActive($card_type_id)
    {
        return $this->countEffect($card_type_id) > 0;
    }

    function isCustomEffectActive($card_type_id, $arg)
    {
        return (count(self::getObjectListFromDB("SELECT effect_card_type FROM currenteffects WHERE effect_card_type='$card_type_id' AND effect_arg='$arg'", true)) > 0);
    }

    function getActiveEffects($card_type_ids)
    {
        return self::getObjectListFromDB("SELECT effect_card_type FROM currenteffects WHERE effect_card_type IN ('" . implode("','", $card_type_ids) . "')", true);
    }

    function resetEndActionEffects()
    {
        self::DbQuery("DELETE FROM currenteffects WHERE effect_duration='endAction'");
    }

    function resetEndOfTurnEffect()
    {
        self::DbQuery("DELETE FROM currenteffects WHERE effect_duration!='custom'");
    }

    // Some card has been played with an effect when it arrives in play
    function actionCard($card_id, $card_type, $card_type_id)
    {
        $player_id = self::getActivePlayerId();
        $card_name = $card_type['name'];
        $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'played');

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $card_type['name'],
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName()
        );

        $nextState = $this->$method_name($player_id, $card_type_id, $card_type, array(), $notifArgs);

        return $nextState;
    }

    // Some card has been removed with an effect when it happens
    function cardRemoved($owner_id, $card_type, $card_type_id)
    {
        $card_name = $card_type['name'];
        $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'removed');

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $card_type['name'],
            'player_id' => $owner_id,
            'player_name' => self::getActivePlayerName()
        );

        $nextState = $this->$method_name($owner_id);

        return $nextState;
    }

    // Some event has been trigged, that can trigger some effects on cards on active player's tableau
    function ttaEvent($eventName, $player_id, $eventArgs = null)
    {
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);

        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];

            if (isset($card_type['events'])) {
                if (in_array($eventName, $card_type['events'])) {
                    // Check this is not a Wonder in building
                    if ($card_type['category'] != 'Wonder' || $card['type_arg'] == 0) {
                        // This card registered to this event
                        $card_name = $card_type['name'];
                        $method_name = self::getCardEffectMethod($card_name, $card_type['age'], $eventName);

                        $notifArgs = array(
                            'i18n' => array('card_name'),
                            'card_name' => $card_type['name'],
                            'player_id' => $player_id,
                            'player_name' => self::getActivePlayerName()
                        );

                        $this->$method_name($player_id, $card['type'], $card_type, $eventArgs, $notifArgs);
                    }
                }
            }
        }


        // If needed: buildings (tokens):
        /*        $buildings = self::getObjectListFromDB( "SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL" );
*/
    }


    function acceptance_of_supremacy_II_roundBeginning($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_discontent = self::getUniqueValueFromDB("SELECT player_discontent FROM player WHERE player_id='$player_id'");
        $player_avail = self::getUniqueValueFromDB("SELECT COUNT( token_id ) FROM token WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id='0'");
        if ($player_discontent <= $player_avail) {
            if ($card_type_id == 109) {
                self::getBlueTokens($player_id, 'ress', 1);
                $notifArgs['nbr'] = 1;
                self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);
            } else if ($card_type_id == 1109) {
                if (self::spendBlueTokens($player_id, 'ress', 1, true) == 1) {
                    $notifArgs['nbr'] = 1;
                    self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} loses ${nbr} resource(s)'), $notifArgs);
                }
            }
        }
    }

    function albert_einstein_III_getscience($player_id, $buildings, $cards_in_tableau)
    {
        // Must find the max age of Lab or Library
        $max_age = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Lab' || $card_type['type'] == 'Library') {
                $max_age = max($max_age, self::ageCharToNum($card_type['age']));
            }
        }

        return $max_age;
    }

    function albert_einstein_III_playTechnologyCard($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +3 culture
        $delta = 3;
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);

    }


    function alexander_the_great_A_getstrength($player_id, $buildings, $cards_in_tableau)
    {
        $bonus = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['category'] == 'Military')
                $bonus++;
        }

        return $bonus;
    }

    function annex_II_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        // Check if defender have at least one territory

        $territories = self::getCardsOfTypeFromTableau($defender_id, 'Territory');

        if (count($territories) == 0) {
            $notifArgs['player_name'] = $players[$defender_id]['player_name'];
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} has no colony'), $notifArgs);
        } else {
            self::setGameStateValue('saved_player', $defender_id);

            return 'annex';
        }
    }

    function annex_II_chooseOpponentTableauCard($card_id)
    {
        // Check this card is on target opponent's

        $player_id = self::getActivePlayerId();
        $defender_id = self::getGameStateValue('saved_player');

        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $defender_id)
            throw new feException(self::_("You must choose a card in the tableau of the player you attacked"), true);

        if ($this->card_types[$card['type']]['type'] != 'Territory')
            throw new feException(self::_("You must choose a territory"), true);

        $card['location_arg'] = $player_id;

        // Move card to player tableau
        $this->cards->moveCard($card['id'], 'tableau', $player_id);

        self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $defender_id, 'card_id' => $card['id']));
        self::notifyAllPlayers('playCard', clienttranslate('${card_name}: ${player_name} steals ${territory}'), array(
            'i18n' => array('card_name', 'territory'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->card_types[110]['name'],
            'territory' => $this->card_types[$card['type']]['name'],
            'card' => $card
        ));

        self::removeCardApplyConsequences($defender_id, $card['type']);
        self::adjustPlayerIndicators($defender_id);

        // Yellow/blue token to gain
        self::applyTokenDeltaToPlayer($player_id, $this->card_types[$card['type']], array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->card_types[$card['type']]['name']
        ));


        $this->gamestate->nextState('chooseOpponentTableauCard');
    }

    function aristotle_A_pickTechnologyCard($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 science point
        $delta = 1;
        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

        $notifArgs['points'] = $currentSciencePoints + $delta;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);
    }


    function armed_intervention_III_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        // + up to 7 points
        $delta = min(7, self::getUniqueValueFromDb("SELECT player_score FROM player WHERE player_id='$defender_id'"));
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);


        // - up to 7 points
        $sql = "UPDATE player SET
                    player_score=player_score-$delta
                    WHERE player_id='$defender_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = -$delta;
        $notifArgs['delta'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $defender_id;
        $notifArgs['player_name'] = $players[$defender_id]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${delta} points of culture'), $notifArgs);

        self::incStat(-$delta, 'points_cards_effects', $defender_id);

    }

    function infiltrate_II_prerequisite($player_id, $defender_id)
    {
        $leader = self::getLeader($defender_id);
        $wonder = self::getWonderUnderConstruction($defender_id);
        if ($leader == null && $wonder == null) {
            throw new feException(self::_("This player has no leader nor unfinished wonder in play"), true);
        }
    }

    function infiltrate_II_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        $leader = self::getLeader($defender_id);
        $wonder = self::getWonderUnderConstruction($defender_id);
        if ($leader == null) {
            return self::infiltrate_II_removeWonder($player_id, $defender_id, $wonder, $notifArgs);
        } else if ($wonder == null) {
            return self::infiltrate_II_removeLeader($player_id, $defender_id, $leader, $notifArgs);
        } else {
            return "infiltrate";
        }
    }

    function infiltrate_II_chooseOpponentTableauCard($card_id)
    {
        $player_id = self::getActivePlayerId();
        $defender_id = self::getGameStateValue('saved_player');
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $defender_id)
            throw new feException(self::_("You must choose a card in the tableau of the player you attacked"), true);

        $card_type = $this->card_types[$card['type']];

        if ($card_type['category'] != 'Leader' && ($card_type['category'] != 'Wonder' || $card['type_arg'] != 1))
            throw new feException(self::_("You must remove the leader or the unfinished wonder"), true);

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $this->card_types[112]['name'],
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName()
        );

        if ($card_type['category'] == 'Leader') {
            self::infiltrate_II_removeLeader($player_id, $defender_id, $card, $notifArgs);
        } else {
            self::infiltrate_II_removeWonder($player_id, $defender_id, $card, $notifArgs);
        }
        $this->gamestate->nextState('endAggression');
    }

    function infiltrate_II_removeLeader($player_id, $defender_id, $leader, $notifArgs)
    {
        $this->cards->moveCard($leader['id'], 'removed');
        self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $defender_id, 'card_id' => $leader['id']));

        $leader_type = $this->card_types[$leader['type']];
        $leaderLevel = self::ageCharToNum($leader_type['age']);

        $notifArgs['i18n'][] = 'leader';
        $notifArgs['leader'] = $leader_type['name'];
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} kills ${leader}'), $notifArgs);

        $delta = 3 * $leaderLevel;
        self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        $this->removeCardApplyConsequences($defender_id, $leader['type']);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function infiltrate_II_removeWonder($player_id, $defender_id, $wonder, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();
        $wonder_type = $this->card_types[$wonder['type']];
        $age = self::ageCharToNum($wonder_type['age']);

        // Destroy Wonder in construction
        $this->cards->moveCard($wonder['id'], 'removed');
        self::notifyAllPlayers('discardFromTableau', clienttranslate('${card_name}: ${player_name} discards ${card_name2}'), array(
            'i18n' => array('card_name', 'card_name2'),
            'player_id' => $defender_id,
            'card_id' => $wonder['id'],
            'player_name' => $players[$defender_id]['player_name'],
            'card_name' => $this->card_types[169]['name'],
            'card_name2' => $this->card_types[$wonder['type']]['name']
        ));

        self::removeBlueTokensOnCard($defender_id, $wonder['id']);

        // Score 3 times
        $delta = $age * 3;
        self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $player_id;
        $notifArgs['player_name'] = $players[$player_id]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function barbarians_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // If culture leader is 1 of the 2 weakest, it loses 1 population

        $culture_leader = self::getLeaderIn('score');
        $weakest = self::getLeaderIn('strength', true, 2);

        if (in_array($culture_leader, $weakest)) {
            // Culture leader loss 1 population
            $this->gamestate->changeActivePlayer($culture_leader);
            return "lossPopulation";
        }

    }


    function border_conflict_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Weakest civilization loses 1 urban building, farm, or mine; strongest produces 3 resources

        $players = self::loadPlayersBasicInfos();

        $strongest = self::getLeaderIn('strength');
        self::getBlueTokens($strongest, 'ress', 3);

        $notifArgs['player_id'] = $strongest;
        $notifArgs['player_name'] = $players[$strongest]['player_name'];
        $notifArgs['nbr'] = 3;

        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);

        $weakest = self::getLeaderIn('strength', true);
        $this->gamestate->changeActivePlayer($weakest);
        return 'lossBuilding';
    }

    function stockpile_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 resource and +1 food
        self::getBlueTokens($player_id, 'ress', 1);
        self::getBlueTokens($player_id, 'food', 1);
        $notifArgs['nbr'] = 1;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
    }

    function reserves_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 resources or +2 food
        self::addEffect($card_type_id, 'endAction');
        return "chooseReservesGain";
    }

    function reserves_I_dualChoice($choice)
    {
        $this->food_or_resources_dualChoice(self::getActivePlayerId(), 2, $choice);
        $this->gamestate->nextState('endAction');
    }

    function reserves_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +3 resources or +3 food
        self::addEffect($card_type_id, 'endAction');
        return "chooseReservesGain";
    }

    function reserves_II_dualChoice($choice)
    {
        $this->food_or_resources_dualChoice(self::getActivePlayerId(), 3, $choice);
        $this->gamestate->nextState('endAction');
    }

    function reserves_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +4 resources or +4 food
        self::addEffect($card_type_id, 'endAction');
        return "chooseReservesGain";
    }

    function reserves_III_dualChoice($choice)
    {
        $this->food_or_resources_dualChoice(self::getActivePlayerId(), 4, $choice);
        $this->gamestate->nextState('endAction');
    }

    function breakthrough_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // play a technology then get 2 science
        self::addEffect($card_type_id, 'endAction');
        return "mustPlayTechnology";
    }

    function breakthrough_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // play a technology then get 3 science
        self::addEffect($card_type_id, 'endAction');
        return "mustPlayTechnology";
    }

    function alexander_the_great_A_activepolitic($player_id, $card_type_id, $card_type, $card, $notifArgs)
    {
        $this->cards->moveCard($card['id'], 'removed');
        self::notifyAllPlayers('discardFromTableau', clienttranslate('${player_name} removes ${card_name} from play to get 1 new yellow token'), array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card' => $card,
            'card_id' => $card['id'],
            'card_name' => $card_type['name']
        ));

        self::DbQuery("INSERT INTO token (token_type, token_card_id, token_player_id) VALUES ('yellow',NULL, '$player_id')");
        $moveTokens[] = array('id' => self::DbGetLastId(), 'card_id' => null, 'player' => $player_id, 'type' => 'yellow');
        self::notifyAllPlayers('moveTokens', '', array('tokens' => $moveTokens));

        return "endAction";
    }

    function christopher_columbus_I_activepolitic($player_id, $card_type_id, $card_type, $card, $notifArgs)
    {
        self::setGameStateValue('activePlayerBeforeEffect', $player_id);
        self::addEffect($card_type_id, 'endAction');
        return "christopherColumbus";
    }

    function christopher_columbus_I_playMilitaryCard($player_id, $card_id, $card_type, $card, $notifArgs)
    {
        if ($card_type['type'] != 'Territory')
            throw new feException("You must choose a territory", true);

        $columbusList = $this->cards->getCardsOfTypeInLocation(46, null, 'tableau', $player_id);
        $columbus = reset($columbusList);
        // Remove Christopher Columbus from the game
        $this->cards->moveCard($columbus['id'], 'removed');
        self::notifyAllPlayers('discardFromTableau', clienttranslate('${player_name} discards ${leader_name}'), array(
            'i18n' => array('leader_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'leader_name' => 'Christopher Columbus',
            'card_id' => $columbus['id']
        ));

        // Place the territory into play
        $this->cards->moveCard($card_id, 'tableau', $player_id);

        self::notifyAllPlayers('playCard', clienttranslate('Christopher Columbus: ${player_name} plays ${card_name} for free'), array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card' => $card,
            'card_name' => $card_type['name']
        ));

        self::adjustPlayerIndicators($player_id);

        // Yellow/blue token to gain
        self::applyTokenDeltaToPlayer($player_id, $card_type, $notifArgs);

        // Apply new territory effects
        $card_name = $card_type['name'];
        $method_name = self::getCardEffectMethod($card_name, $card_type['age'], 'colonized');

        self::incStat(1, 'territory', $player_id);

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $card_type['name'],
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName()
        );

        $this->$method_name($player_id, $card['type'], $card_type, array(), $notifArgs);

        $this->gamestate->nextState('endAction');
    }

    function civil_unrest_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // -4 points per discontent
        $players = self::loadPlayersBasicInfos();
        $player_to_discontent = self::getCollectionFromDB("SELECT player_id, player_discontent FROM player WHERE player_discontent>0 AND player_eliminated = 0 AND player_zombie = 0", true);
        $most_discontent = 0;

        foreach ($player_to_discontent as $player_id => $workers) {
            $delta = 4 * $workers;
            $sql = "UPDATE player SET
                        player_score=GREATEST( 0, player_score-$delta )
                        WHERE player_id='$player_id'";
            self::DbQuery($sql);

            self::incStat(-$delta, 'points_cards_effects', $player_id);

            $notifArgs['culture'] = -$delta;
            $notifArgs['delta'] = $delta;
            $notifArgs['science'] = 0;
            $notifArgs['player_id'] = $player_id;
            $notifArgs['player_name'] = $players[$player_id]['player_name'];
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${delta} points of culture'), $notifArgs);

            $most_discontent = max($most_discontent, $workers);
        }

        // -1 blue token for most discontent
        foreach ($player_to_discontent as $player_id => $workers) {
            if ($workers == $most_discontent) {
                self::loseToken($player_id, 'blue', 1);
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} loses 1 blue token'), $notifArgs);
            }
        }
    }

    function cold_war_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // 2 Strongest scores 6 sciences

        $players = self::loadPlayersBasicInfos();

        $strongest = self::getLeaderIn('strength', false, 2);

        foreach ($strongest as $player_id) {
            $delta = 6;
            $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
            self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

            $notifArgs['points'] = $currentSciencePoints + $delta;
            $notifArgs['science'] = $delta;
            $notifArgs['player_name'] = $players[$player_id]['player_name'];
            $notifArgs['player_id'] = $player_id;
            self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);
        }
    }

    function crime_wave_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // 2 weakest civilizations lose 3 culture and 1 blue token

        $players = self::loadPlayersBasicInfos();

        $weakest = self::getLeaderIn('strength', true, 2);

        foreach ($weakest as $player_id) {
            // -3 points
            $current_score = self::getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
            $delta = max(-3, -$current_score);
            $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
            self::DbQuery($sql);

            $notifArgs['culture'] = $delta;
            $notifArgs['cult'] = abs($delta);
            $notifArgs['science'] = 0;
            $notifArgs['player_id'] = $player_id;
            $notifArgs['player_name'] = $players[$player_id]['player_name'];
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${cult} points of culture'), $notifArgs);

            self::incStat($delta, 'points_cards_effects', $player_id);


            self::loseToken($player_id, 'blue', 1);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} loses 1 blue token'), $notifArgs);
        }

        return "checkLooseTokens";
    }

    function crusades_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Strongest civ scores 4 culture; weakest loses 4 culture

        $players = self::loadPlayersBasicInfos();

        $strongest = self::getLeaderIn('strength');
        $weakest = self::getLeaderIn('strength', true);

        // +4 points
        $delta = 4;
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$strongest'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $strongest;
        $notifArgs['player_name'] = $players[$strongest]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $strongest);


        // -4 points
        $current_score = self::getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$weakest'");
        $delta = max(-4, -$current_score);
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$weakest'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['cult'] = abs($delta);
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $weakest;
        $notifArgs['player_name'] = $players[$weakest]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${cult} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $weakest);

    }

    function cultural_influence_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Each civilization scores culture = to culture rating
        // (see maintenance phase)

        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $progression = self::getObjectFromDB("SELECT player_culture FROM player WHERE player_id='$player_id'");

                $sql = "UPDATE player SET
                        player_score=player_score+'" . $progression['player_culture'] . "'
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                $notifArgs['culture'] = $progression['player_culture'];
                $notifArgs['science'] = 0;

                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($progression['player_culture'], 'points_cards_effects', $player_id);
            }
        }

    }


    function developed_territory_I_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +3 science

        $delta = 3;
        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

        $notifArgs['points'] = $currentSciencePoints + $delta;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);
    }

    function developed_territory_II_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +5 science

        $delta = 5;
        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

        $notifArgs['points'] = $currentSciencePoints + $delta;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);
    }

    function development_of_agriculture_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 food for everyone
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                self::getBlueTokens($player_id, 'food', 2);
            }
        }

        $notifArgs['nbr'] = 2;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Everyone gets ${nbr} food(s)'), $notifArgs);
    }

    function development_of_crafts_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 resources for everyone
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                // +1 resource
                self::getBlueTokens($player_id, 'ress', 2);
            }
        }

        $notifArgs['nbr'] = 2;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Everyone gets ${nbr} resource(s)'), $notifArgs);

    }

    function development_of_markets_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 food/resource
        // Active all players
        $this->gamestate->setAllPlayersMultiactive();
        return "freeFoodResource";
    }

    function development_of_markets_A_dualChoice($choice)
    {
        $player_id = self::getCurrentPlayerId();
        $this->food_or_resources_dualChoice($player_id, 2, $choice);
        $this->gamestate->setPlayerNonMultiactive($player_id, 'dualChoice');
    }

    function food_or_resources_dualChoice($player_id, $quantity, $choice)
    {
        if ($choice == 1) {
            // Food
            self::getBlueTokens($player_id, 'food', $quantity);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[self::getGameStateValue('currentCardEffectType')]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $quantity
            ));
        } else {
            // Resource
            self::getBlueTokens($player_id, 'ress', $quantity);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[self::getGameStateValue('currentCardEffectType')]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $quantity
            ));
        }
    }

    function development_of_politics_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Each player draw 3 military cards
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                self::pickMilitaryCardsForPlayer($player_id, 3);
            }
        }
    }

    function development_of_religion_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        return "freeTemple";
    }

    function development_of_religion_A_dualChoice($choice)
    {
        $player_id = self::getCurrentPlayerId();

        if ($choice == 1) {
            // Free temple Age A

            // Get free temple Age A (=31) technology card
            $cards = $this->cards->getCardsOfTypeInLocation(31, null, 'tableau', $player_id);

            if (count($cards) == 0)
                throw new feException("Can't find temple technology card");

            $card = reset($cards);

            self::doBuild($card['id'], $player_id, true);
        } else {
            // Nothing to do
        }

        $this->gamestate->setPlayerNonMultiactive($player_id, 'dualChoice');
    }

    function development_of_settlement_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 pop for everyone


        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                self::doIncreasePop($player_id, true);
            }
        }

        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Everyone gets +1 population'), $notifArgs);
    }


    function development_of_science_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 science for everyone
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $delta = 2;
                $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
                self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

                self::notifyAllPlayers('updateSciencePoints', '', array(
                    'player_id' => $player_id,
                    'science' => $delta,
                    'points' => $currentSciencePoints + $delta
                ));
            }
        }

        $notifArgs['nbr'] = 2;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Everyone gets ${nbr} science points'), $notifArgs);
    }

    function development_of_trade_routes_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 science / food / resources
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $delta = 1;
                $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
                self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

                self::notifyAllPlayers('updateSciencePoints', '', array(
                    'player_id' => $player_id,
                    'science' => $delta,
                    'points' => $currentSciencePoints + $delta
                ));


                self::getBlueTokens($player_id, 'food', 1);
                self::getBlueTokens($player_id, 'ress', 1);
            }
        }
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Everyone gets 1 science point, 1 food and 1 resource'), $notifArgs);
    }

    function development_of_warfare_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        return "freeWarrior";
    }

    function development_of_warfare_A_dualChoice($choice)
    {
        $player_id = self::getCurrentPlayerId();

        if ($choice == 1) {
            // Free warrior Age A

            // Get free warrior Age A (=34) technology card
            $cards = $this->cards->getCardsOfTypeInLocation(34, null, 'tableau', $player_id);

            if (count($cards) == 0)
                throw new feException("Can't find warrior technology card");

            $card = reset($cards);

            self::doBuild($card['id'], $player_id, true);
        } else {
            // Nothing to do
        }

        $this->gamestate->setPlayerNonMultiactive($player_id, 'dualChoice');
    }

    function development_of_civilization_A_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $this->gamestate->setAllPlayersMultiactive();
        return "developmentOfCivilization";
    }

    function economic_progress_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $this_player_id => $this_player) {
            if ($this_player['player_eliminated'] == 0 && $this_player['player_zombie'] == 0) {
                self::applyCorruption($this_player_id);
            }
        }

        // Good harvest produces food
        self::good_harvest_I_event($player_id, $card_type_id, $card, $args, $notifArgs);

        foreach ($players as $this_player_id => $this_player) {
            if ($this_player['player_eliminated'] == 0 && $this_player['player_zombie'] == 0) {
                self::applyConsumption($this_player_id);
            }
        }

        // New deposits produces resources
        self::new_deposits_I_event($player_id, $card_type_id, $card, $args, $notifArgs);
    }

    function efficient_upgrade_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Build a wonder step for less resources
        self::addEffect($card_type_id, 'endAction');
        return "mustUpgradeBuilding";
    }

    function efficient_upgrade_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Build a wonder step for less resources
        self::addEffect($card_type_id, 'endAction');
        return "mustUpgradeBuilding";
    }

    function emigration_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        // Each player loose half of discontent worker (rounded up)
        $player_to_discontent = self::getCollectionFromDB("SELECT player_id, player_discontent FROM player WHERE player_discontent>0 AND player_eliminated = 0 AND player_zombie = 0", true);
        $player_to_sacrifice = array();
        foreach ($player_to_discontent as $player_id => $workers) {
            $nbr_to_loose = ceil($workers / 2);

            for ($i = 0; $i < $nbr_to_loose; $i++) {
                // Is there worker in pool to lost?

                $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
                    WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id='0' LIMIT 0,1");

                if ($token_id === null) {
                    $player_to_sacrifice[] = $player_id;
                    self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} loses a population'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[130]['name'],
                        'player_id' => $player_id,
                        'player_name' => $players[$player_id]['player_name']
                    ));
                } else {
                    // Remove a token from the worker pool
                    self::DbQuery("UPDATE token SET token_card_id=NULL WHERE token_id='$token_id'");

                    self::notifyAllPlayers('moveTokens', clienttranslate('${card_name}: ${player_name} loses a population'), array(
                        'i18n' => array('card_name'),
                        'card_name' => $this->card_types[130]['name'],
                        'player_id' => $player_id,
                        'player_name' => $players[$player_id]['player_name'],
                        'tokens' => array(
                            array('id' => $token_id, 'card_id' => null, 'player' => $player_id, 'type' => 'yellow')
                        )
                    ));
                }
            }
        }

        if (!empty($player_to_sacrifice)) {
            $player_to_sacrifice_joined = join(',', $player_to_sacrifice);
            self::DbQuery("UPDATE player SET player_yellow_toloose = player_yellow_toloose + 1 WHERE player_id IN ($player_to_sacrifice_joined)");
        }
    }

    function endowment_for_the_arts_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Count number of civilization stronger

        $player_ordered = self::getLeaderIn('score', false, 999);

        $nbr_stronger = 0;
        foreach ($player_ordered as $other_player) {
            if ($other_player == $player_id)
                break;

            $nbr_stronger++;
        }

        $remainingPlayers = self::getUniqueValueFromDb("SELECT count(*) FROM player WHERE player_eliminated = 0 AND player_zombie = 0");

        $players_nbr_to_score = array(4 => 2, 3 => 3, 2 => 6);

        $delta = $players_nbr_to_score[$remainingPlayers] * $nbr_stronger;

        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function engineering_genius_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Build a wonder step for less resources
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildWonder";
    }

    function engineering_genius_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Build a wonder step for less resources
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildWonder";
    }

    function engineering_genius_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Build a wonder step for less resources
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildWonder";
    }

    function engineering_genius_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Build a wonder step for less resources
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildWonder";
    }

    function enslave_I_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        // +2 resources and +2 food
        self::getBlueTokens($player_id, 'ress', 2);
        self::getBlueTokens($player_id, 'food', 2);
        $notifArgs['nbr'] = 2;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);

        // Loss one pop
        $this->gamestate->changeActivePlayer($defender_id);
        return "lossPopulation";
    }

    function fast_food_chains_III_played($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        $delta = 0;

        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            // +1 for each worker on military/urban
            // +2 fore each worker on farm/mine
            if ($card_type['category'] == 'Military')
                $delta++;
            else if ($card_type['category'] == 'Urban')
                $delta++;
            else if ($card_type['category'] == 'Production')
                $delta += 2;
        }

        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }


    function vast_territory_I_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +3 foods
        self::getBlueTokens($player_id, 'food', 3);
        $notifArgs['nbr'] = 3;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
    }

    function vast_territory_II_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +4 foods
        self::getBlueTokens($player_id, 'food', 4);
        $notifArgs['nbr'] = 4;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
    }

    function first_space_flight_III_played($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        $delta = 0;
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];

            if ($card_type['category'] == 'Special'
                || $card_type['category'] == 'Govt'
                || $card_type['category'] == 'Production'
                || $card_type['category'] == 'Urban'
                || $card_type['category'] == 'Military'
            ) {
                $delta += self::ageCharToNum($card_type['age']);
            }
        }

        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function foray_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $strongest = self::getLeaderIn('strength', false, 2);

        // +3 food/resource
        // Active all strongest players
        $this->gamestate->setPlayersMultiactive($strongest, 'dummy');
        return "freeFoodResourceCustom";

    }

    function foray_I_dualChoice($choice_id)
    {
        $player_id = self::getCurrentPlayerId();

        $ress = $choice_id;
        $food = 3 - $choice_id;

        if ($ress < 0 || $food < 0)
            throw new feException("Wrong choice");

        if ($food > 0) {
            self::getBlueTokens($player_id, 'food', $food);

            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[57]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $food
            ));
        }
        if ($ress > 0) {
            self::getBlueTokens($player_id, 'ress', $ress);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[57]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $ress
            ));
        }

        $this->gamestate->setPlayerNonMultiactive($player_id, 'dualChoice');
    }

    function frederick_barbarossa_I_active($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} uses ${card_name}...'), $notifArgs);
        return "mustBuildMilitary";
    }

    function frugality_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Increase pop
        self::doIncreasePop(null, false, 0, true);

        // +1 food
        self::getBlueTokens($player_id, 'food', 1);
        $notifArgs['nbr'] = 1;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
    }

    function frugality_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Increase pop
        self::doIncreasePop(null, false, 0, true);

        // +1 food
        self::getBlueTokens($player_id, 'food', 2);
        $notifArgs['nbr'] = 2;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
    }

    function frugality_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Increase pop
        self::doIncreasePop(null, false, 0, true);

        // +1 food
        self::getBlueTokens($player_id, 'food', 3);
        $notifArgs['nbr'] = 3;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
    }

    function sid_meier_III_getculture($player_id, $buildings, $cards_in_tableau)
    {
        // Each lab +1 culture / level
        $bonus = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['category'] == 'Urban' && $card_type['type'] == 'Lab') {
                $bonus += self::ageCharToNum($card_type['age']);
            }
        }

        return $bonus;
    }


    function sid_meier_III_getscience($player_id, $buildings, $cards_in_tableau)
    {
        // Each lab -1 science
        $bonus = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['category'] == 'Urban' && $card_type['type'] == 'Lab') {
                $bonus--;
            }
        }

        return $bonus;
    }

    function genghis_khan_I_getculture($player_id)
    {
        $strongest = self::getLeaderIn('strength', false, 2);
        if (in_array($player_id, $strongest)) {
            self::DbQuery("UPDATE player SET player_score=player_score+3 WHERE player_id='$player_id'");
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), array(
                'i18n' => array('card_name'),
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->card_types[60]['name'],
                'culture' => 3,
                'science' => 0
            ));
            self::incStat(3, 'points_cards_effects', $player_id);
        }
    }

    function good_harvest_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                // Get all cards in tableau
                $cards = $this->cards->getCardsInLocation('tableau', $player_id);

                // Get yellow tokens on cards
                $card_to_yellow_token = self::getCollectionFromDb("SELECT token_card_id, COUNT( token_id ) FROM token
                        WHERE token_card_id IS NOT NULL AND token_type='yellow'
                        GROUP BY token_card_id ", true);

                // Get available blue tokens
                $available_blue = self::getObjectListFromDb("SELECT token_id FROM token
                    WHERE token_player_id='$player_id'
                    AND token_card_id IS NULL
                    AND token_type='blue' ", true);

                self::produceFood($cards, $card_to_yellow_token, $available_blue);
            }
        }
    }

    function great_wall_I_getstrength($player_id, $buildings, $cards_in_tableau)
    {
        $bonus = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['category'] == 'Military') {
                if ($card_type['type'] == 'Infantry' || $card_type['type'] == 'Artillery')
                    $bonus++;
            }
        }

        return $bonus;
    }

    function napoleon_bonaparte_II_getstrength($player_id, $workers, $cards_in_tableau)
    {
        $unitType = array('Infantry' => 0, 'Cavalry' => 0, 'Artillery' => 0, 'Air Force' => 0);
        foreach ($workers as $worker) {
            $card_type = $this->card_types[$worker['card_type']];
            if ($card_type['category'] == 'Military') {
                $unitType[$card_type['type']] = 1;
            }
        }
        return array_count_values($unitType)[1] * 2;
    }

    function historic_territory_I_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +6 points
        $delta = 6;
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }


    function historic_territory_II_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +11 points
        $delta = 11;

        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function hollywood_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // 2x culture produced by Theaters and Libraries
        $delta = 0;

        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];
            if ($card_type['type'] == 'Theater' || $card_type['type'] == 'Library') {
                $delta += 2 * $card_type['culture'];
            }
        }

        // Effects that modify culture production of theaters and libraries (Shakespeare, Bach, Chaplin) apply, too.
        $leader = self::getLeader($player_id);
        if (in_array($leader['type'], array(106, 145, 199))) {
            $card_type = $this->card_types[$leader['type']];
            $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'getculture');
            $delta += 2 * $this->$method_name($player_id, $buildings, $this->cards->getCardsInLocation('tableau', $player_id));
        }

        self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function hammurabi_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function hammurabi_A_roundBeginning($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function hammurabi_A_active($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        if (!self::isEffectActive(20)) {
            throw new feException(self::_("You already used this effect during this turn"), true);
        }
        self::addEffect(1020, 'endTurn');
        self::removeEffect(20);
        self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} uses ${card_name}...'), $notifArgs);
    }

    function homer_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function homer_A_roundBeginning($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function homer_A_removed($player_id)
    {
        $cards = $this->cards->getCardsInLocation('tableau', $player_id);
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == 'Wonder' && $card['type_arg'] == 0) { // A built Wonder
                return "homer";
            }
        }
        return null; // No eligible wonder
    }

    function homer_A_chooseCard($card_id)
    {
        $player_id = self::getActivePlayerId();
        // Basic checks
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        if ($card_type['category'] != 'Wonder' || $card['type_arg'] != 0) {
            throw new feException(self::_("You must choose a finished Wonder"), true);
        }

        $homers = $this->cards->getCardsOfTypeInLocation(22, null, 'removed');
        $homer = reset($homers);
        $this->cards->moveCard($homer['id'], 'under_card', $card_id);


        self::notifyAllPlayers('slideCardUnder', clienttranslate('${player_name} slides ${card_name} under ${other_card_name}'), array(
            'i18n' => array('card_name', 'other_card_name'),
            'player_id' => $player_id,
            'card' => $this->cards->getCard($homer['id']),
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->card_types[22]['name'],
            'other_card_name' => $card_type['name']
        ));

        $this->gamestate->nextState('endAction');
    }

    function bill_gates_III_removed($player_id)
    {
        $delta = 0;
        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];
            if ($card_type['type'] == 'Lab') {
                $delta += self::ageCharToNum($card_type['age']);
            }
        }
        $billy = $this->card_types[238];

        $players = self::loadPlayersBasicInfos();
        self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");

        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), array(
            'i18n' => array('card_name'),
            'card_name' => $billy['name'],
            'player_id' => $player_id,
            'player_name' => $players[$player_id]['player_name'],
            'culture' => $delta,
            'science' => 0
        ));

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function iconoclasm_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Discard all leaders not from curent age
        $players = self::loadPlayersBasicInfos();

        $current_age = self::ageNumToChar(self::getGameStateValue('currentAge'));

        // Get all leaders
        $cards = $this->cards->getCardsInLocation('tableau');
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == 'Leader') {
                if ($current_age != $this->card_types[$card['type']]['age']) {
                    // Discard this leader
                    $this->cards->moveCard($card['id'], 'removed');
                    $player_id = $card['location_arg'];
                    self::notifyAllPlayers('discardFromTableau', clienttranslate('${card_name}: ${player_name} discards ${leader_name}'), array(
                        'i18n' => array('card_name', 'leader_name'),
                        'player_id' => $player_id, 'card_id' => $card['id'],
                        'player_name' => $players[$player_id]['player_name'],
                        'card_name' => $this->card_types[136]['name'],
                        'leader_name' => $this->card_types[$card['type']]['name']
                    ));

                    self::removeCardApplyConsequences($player_id, $card['type']);
                }
            }
        }
    }

    function urban_growth_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildCivil";
    }

    function urban_growth_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildCivil";
    }

    function urban_growth_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildCivil";
    }

    function urban_growth_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildCivil";
    }

    function immigration_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_happy = self::getCollectionFromDB("SELECT player_id, player_happy FROM player WHERE player_eliminated = 0 AND player_zombie = 0", true);

        $players = self::loadPlayersBasicInfos();
        $player_ids = getKeysWithMaximum($player_to_happy);

        foreach ($player_ids as $player_id) {
            $notifArgs['player_name'] = $players[$player_id]['player_name'];

            self::doIncreasePop($player_id, true);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets +1 population'), $notifArgs);
        }
    }

    function impact_of_agriculture_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $production = self::getFoodProduction($player_id);
                $consumption = self::getFoodConsumption($player_id);
                $delta = $production;
                if ($production > $consumption) {
                    $delta += 4;
                }
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_architecture_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_delta[$player_id] = 0;
        }

        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type, token_player_id player_id
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['category'] == 'Urban') {
                $player_to_delta[$building['player_id']] += self::ageCharToNum($card_type['age']);
            }
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_balance_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::getCollectionFromDB("SELECT player_id, player_name, player_culture culture, player_science science FROM player WHERE player_eliminated = 0 AND player_zombie = 0");
        foreach ($players as $player_id => $player) {
            $delta = 2 * min($player['culture'], $player['science'], self::getResourceProduction($player_id), self::getFoodProduction($player_id));
            self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
            $notifArgs['culture'] = $delta;
            $notifArgs['science'] = 0;
            $notifArgs['player_id'] = $player_id;
            $notifArgs['player_name'] = $player['player_name'];
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);
            self::incStat($delta, 'points_final_scoring', $player_id);
        }
    }

    function impact_of_colonies_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_delta[$player_id] = 0;
        }

        $cards = $this->cards->getCardsInLocation('tableau');


        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['type'] == 'Territory') {
                $player_to_delta[$card['location_arg']] += 3;
            }
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);
                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }

    }

    function impact_of_competition_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_delta[$player_id] = 0;
        }

        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type, token_player_id player_id
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Arena' || $card_type['category'] == 'Military') {
                $player_to_delta[$building['player_id']] += self::ageCharToNum($card_type['age']);
            }
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_government_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $limits = self::getGovernmentLimits($player_id);

            $player_to_delta[$player_id] = 2 * $limits['civilActions'] + $limits['militaryActions'];
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }

    }

    function impact_of_happiness_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        $player_to_delta = self::getCollectionFromDB("SELECT player_id, player_happy, player_discontent FROM player");

        foreach ($player_to_delta as $player_id => $player) {
            if ($players[$player_id]['player_eliminated'] == 0 && $players[$player_id]['player_zombie'] == 0) {
                $delta = min(16, 2 * $player['player_happy']);
                $delta -= 2 * $player['player_discontent'];    // 2 / happy face - 2 / discontent

                $sql = "UPDATE player SET
                        player_score=GREATEST( 0, player_score+$delta )
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_industry_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        foreach (self::loadPlayersBasicInfos() as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $delta = self::getResourceProduction($player_id, true);
                self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");
                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);
                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_population_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        // Count content workers = total workers - discontent
        $player_to_worker = self::getCollectionFromDB("SELECT token_player_id, COUNT( token_id )
            FROM `token` WHERE token_type='yellow' AND token_card_id IS NOT NULL
            GROUP BY token_player_id", true);

        $player_to_discontent = self::getCollectionFromDB("SELECT player_id, player_discontent FROM player", true);

        foreach ($player_to_worker as $player_id => $workers) {
            $player = $players[$player_id];
            $happy_workers = $workers - $player_to_discontent[$player_id];

            if ($happy_workers > 10 && $player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $delta = 2 * ($happy_workers - 10);

                $sql = "UPDATE player SET
                            player_score=player_score+$delta
                            WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_progress_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_delta[$player_id] = 0;
        }

        $cards = $this->cards->getCardsInLocation('tableau');

        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == 'Special' || $this->card_types[$card['type']]['category'] == 'Govt') {
                $player_to_delta[$card['location_arg']] += self::ageCharToNum($this->card_types[$card['type']]['age']);
            }
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $delta *= 2;

                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_science_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $science_leaders = self::getLeaderIn('science', false, 999);

        $remainingPlayers = self::getUniqueValueFromDb("SELECT count(*) FROM player WHERE player_eliminated = 0 AND player_zombie = 0");

        $players = self::loadPlayersBasicInfos();

        if ($remainingPlayers == 2)
            $scores = array(10, 0);
        else if ($remainingPlayers == 3)
            $scores = array(14, 7, 0);
        else if ($remainingPlayers == 4)
            $scores = array(15, 10, 5, 0);

        foreach ($science_leaders as $player_id) {
            $delta = array_shift($scores);
            if ($delta > 0) {
                $sql = "UPDATE player SET
                            player_score=player_score+$delta
                            WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_strength_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $science_leaders = self::getLeaderIn('strength', false, 999);

        $remainingPlayers = self::getUniqueValueFromDb("SELECT count(*) FROM player WHERE player_eliminated = 0 AND player_zombie = 0");

        $players = self::loadPlayersBasicInfos();

        if ($remainingPlayers == 2)
            $scores = array(10, 0);
        else if ($remainingPlayers == 3)
            $scores = array(14, 7, 0);
        else if ($remainingPlayers == 4)
            $scores = array(15, 10, 5, 0);

        foreach ($science_leaders as $player_id) {
            $delta = array_shift($scores);
            if ($delta > 0) {
                $sql = "UPDATE player SET
                            player_score=player_score+$delta
                            WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_technology_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_delta[$player_id] = 0;
        }

        $cards = $this->cards->getCardsInLocation('tableau');

        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['age'] == 'III' && $this->card_types[$card['type']]['techcost'] > 0) {
                $player_to_delta[$card['location_arg']] += 4;
            }
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_variety_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $player_to_types = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_types[$player_id] = array();
        }

        $workers = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type, token_player_id player_id
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_card_id IS NOT NULL");

        foreach ($workers as $worker) {
            $card_type = $this->card_types[$worker['card_type']];
            if ($card_type['category'] == 'Urban' || $card_type['category'] == 'Military')
                $player_to_types[$worker['player_id']][$card_type['type']] = true;
        }

        $cards = $this->cards->getCardsInLocation('tableau');

        foreach ($cards as $card) {
            $card_type = $this->card_types[$card['type']];
            if ($card_type['category'] == 'Special') {
                $player_to_types[$card['location_arg']][$card_type['type']] = true;
            }
        }

        foreach ($player_to_types as $player_id => $types) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $delta = count($types) * 2;
                self::DbQuery("UPDATE player SET player_score=player_score+ $delta WHERE player_id='$player_id'");

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function impact_of_wonders_III_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $wonder_to_score = array('A' => 5, 'I' => 4, 'II' => 3, 'III' => 2);

        $player_to_delta = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_to_delta[$player_id] = 0;
        }

        $cards = $this->cards->getCardsInLocation('tableau');

        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == 'Wonder' && $card['type_arg'] != 1) {
                $player_to_delta[$card['location_arg']] += $wonder_to_score[$this->card_types[$card['type']]['age']];
            }
        }

        foreach ($player_to_delta as $player_id => $delta) {
            $player = $players[$player_id];
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $sql = "UPDATE player SET
                        player_score=player_score+$delta
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['culture'] = $delta;
                $notifArgs['science'] = 0;
                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $player['player_name'];
                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($delta, 'points_final_scoring', $player_id);
            }
        }
    }

    function independence_declaration_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $weakest = self::getLeaderIn('strength', true);
        $colonies = array();

        // Have at least one colony?
        $cards = $this->cards->getCardsInLocation('tableau', $weakest);
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['type'] == 'Territory') {
                $colonies[] = $card;
            }
        }

        if (count($colonies) > 1) {
            $this->gamestate->changeActivePlayer($weakest);
            return 'lossColony';
        } else if (count($colonies) == 1) {
            self::independence_declaration_II_removeColony($weakest, $colonies[0]);
        } else {
            $players = self::loadPlayersBasicInfos();
            $notifArgs['player_id'] = $weakest;
            $notifArgs['player_name'] = $players[$weakest]['player_name'];

            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} has no colony'), $notifArgs);
        }
    }

    function independence_declaration_II_chooseCard($card_id)
    {
        $player_id = self::getCurrentPlayerId();

        // Basic checks
        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }

        $card_type = $this->card_types[$card['type']];

        if ($card_type['type'] != 'Territory')
            throw new feException(self::_("You must choose a territory"), true);

        self::independence_declaration_II_removeColony($player_id, $card);

        $this->gamestate->nextState('endEvent');
    }

    function independence_declaration_II_removeColony($player_id, $card)
    {
        $players = self::loadPlayersBasicInfos();
        $this->cards->moveCard($card['id'], 'removed');

        self::notifyAllPlayers('discardFromTableau', clienttranslate('${card_name}: ${player_name} discards ${territory}'), array(
            'i18n' => array('card_name', 'territory'),
            'player_id' => $player_id,
            'card_id' => $card['id'],
            'player_name' => $players[$player_id]['player_name'],
            'card_name' => $this->card_types[138]['name'],
            'territory' => $this->card_types[$card['type']]['name'],
        ));

        self::removeCardApplyConsequences($player_id, $card['type']);
        self::adjustPlayerIndicators($player_id);
    }

    function inhabited_territory_I_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 pop
        self::doIncreasePop($player_id, true);
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets +1 population'), $notifArgs);
    }

    function inhabited_territory_II_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 pop
        self::doIncreasePop($player_id, true);
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets +1 population'), $notifArgs);
        self::doIncreasePop($player_id, true);
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets +1 population'), $notifArgs);
    }


    function international_agreement_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $strongest = self::getLeaderIn('strength');
        $this->gamestate->changeActivePlayer($strongest);

        self::setGameStateValue('saved_player', 5);   // 5 points
        return "pickCardsFromRow";
    }

    function stPickCardsFromRowContinue()
    {
        self::addEffect(140, 'custom', self::getActivePlayerId());
    }

    function international_tourism_III_getculture($player_id, $buildings, $cards_in_tableau)
    {
        $bonus = 0;

        // Get partner

        $pacts = self::getPactsOf($player_id);
        foreach ($pacts as $pact) {
            if ($pact['card_type'] == 225 || $pact['card_type'] == 1225) {
                $partner = $pact['partner'];

                $cards = $this->cards->getCardsInLocation('tableau', $partner);

                foreach ($cards as $card) {
                    if ($this->card_types[$card['type']]['category'] == 'Wonder' && $card['type_arg'] != 1) {
                        $bonus++;
                    }
                }
            }
        }

        return $bonus;
    }

    function internet_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Score culture equal to urban production of culture, science and strength
        $delta = 0;

        $buildings = self::getObjectListFromDB("SELECT token_id, token_card_id, card_type
            FROM token
            INNER JOIN card ON card_id=token_card_id
            WHERE token_type='yellow' AND token_player_id='$player_id' AND token_card_id IS NOT NULL");

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];
            if ($card_type['category'] == 'Urban') {
                $delta += $card_type['culture'] + $card_type['science'] + $card_type['strength'];
            }
        }

        // Effects that modify culture production (Shakespeare, Bach, Chaplin), science production (Newton, Einstein),
        // or both (Meier) of theaters and libraries apply, too.
        $leader = self::getLeader($player_id);
        if (in_array($leader['type'], array(106, 145, 199, 207))) {
            $card_type = $this->card_types[$leader['type']];
            $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'getculture');
            $delta += $this->$method_name($player_id, $buildings, $this->cards->getCardsInLocation('tableau', $player_id));
        }
        if (in_array($leader['type'], array(142, 190, 207))) {
            $card_type = $this->card_types[$leader['type']];
            $method_name = self::getCardEffectMethod($card_type['name'], $card_type['age'], 'getscience');
            $delta += $this->$method_name($player_id, $buildings, $this->cards->getCardsInLocation('tableau', $player_id));
        }

        self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$player_id'");

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $player_id);
    }

    function isaac_newton_II_getscience($player_id, $buildings, $cards_in_tableau)
    {
        // Must find the max age of Lab or Library
        $max_age = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Lab' || $card_type['type'] == 'Library') {
                $max_age = max($max_age, self::ageCharToNum($card_type['age']));
            }
        }

        return $max_age;
    }

    function isaac_newton_II_playTechnologyCard($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Get back a civil action
        self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used - 1, player_civil_actions = player_civil_actions + 1 WHERE player_id = '$player_id'");
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets back a Civil Action'), $notifArgs);
    }

    function james_cook_II_getculture($player_id, $buildings, $cards_in_tableau)
    {
        // 2 for first colony, 1 for the others
        $bonus = 0;
        foreach ($cards_in_tableau as $card) {
            if ($this->card_types[$card['type']]['type'] == 'Territory')
                $bonus++;
        }
        if ($bonus > 0)
            $bonus++;

        return $bonus;
    }

    function j_s__bach_II_getculture($player_id, $buildings, $cards_in_tableau)
    {
        // Each theater +1 culture
        $bonus = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Theater')
                $bonus++;
        }

        return $bonus;
    }

    function j_s__bach_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function j_s__bach_II_roundBeginning($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function joan_of_arc_I_getstrength($player_id, $buildings, $cards_in_tableau)
    {
        // Each temple +1 strength / happy face
        $bonus = 0;

        $bBasilica = self::hasBasilica($player_id);

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Temple') {
                $bonus += $card_type['happy'];

                if ($bBasilica)
                    $bonus++;
            }
        }
        foreach ($cards_in_tableau as $card) {
            $card_type = $this->card_types[$card['type']];
            if ($card_type['type'] == 'Govt' && $card_type['happy'] > 0) {
                $bonus += $card_type['happy'];
                if ($bBasilica)
                    $bonus++;
            }
        }

        return $bonus;
    }

    function joan_of_arc_I_activepolitic($player_id, $card_type_id, $card_type, $card, $notifArgs)
    {
        $event = self::getNextEvent();
        self::notifyPlayer($player_id, 'revealEvent', '', array(
            'i18n' => array('event_name'),
            'card' => $event,
            'event_name' => $this->card_types[$event['type']]['name']
        ));
    }

    function leonardo_da_vinci_I_getscience($player_id, $buildings, $cards_in_tableau)
    {
        // Must find the max age of Lab or Library
        $max_age = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Lab' || $card_type['type'] == 'Library') {
                $max_age = max($max_age, self::ageCharToNum($card_type['age']));
            }
        }

        return $max_age;
    }


    function leonardo_da_vinci_I_playTechnologyCard($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 resource
        self::getBlueTokens($player_id, 'ress', 1);
        $notifArgs['nbr'] = 1;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);
    }


    function national_pride_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();
        $culture_leader = self::getLeaderIn('score');

        $progression = 5;
        $sql = "UPDATE player SET
                    player_score=player_score+'" . $progression . "'
                    WHERE player_id='$culture_leader'";
        self::DbQuery($sql);

        $notifArgs['player_id'] = $culture_leader;
        $notifArgs['player_name'] = $players[$culture_leader]['player_name'];
        $notifArgs['culture'] = $progression;
        $notifArgs['science'] = 0;

        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($progression, 'points_cards_effects', $culture_leader);
    }

    function michelangelo_I_getculture($player_id, $buildings, $cards_in_tableau)
    {
        // Each temple&theatre +1 culture / happy face
        $bonus = 0;

        $bBasilica = self::hasBasilica($player_id);


        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Temple' || $card_type['type'] == 'Theater') {
                $bonus += $card_type['happy'];

                if ($bBasilica)
                    $bonus++;
            }
        }

        // Each wonder: +1 / happy face
        $cardsUnder = $this->cards->getCardsInLocation('under_card');
        $homer = reset($cardsUnder);
        foreach ($cards_in_tableau as $card) {
            $card_type = $this->card_types[$card['type']];
            if ($card_type['category'] == 'Wonder' && $card['type_arg'] != 1) {
                $happy = $card_type['happy'] > 0 ? $card_type['happy'] : 0;
                if ($homer && $homer['location_arg'] == $card['id']) {
                    $happy++;
                }
                if ($happy > 0 && $bBasilica && $card['type'] != 95) { // Another Wonder than Basilica
                    $happy++;
                }
                $bonus += $happy;
            }
        }

        return $bonus;
    }


    function military_build_up_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Count number of civilization stronger

        $player_ordered = self::getLeaderIn('strength', false, 999);

        $nbr_stronger = 0;
        foreach ($player_ordered as $other_player) {
            if ($other_player == $player_id)
                break;

            $nbr_stronger++;
        }

        $players = self::loadPlayersBasicInfos();

        $players_nbr_to_ress = array(4 => 3, 3 => 5, 2 => 8);

        $ress = $players_nbr_to_ress[count($players)] * $nbr_stronger;

        for ($i = 0; $i < $ress; $i++) {
            self::addEffect($card_type_id, 'endTurn');
        }

        $notifArgs['nbr'] = $ress;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resources for Military units this turn'), $notifArgs);
    }

    function new_deposits_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                // Get all cards in tableau
                $cards = $this->cards->getCardsInLocation('tableau', $player_id);

                // Get yellow tokens on cards
                $card_to_yellow_token = self::getCollectionFromDb("SELECT token_card_id, COUNT( token_id ) FROM token
                        WHERE token_card_id IS NOT NULL AND token_type='yellow'
                        GROUP BY token_card_id ", true);

                // Get available blue tokens
                $available_blue = self::getObjectListFromDb("SELECT token_id FROM token
                    WHERE token_player_id='$player_id'
                    AND token_card_id IS NULL
                    AND token_type='blue' ", true);

                self::produceRess($cards, $card_to_yellow_token, $available_blue);
            }
        }
    }

    function ocean_liner_service_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function ocean_liner_service_II_roundBeginning($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function ocean_liner_service_II_active($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        if (self::isEffectActive(155)) // Ocean liner service
        {
            self::removeEffect(155);

            self::notifyAllPlayers('simpleNote', clienttranslate('Using ${card_name}...'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[155]['name']
            ));

            $done = self::doIncreasePop($player_id, true, 0, true);
            if (!$done) {
                throw new feException(self::_("No more yellow tokens available"), true);
            }
            self::adjustPlayerIndicators($player_id);
            $this->gamestate->nextState('endAction');
        } else
            throw new feException(self::_("You already used this effect during this turn"), true);

    }

    function politics_of_strength_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $currentAge = self::getGameStateValue('currentAge');
        $strongest = self::getLeaderIn('strength');
        $weakest = self::getLeaderIn('strength', true);
        if ($currentAge < 4) {
            self::pickMilitaryCardsForPlayer($strongest, 5);
            $cards = $this->cards->getCardsInLocation('hand', $weakest);
            $nbInHand = 0;

            foreach ($cards as $card) {
                if (!self::isCardCategoryCivil($this->card_types[$card['type']]['category']))
                    $nbInHand++;
            }

            if ($nbInHand <= 3) {
                $players = self::loadPlayersBasicInfos();
                foreach ($cards as $card) {
                    $card_type = $this->card_types[$card['type']];
                    if (!self::isCardCategoryCivil($card_type['category'])) {
                        $this->cards->moveCard($card['id'], 'discardMilitary' . $card_type['age']);
                        self::notifyPlayer($weakest, 'discardFromHand', '', array('card_id' => $card['id']));
                    }
                }
                self::updateCardsInHand();
                self::notifyAllPlayers('simpleNote', clienttranslate('${player_name} discards ${nbr} military cards'), array(
                    'player_id' => $weakest,
                    'player_name' => $players[$weakest]['player_name'],
                    'nbr' => $nbInHand
                ));
            } else {
                $this->gamestate->changeActivePlayer($weakest);
                return "discardMilitary";
            }
        } else {
            $players = self::loadPlayersBasicInfos();
            self::DbQuery("UPDATE player SET player_score=player_score+5 WHERE player_id='$strongest'");
            $notifArgs['culture'] = 5;
            $notifArgs['science'] = 0;
            $notifArgs['player_id'] = $strongest;
            $notifArgs['player_name'] = $players[$strongest]['player_name'];
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);
            self::incStat(5, 'points_cards_effects', $strongest);
            // -3 points
            $current_score = self::getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$weakest'");
            $delta = max(-3, -$current_score);
            self::DbQuery("UPDATE player SET player_score=player_score+$delta WHERE player_id='$weakest'");
            $notifArgs['culture'] = $delta;
            $notifArgs['cult'] = abs($delta);
            $notifArgs['science'] = 0;
            $notifArgs['player_id'] = $weakest;
            $notifArgs['player_name'] = $players[$weakest]['player_name'];
            self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${cult} points of culture'), $notifArgs);
            self::incStat(-3, 'points_cards_effects', $weakest);
        }
    }

    function prosperity_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::getCollectionFromDB("SELECT player_id, player_happy happy, player_name name, player_eliminated, player_zombie FROM player");
        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $gain = min(8, $player['happy']);
                self::getBlueTokens($player_id, 'food', $gain);
                $notifArgs['nbr'] = $gain;
                $notifArgs['player_name'] = $player['name'];
                self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), $notifArgs);
            }
        }
    }

    function winston_churchill_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function winston_churchill_III_roundBeginning($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endTurn');
    }

    function winston_churchill_III_active($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        if (!self::isEffectActive($card_type_id)) {
            throw new feException(self::_("You already used this effect during this turn"), true);
        }
        if (!array_key_exists('churchill', $this->gamestate->state()['transitions'])) {
            throw new feException(self::_("You must finish you current action first"), true);
        }
        self::setGameStateValue('currentCardEffectType', $card_type_id);
        $this->gamestate->nextState('churchill');
    }

    function winston_churchill_III_dualChoice($choice)
    {
        $player_id = self::getActivePlayerId();
        if ($choice == 1) {
            self::winston_churchill_III_getCulture($player_id);
        } else {
            self::addEffect(1860, 'endTurn'); // -3 science
            self::addEffect(1861, 'endTurn');
            self::addEffect(1861, 'endTurn');
            self::addEffect(1861, 'endTurn');
        }
        self::removeEffect(186);
        $this->gamestate->nextState('endAction');
    }

    function winston_churchill_III_getCulture($player_id)
    {
        self::DbQuery("UPDATE player SET player_score=player_score+3 WHERE player_id='$player_id'");
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->card_types[186]['name'],
            'culture' => 3,
            'science' => 0
        ));
        self::incStat(3, 'points_cards_effects', $player_id);

    }

    function patriotism_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $notifArgs['nbr'] = 1;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gains a military action and ${nbr} extra resource for military units'), $notifArgs);


        self::addEffect(82, 'endTurn');     // +1 military action (82 = Patriotism age I)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
    }

    function patriotism_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $notifArgs['nbr'] = 2;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gains a military action and ${nbr} extra resource for military units'), $notifArgs);

        self::addEffect(82, 'endTurn');     // +1 military action (82 = Patriotism age I)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
    }

    function patriotism_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $notifArgs['nbr'] = 3;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gains a military action and ${nbr} extra resource for military units'), $notifArgs);

        self::addEffect(82, 'endTurn');     // +1 military action (82 = Patriotism age I)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
    }

    function patriotism_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $notifArgs['nbr'] = 4;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gains a military action and ${nbr} extra resource for military units'), $notifArgs);

        self::addEffect(82, 'endTurn');     // +1 military action (82 = Patriotism age I)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
        self::addEffect(28, 'endTurn');     // -1 on military unit (28 = Patriotism first age)
    }

    function pestilence_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        return "lossPopulationMultiple";
    }

    function plunder_I_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        // Save defender
        self::setGameStateValue('saved_player', $defender_id);
        return "stealFoodResourceChoice";
    }

    function plunder_I_dualChoice($choice_id)
    {
        self::plunder_stealResource($choice_id, 3, self::getGameStateValue('saved_player'), self::getActivePlayerId());
    }


    function plunder_II_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        // Save defender
        self::setGameStateValue('saved_player', $defender_id);
        return "stealFoodResourceChoice5";
    }

    function plunder_II_dualChoice($choice_id)
    {
        self::plunder_stealResource($choice_id, 5, self::getGameStateValue('saved_player'), self::getActivePlayerId());
    }

    function plunder_III_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        // Save defender
        self::setGameStateValue('saved_player', $defender_id);
        return "stealFoodResourceChoice7";
    }

    function plunder_III_dualChoice($choice_id)
    {
        self::plunder_stealResource($choice_id, 7, self::getGameStateValue('saved_player'), self::getActivePlayerId());
    }

    function plunder_stealResource($choice_id, $total, $from, $to, $bWarOverResource = false)
    {
        $player_id = $to;
        $defender_id = $from;

        $ress = $choice_id;
        $food = $total - $choice_id;

        if ($ress < 0 || $food < 0)
            throw new feException("Wrong choice");

        $card_type_id = $bWarOverResource ? 180 : 85;

        if ($food > 0) {
            $avail = self::getBlueTokenTotalValue($defender_id, 'food');

            $plunded = min($avail, $food);

            self::spendBlueTokens($defender_id, 'food', $plunded);
            self::getBlueTokens($player_id, 'food', $plunded);

            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} food(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[$card_type_id]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $plunded
            ));
        }
        if ($ress > 0) {
            $avail = self::getBlueTokenTotalValue($defender_id, 'ress');

            $plunded = min($avail, $ress);

            self::spendBlueTokens($defender_id, 'ress', $plunded);
            self::getBlueTokens($player_id, 'ress', $plunded);

            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[$card_type_id]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $plunded
            ));
        }

        $this->gamestate->nextState('dualChoice');
    }

    function popularization_of_science_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Each civilization scores culture = science rating

        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                $progression = self::getObjectFromDB("SELECT player_science FROM player WHERE player_id='$player_id'");
                $sql = "UPDATE player SET
                        player_score=player_score+'" . $progression['player_science'] . "'
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                $notifArgs['player_id'] = $player_id;
                $notifArgs['player_name'] = $players[$player_id]['player_name'];
                $notifArgs['culture'] = $progression['player_science'];
                $notifArgs['science'] = 0;

                self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

                self::incStat($progression['player_science'], 'points_cards_effects', $player_id);
            }
        }
    }

    function raid_I_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        self::setGameStateValue('saved_player', $defender_id);
        self::addEffect($card_type_id, 'custom');
        return "chooseBuildingToDestroy";
    }

    function raid_I_chooseOpponentTableauCard($card_id)
    {
        self::raids_chooseOpponentTableauCard($card_id);
    }


    function raid_II_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        self::setGameStateValue('saved_player', $defender_id);
        self::addEffect($card_type_id, 'custom');
        self::addEffect(87, 'custom'); // Raid I for Age I or older
        return "chooseBuildingToDestroy";
    }

    function raid_II_chooseOpponentTableauCard($card_id)
    {
        self::raids_chooseOpponentTableauCard($card_id);
    }


    function raid_III_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        self::setGameStateValue('saved_player', $defender_id);
        self::addEffect($card_type_id, 'custom');
        self::addEffect(162, 'custom'); // Raid II for Age II or older
        return "chooseBuildingToDestroy";
    }

    function raid_III_chooseOpponentTableauCard($card_id)
    {
        self::raids_chooseOpponentTableauCard($card_id);
    }

    function raids_chooseOpponentTableauCard($card_id)
    {
        // Check this card is on target opponent's
        $player_id = self::getActivePlayerId();
        $defender_id = self::getGameStateValue('saved_player');
        $card = $this->cards->getCard($card_id);
        $card_type = $this->card_types[$card['type']];
        $raid_id = self::getGameStateValue("cardBuilt");
        $raid_card = $this->cards->getCard($raid_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $defender_id) {
            throw new feException(self::_("You must choose a card in the tableau of the player you attacked"), true);
        } else if ($card_type['category'] != 'Urban') {
            throw new feException(self::_("You must choose an Urban building"), true);
        }

        $effects = self::getActiveEffects(array(87, 162, 244)); // Raids I, II & III
        switch ($card_type['age']) {
            case 'III':
                if (in_array(244, $effects)) {
                    self::removeEffect(244);
                } else if (in_array(162, $effects)) {
                    throw new feException(self::_("You must choose an urban building of Age II or older"), true);
                } else {
                    throw new feException(self::_("You must choose an urban building of Age I or older"), true);
                }
                break;
            case 'II':
                if (in_array(162, $effects)) {
                    self::removeEffect(162);
                } else if (in_array(244, $effects)) {
                    self::removeEffect(244);
                } else {
                    throw new feException(self::_("You must choose an urban building of Age I or older"), true);
                }
                break;
            default:
                if (in_array(87, $effects)) {
                    self::removeEffect(87);
                } else if (in_array(162, $effects)) {
                    self::removeEffect(162);
                } else {
                    self::removeEffect(244);
                }
                break;
        }

        // Destroy a building on this card
        $bDestroy = self::doDestroy($card_id, $defender_id, true, false, false, true);

        if (sizeof($effects) == 1) { // Was the last building to destroy
            $raided = $raid_card['type_arg'];
            if ($bDestroy) {
                $raided += $card_type['resscost'];
            }
            if ($raided > 0) {
                $gain = ceil($raided / 2); // Rounded up
                self::getBlueTokens($player_id, 'ress', $gain);
                self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), array(
                    'i18n' => array('card_name'),
                    'player_id ' => $player_id,
                    'player_name' => self::getActivePlayerName(),
                    'card_name' => $this->card_types[$raid_card['type']]['name'],
                    'nbr' => $gain
                ));
            }
            $this->gamestate->nextState('chooseOpponentTableauCard');
        } else if ($bDestroy) {
            // Store the total destruction cost on Raid card
            $raided = $card_type['resscost'];
            self::DbQuery("UPDATE card SET card_type_arg = $raided WHERE card_id='$raid_id'");
            self::stDestroyBuilding(); // Need to check if the second building can be destroyed
        }
    }

    function raiders_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $weakest = self::getLeaderIn('strength', true, 2);

        $removed = array();

        foreach ($weakest as $player_id) {
            // If less than 2 food+resource, loose everything
            if ((self::getBlueTokenTotalValue($player_id, 'food') + self::getBlueTokenTotalValue($player_id, 'ress')) <= 2) {
                // consume all resources
                self::spendBlueTokens($player_id, 'food', self::getBlueTokenTotalValue($player_id, 'food'));
                self::spendBlueTokens($player_id, 'ress', self::getBlueTokenTotalValue($player_id, 'ress'));
                $removed[] = $player_id;
            }
        }

        foreach ($removed as $player_id) {
            if (($key = array_search($player_id, $weakest)) !== false) {
                unset($weakest[$key]);
            }
        }

        if (count($weakest) == 0)
            return; // Done !
        else {
            $this->gamestate->setPlayersMultiactive($weakest, 'dummy');
            return "payResourceFood";
        }
    }

    function raiders_I_dualChoice($choice_id)
    {
        $player_id = self::getCurrentPlayerId();

        $ress = $choice_id;
        $food = 2 - $choice_id;

        if ($ress < 0 || $food < 0)
            throw new feException("Wrong choice");

        if ($food > 0) {
            self::spendBlueTokens($player_id, 'food', $food);

            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} looses ${nbr} food(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[88]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),
                'nbr' => $food
            ));
        }
        if ($ress > 0) {
            self::spendBlueTokens($player_id, 'ress', $ress);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} looses ${nbr} resource(s)'), array(
                'i18n' => array('card_name'),
                'card_name' => $this->card_types[88]['name'],
                'player_id' => $player_id,
                'player_name' => self::getCurrentPlayerName(),

                'nbr' => $ress
            ));
        }

        $this->gamestate->setPlayerNonMultiactive($player_id, 'dualChoice');
    }

    function rats_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Loose all stored food

        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                self::spendBlueTokens($player_id, 'food', self::getBlueTokenTotalValue($player_id, 'food'));
            }
        }

        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Each civilization loses all stored food'), $notifArgs);
    }

    function ravages_of_time_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Active all players with a wonder from age A or I
        $players_to_active = array();
        $players_to_eligible_wonders = array();

        $cards = $this->cards->getCardsInLocation('tableau');
        foreach ($cards as $card) {
            if ($this->card_types[$card['type']]['category'] == 'Wonder'  // A wonder
                && $card['type_arg'] == 0     // Built
                && ($this->card_types[$card['type']]['age'] == 'A' || $this->card_types[$card['type']]['age'] == 'I')
            ) {
                $players_to_eligible_wonders[$card['location_arg']][] = $card;
            }
        }

        foreach ($players_to_eligible_wonders as $player_id => $wonders) {
            if (sizeof($wonders) > 1) {
                $players_to_active[] = $player_id;
            } else if (sizeof($wonders) == 1) {
                self::ravages_of_time_II_ravage($player_id, $wonders[0]);
            }
        }

        if (count($players_to_active) > 0) {
            $this->gamestate->setPlayersMultiactive($players_to_active, 'dummy');
            return 'ravagesOfTime';
        }
    }

    function ravages_of_time_II_chooseCard($card_id)
    {
        $player_id = self::getCurrentPlayerId();

        // Basic checks
        $card = $this->cards->getCard($card_id);
        if ($card['location'] != 'tableau' || $card['location_arg'] != $player_id) {
            throw new feException("This card is not in your tableau");
        }
        $card_type = $this->card_types[$card['type']];

        if ($card_type['category'] != 'Wonder' || $card['type_arg'] != 0)
            throw new feException(self::_("You must choose a finished Wonder"), true);

        if ($card_type['age'] != 'A' && $card_type['age'] != 'I')
            throw new feException(self::_("You must choose Wonder from age A or I"), true);

        self::ravages_of_time_II_ravage($player_id, $card);

        $this->gamestate->setPlayerNonMultiactive($player_id, 'endEvent');
    }

    function ravages_of_time_II_ravage($player_id, $card)
    {
        $players = self::loadPlayersBasicInfos();
        $card_type = $this->card_types[$card['type']];
        $card_id = $card['id'];

        self::DbQuery("UPDATE card SET card_type_arg='2' WHERE card_id='$card_id'");
        $card['type_arg'] = 1;

        self::notifyAllPlayers('ravage', clienttranslate('${card_name}: ${player_name} ravages ${wonder_name}'), array(
            'i18n' => array('card_name', 'wonder_name'),
            'card' => $card,
            'player_id' => $player_id,
            'player_name' => $players[$player_id]['player_name'],
            'card_name' => $this->card_types[163]['name'],
            'wonder_name' => $card_type['name']
        ));

        self::removeCardApplyConsequences($player_id, $card['type']);
        self::adjustPlayerIndicators($player_id);
    }

    function rebellion_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::getCollectionFromDb("SELECT player_id, player_civil_actions civil_actions, player_discontent discontent, player_name name FROM player WHERE player_eliminated = 0 AND player_zombie = 0");
        self::dbQuery("UPDATE player SET player_civil_actions_used = player_civil_actions_used + LEAST(player_civil_actions, player_discontent * 2),  player_civil_actions = IF(player_civil_actions > player_discontent * 2,player_civil_actions - player_discontent * 2,0)");
        foreach ($players as $player_id => $player) {
            $notifArgs['player_name'] = $player['name'];
            $notifArgs['nbr'] = min($player['discontent'] * 2, $player['civil_actions']);
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} loses ${nbr} Civil Actions'), $notifArgs);
        }
    }

    function refugees_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        // Weakest civilization loses 3 culture and 1 pop; strongest gains 3 culture and 1 pop
        $strongest = self::getLeaderIn('strength', false);

        // +3 points
        $delta = 3;
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$strongest'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $strongest;
        $notifArgs['player_name'] = $players[$strongest]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $strongest);

        // +1 pop
        self::doIncreasePop($strongest, true);

        // Now, weakest
        $weakest = self::getLeaderIn('strength', true);

        // -3 points
        $delta = 3;
        $sql = "UPDATE player SET
                    player_score=GREATEST(0,player_score-$delta)
                    WHERE player_id='$weakest'";
        self::DbQuery($sql);

        $notifArgs['culture'] = -$delta;
        $notifArgs['delta'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $weakest;
        $notifArgs['player_name'] = $players[$weakest]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${delta} points of culture'), $notifArgs);

        self::incStat(-$delta, 'points_cards_effects', $weakest);

        $this->gamestate->changeActivePlayer($weakest);
        return 'lossPopulation';
    }

    function reign_of_terror_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Weakest loose 1 pop
        $weakest = self::getLeaderIn('strength', true);

        $this->gamestate->changeActivePlayer($weakest);
        return 'lossPopulation';
    }


    function cultural_heritage_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +1 science, +4 culture
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+1, player_score=player_score+4 WHERE player_id='$player_id'");

        $notifArgs['culture'] = 4;
        $notifArgs['science'] = 1;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s) and ${culture} culture point(s)'), $notifArgs);

        self::incStat(4, 'points_cards_effects', $player_id);
    }

    function cultural_heritage_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +2 science, +2 culture
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+2, player_score=player_score+2 WHERE player_id='$player_id'");

        $notifArgs['culture'] = 2;
        $notifArgs['science'] = 2;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s) and ${culture} culture point(s)'), $notifArgs);

        self::incStat(2, 'points_cards_effects', $player_id);
    }

    function revolutionary_idea_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +4 science point
        $delta = 4;
        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

        $notifArgs['points'] = $currentSciencePoints + $delta;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);
    }

    function revolutionary_idea_III_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +6 science point
        $delta = 6;
        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

        $notifArgs['points'] = $currentSciencePoints + $delta;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('updateSciencePoints', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);
    }


    function rich_land_A_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildProduction";
    }

    function rich_land_I_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildProduction";
    }

    function rich_land_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        self::addEffect($card_type_id, 'endAction');
        return "mustBuildProduction";
    }

    function charlie_chaplin_III_getculture($player_id, $buildings, $cards_in_tableau)
    {
        // Best theater x2 culture
        $bonus = 0;

        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];

            if ($card_type['type'] == 'Theater') {
                $bonus = max($bonus, $card_type['culture']);
            }
        }

        return $bonus;
    }

    function scientific_breakthrough_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Score science points

        $players = self::loadPlayersBasicInfos();

        foreach ($players as $player_id => $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0) {
                // Progress on Culture & Science
                $progression = self::getObjectFromDB("SELECT player_culture, player_science FROM player WHERE player_id='$player_id'");
                $sql = "UPDATE player SET
                        player_science_points=player_science_points+'" . $progression['player_science'] . "'
                        WHERE player_id='$player_id'";
                self::DbQuery($sql);

                self::notifyAllPlayers('scoreProgress', '', array(
                    'player_name' => self::getActivePlayerName(),
                    'player_id' => $player_id,
                    'culture' => 0,
                    'science' => $progression['player_science']
                ));
            }
        }

        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: Each civilization scores science equals to its science rating'), $notifArgs);
    }

    function spy_II_aggression($player_id, $card_type_id, $card_type, $defender_id, $notifArgs)
    {
        // Loose/Win 5 science points

        $players = self::loadPlayersBasicInfos();
        $player_id = self::getActivePlayerId();

        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$defender_id'");
        $delta = min(5, $currentSciencePoints);

        self::DbQuery("UPDATE player SET player_science_points=player_science_points-$delta WHERE player_id='$defender_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");

        $notifArgs['culture'] = 0;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);

        $notifArgs['culture'] = 0;
        $notifArgs['science'] = -$delta;
        $notifArgs['sciencedisp'] = $delta;
        $notifArgs['player_name'] = $players[$defender_id]['player_name'];
        $notifArgs['player_id'] = $defender_id;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${sciencedisp} science point(s)'), $notifArgs);

    }

    function strategic_territory_I_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // pick 3 military cards
        self::pickMilitaryCardsForPlayer($player_id, 3);

    }

    function strategic_territory_II_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // pick 3 military cards
        self::pickMilitaryCardsForPlayer($player_id, 5);

    }

    function terrorism_II_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $weakest = self::getLeaderIn('score', true);

        $this->gamestate->changeActivePlayer($weakest);
        self::setGameStateValue('saved_player', $weakest);
        return "terrorism";
    }


    function terrorism_II_chooseOpponentTableauCard($card_id)
    {
        // Check this card is on target opponent's

        $player_id = self::getActivePlayerId();
        $defender_id = self::getGameStateValue('saved_player');

        $card = $this->cards->getCard($card_id);

        $players = self::loadPlayersBasicInfos();

        if ($card['location'] != 'tableau' || $card['location_arg'] != $defender_id)
            throw new feException(self::_("You must choose a card in the tableau of:") . ' ' . $players[$defender_id]['player_name'], true);

        if ($this->card_types[$card['type']]['category'] != 'Urban')
            throw new feException(self::_("You must choose an Urban building"), true);

        // Destroy a building on this card
        $bDestroy = self::doDestroy($card_id, $defender_id, true, false, false, true);

        $this->gamestate->nextState('chooseOpponentTableauCard');
    }

    function stDestroyBuildingNextPlayer()
    {
        $players = self::loadPlayersBasicInfos();
        $next_player = self::createNextPlayerTable(array_keys($players));

        $target = $next_player[self::getGameStateValue('saved_player')];

        if ($target == self::getActivePlayerId()) {
            $this->gamestate->nextState('end');
        } else {
            self::setGameStateValue('saved_player', $target);
            $this->gamestate->nextState('next');
        }
    }

    function trade_routes_agreement_I_active($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Is effect already been used this turn?
        if (self::isEffectActive($card_type_id)) {
            throw new feException(self::_("You already used this at this round"), true);
        }

        self::addEffect($card_type_id, 'endTurn');

        if ($card_type_id == 1101) {
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: the next time ${player_name} will use 1 food, he will use 1 resource instead'), $notifArgs);
        } else if ($card_type_id == 101) {
            self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: the next time ${player_name} will use 1 resource, he will use 1 food instead'), $notifArgs);
        }
    }

    function uncertain_borders_I_event($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();
        $weakest = self::getLeaderIn('strength', true);
        $strongest = self::getLeaderIn('strength', false);

        // Is there a yellow token on weakest bank ?

        $token_id = self::getUniqueValueFromDB("SELECT token_id FROM token
            WHERE token_type='yellow' AND token_player_id='$weakest' AND token_card_id IS NULL LIMIT 0,1");

        if ($token_id === null) {
            // No token
            self::notifyAllPlayers("simpleNote", clienttranslate("No yellow token in weakest player bank"), array());
        } else {
            // Remove a token from the bank
            self::DbQuery("DELETE FROM token WHERE token_id='$token_id'");

            self::notifyAllPlayers('moveTokens', '', array(
                'player_id' => $weakest,
                'tokens' => array(
                    array('id' => $token_id, 'card_id' => 9999, 'player' => $weakest, 'type' => 'yellow')
                )
            ));

            // Add a yellow token to strongest
            $moveTokens = array();
            $sql = "INSERT INTO token (token_type, token_card_id, token_player_id) VALUES
                    ('yellow',NULL, '$strongest')";
            self::DbQuery($sql);
            $moveTokens[] = array('id' => self::DbGetLastId(), 'card_id' => null, 'player' => $strongest, 'type' => 'yellow');

            $notifArgs['player_name'] = $players[$strongest]['player_name'];
            $notifArgs['player_name2'] = $players[$weakest]['player_name'];
            $notifArgs['tokens'] = $moveTokens;

            self::notifyAllPlayers('moveTokens', clienttranslate('${card_name}: ${player_name} steals a yellow token from ${player_name2} bank'), $notifArgs);

        }
    }

    function war_over_culture_III_war($aggressor_id, $card_type_id, $card_type, $defender_id, $war_winner, $war_loser, $force, $notifArgs)
    {
        $players = self::loadPlayersBasicInfos();

        $loser_culture_points = self::getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$war_loser'");

        // 5 points + force difference
        $delta = min($force + 5, $loser_culture_points);
        $sql = "UPDATE player SET
                    player_score=player_score+$delta
                    WHERE player_id='$war_winner'";
        self::DbQuery($sql);

        $notifArgs['culture'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $war_winner;
        $notifArgs['player_name'] = $players[$war_winner]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} gains ${culture} points of culture'), $notifArgs);

        self::incStat($delta, 'points_cards_effects', $war_winner);

        $sql = "UPDATE player SET
                    player_score=GREATEST(0,player_score-$delta)
                    WHERE player_id='$war_loser'";
        self::DbQuery($sql);

        $notifArgs['culture'] = -$delta;
        $notifArgs['delta'] = $delta;
        $notifArgs['science'] = 0;
        $notifArgs['player_id'] = $war_loser;
        $notifArgs['player_name'] = $players[$war_loser]['player_name'];
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${delta} points of culture'), $notifArgs);

        self::incStat(-$delta, 'points_cards_effects', $war_loser);
    }

    function war_over_technology_II_war($aggressor_id, $card_type_id, $card_type, $defender_id, $war_winner, $war_loser, $force, $notifArgs)
    {
        if ($force > 0) {
            self::setGameStateValue('saved_player', $war_loser);
            $this->gamestate->changeActivePlayer($war_winner);
            return "stealTechnology";
        }
    }

    function war_over_technology_II_dualChoice($choice_id)
    {
        $war_id = self::getGameStateValue('cardBuilt');
        $to_steal = self::getUniqueValueFromDB("SELECT war_force, war_winner FROM war WHERE war_card_id='$war_id'");

        $winner_id = self::getActivePlayerId();
        $loser_id = self::getGameStateValue('saved_player');

        // Steal science points
        $players = self::loadPlayersBasicInfos();

        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$loser_id'");
        $delta = min($to_steal, $currentSciencePoints);

        self::DbQuery("UPDATE player SET player_science_points=player_science_points-$delta WHERE player_id='$loser_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$winner_id'");

        $notifArgs = array(
            'i18n' => array('card_name'),
            'card_name' => $this->card_types[179]['name'],
            'player_name' => self::getActivePlayerName(),
            'player_id' => $winner_id
        );

        $notifArgs['culture'] = 0;
        $notifArgs['science'] = $delta;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} scores ${science} science point(s)'), $notifArgs);

        $notifArgs['culture'] = 0;
        $notifArgs['science'] = -$delta;
        $notifArgs['sciencedisp'] = $delta;
        $notifArgs['player_name'] = $players[$loser_id]['player_name'];
        $notifArgs['player_id'] = $loser_id;
        self::notifyAllPlayers('scoreProgress', clienttranslate('${card_name}: ${player_name} loses ${sciencedisp} science point(s)'), $notifArgs);

        $this->gamestate->nextState('dualChoice');
    }

    function war_over_technology_II_chooseOpponentTableauCard($card_id)
    {
        // Check this card is on target opponent's

        $player_id = self::getActivePlayerId();
        $defender_id = self::getGameStateValue('saved_player');

        $card = $this->cards->getCard($card_id);

        if ($card['location'] != 'tableau' || $card['location_arg'] != $defender_id)
            throw new feException(self::_("You must choose a card in the tableau of the player you attacked"), true);

        if ($this->card_types[$card['type']]['category'] != 'Special')
            throw new feException(self::_("You must choose a Special technology"), true);

        $card['location_arg'] = $player_id;

        $war_id = self::getGameStateValue('cardBuilt');
        $to_steal = self::getUniqueValueFromDB("SELECT war_force, war_winner FROM war WHERE war_card_id='$war_id'");

        $cost = $this->card_types[$card['type']]['techcost'];
        if ($cost > $to_steal) {
            throw new feException(sprintf(self::_("You may steal a Special technology with a cost lesser than %s"), $to_steal), true);
        }

        self::DbQuery("UPDATE war SET war_force=war_force-$cost WHERE war_card_id='$war_id'");

        $card_type = $this->card_types[$card['type']];
        // => remove the old one with same type if any
        $specialInPlay = self::getCardsOfCategoryFromTableau($player_id, 'Special');
        $bRemoveItAfterwards = false;
        foreach ($specialInPlay as $special) {
            if ($this->card_types[$special['type']]['type'] == $card_type['type']) {
                if ($card['type'] <= $special['type']) {
                    // Stealing a technology that is LOWER than the current one
                    // => must be discarded afterwards
                    $bRemoveItAfterwards = true;
                } else {
                    $this->cards->moveCard($special['id'], 'removed');
                    self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $player_id, 'card_id' => $special['id']));

                    self::removeCardApplyConsequences($player_id, $card['type']);
                }
            }
        }


        // Move card to player tableau
        $this->cards->moveCard($card['id'], 'tableau', $player_id);

        self::notifyAllPlayers('discardFromTableau', '', array('player_id' => $defender_id, 'card_id' => $card['id']));
        self::notifyAllPlayers('playCard', clienttranslate('${card_name}: ${player_name} steals ${territory}'), array(
            'i18n' => array('card_name', 'territory'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->card_types[179]['name'],
            'territory' => $this->card_types[$card['type']]['name'],
            'card' => $card
        ));

        self::removeCardApplyConsequences($defender_id, $card['type']);
        self::adjustPlayerIndicators($defender_id);

        // Yellow/blue token to gain
        self::applyTokenDeltaToPlayer($player_id, $this->card_types[$card['type']], array(
            'i18n' => array('card_name'),
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->card_types[$card['type']]['name']
        ));

        if ($bRemoveItAfterwards) {
            $this->cards->moveCard($card['id'], 'removed');
            self::notifyAllPlayers('discardFromTableau', clienttranslate('You already have a better version of this technology so it is discarded'), array('player_id' => $player_id, 'card_id' => $card['id']));

            self::removeCardApplyConsequences($player_id, $card['type']);

        }

        self::adjustPlayerIndicators($player_id);
        self::adjustPlayerActions($player_id);
        $this->gamestate->nextState('chooseOpponentTableauCard');
    }

    function war_over_territory_II_war($aggressor_id, $card_type_id, $card_type, $defender_id, $war_winner, $war_loser, $force, $notifArgs)
    {
        $tokens_to_take = 1 + floor($force / 5);

        $token_on_bank_nbr = self::getUniqueValueFromDB("SELECT COUNT(token_id) FROM token
                WHERE token_type='yellow' AND token_player_id='$war_loser' AND token_card_id IS NULL");

        $tokens_to_take = min($tokens_to_take, $token_on_bank_nbr);

        $fake_card_type = array('tokendelta' => array('yellow' => $tokens_to_take, 'blue' => 0));

        $players = self::loadPlayersBasicInfos();

        $notifArgs['player_name'] = $players[$war_winner]['player_name'];
        self::applyTokenDeltaToPlayer($war_winner, $fake_card_type, $notifArgs);


        self::loseToken($war_loser, 'yellow', $tokens_to_take);
        $notifArgs['nbr'] = $tokens_to_take;
        $notifArgs['player_name'] = $players[$war_loser]['player_name'];
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} loses ${nbr} yellow token(s)'), $notifArgs);

    }


    function wave_of_nationalism_II_played($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // Count number of civilization stronger

        $player_ordered = self::getLeaderIn('strength', false, 999);

        $nbr_stronger = 0;
        foreach ($player_ordered as $other_player) {
            if ($other_player == $player_id)
                break;

            $nbr_stronger++;
        }

        $remainingPlayers = self::getUniqueValueFromDb("SELECT count(*) FROM player WHERE player_eliminated = 0 AND player_zombie = 0");

        $players_nbr_to_ress = array(4 => 2, 3 => 3, 2 => 6);

        $ress = $players_nbr_to_ress[$remainingPlayers] * $nbr_stronger;

        for ($i = 0; $i < $ress; $i++) {
            self::addEffect($card_type_id, 'endTurn');
        }

        $notifArgs['nbr'] = $ress;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resources for Military units this turn'), $notifArgs);
    }

    function wealthy_territory_I_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +5 resources
        $gain = 5;
        self::getBlueTokens($player_id, 'ress', $gain);

        $notifArgs['nbr'] = $gain;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);
    }

    function wealthy_territory_II_colonized($player_id, $card_type_id, $card, $args, $notifArgs)
    {
        // +9 resources
        $gain = 9;
        self::getBlueTokens($player_id, 'ress', $gain);

        $notifArgs['nbr'] = $gain;
        self::notifyAllPlayers('simpleNote', clienttranslate('${card_name}: ${player_name} gets ${nbr} resource(s)'), $notifArgs);
    }

    function william_shakespeare_II_getculture($player_id, $buildings, $cards_in_tableau)
    {
        // Each Library+Theater +2
        $library_nbr = 0;
        $theather_nbr = 0;
        foreach ($buildings as $building) {
            $card_type = $this->card_types[$building['card_type']];
            if ($card_type['type'] == 'Library')
                $library_nbr++;
            if ($card_type['type'] == 'Theater')
                $theather_nbr++;
        }
        return 2 * min($library_nbr, $theather_nbr);
    }

    /////////////////////////////////////////////////
    // Debugging methods


    function te($event_id)
    {
        // Trigger event with given ID

        // Create a new card with this type
        $this->cards->createCards(array(
            array('type' => $event_id, 'type_arg' => 0, 'nbr' => 1)
        ), 'newcard');
        $cards = $this->cards->getCardsInLocation('newcard');
        $card = reset($cards);

        $this->cards->insertCardOnExtremePosition($card['id'], 'events', true);

        $this->gamestate->nextState('applyEvent');
    }

    function et($event_id)
    {
        self::te($event_id);
    }

    function emptyevt()
    {
        $this->cards->moveAllCardsInLocation('events', 'removed');
    }

    function ac($card_type_id)
    {
        // Add this card to hand of current player
        $player_id = self::getActivePlayerId();

        // Create a new card with this type
        $this->cards->createCards(array(
            array('type' => $card_type_id, 'type_arg' => 0, 'nbr' => 1)
        ), 'newcard');
        $cards = $this->cards->getCardsInLocation('newcard');
        $card = reset($cards);

        $this->cards->moveCard($card['id'], 'hand', $player_id);

        self::notifyAllPlayers('pickCard', '', array('card' => $card, 'player_id' => $player_id));
    }

    function ar($nbr)
    {
        // Add resources
        self::getBlueTokens(self::getActivePlayerId(), 'ress', $nbr);
    }

    function af($nbr)
    {
        // Add food
        self::getBlueTokens(self::getActivePlayerId(), 'food', $nbr);
    }

    function sr($nbr)
    {
        self::spendBlueTokens(self::getActivePlayerId(), 'ress', $nbr);
    }

    function sf($nbr)
    {
        self::spendBlueTokens(self::getActivePlayerId(), 'food', $nbr);
    }

    function asc($delta)
    {
        // +1 science point
        $player_id = self::getActivePlayerId();
        $currentSciencePoints = self::getUniqueValueFromDB("SELECT player_science_points FROM player WHERE player_id='$player_id'");
        self::DbQuery("UPDATE player SET player_science_points=player_science_points+$delta WHERE player_id='$player_id'");


        self::notifyAllPlayers('updateSciencePoints', '', array(
            'player_id' => $player_id,
            'points' => $currentSciencePoints + $delta,
            'science' => $delta
        ));
    }

    function addtokens()
    {
        $player_id = self::getActivePlayerId();

        $fake_card_type = array('tokendelta' => array('yellow' => 2, 'blue' => 2));

        self::applyTokenDeltaToPlayer($player_id, $fake_card_type, array('player_name' => 'toto', 'nbr' => 0, 'card_name' => 'x'));
    }

    function accelerate()
    {
        // Empty Civil deck of current age => will trigger end of age on next turn
        $age = self::getGameStateValue('currentAge');
        $this->cards->moveAllCardsInLocation('civil' . self::ageNumToChar($age), 'removed');
    }

///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:

        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.

    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        if ($from_version <= 1905221444) {
            try {
                self::DbQuery("ALTER TABLE player DROP player_did_revolution");
            } catch (feException $ex) {
                // (ignore)
            }
        }
    }

}
