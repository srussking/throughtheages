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
 * states.inc.php
 *
 * throughtheagesmobilereadability game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => 9 )
    ),
    

    ///////////////////// Player turn ////////////////////:

    // (Note: 8 is taken)

    9 => array(
        "name" => "firstPlayerTurn",
        "description" => '',
        "type" => "game",
        "action" => "stFirstPlayerTurn",
        "transitions" => array( "nextPlayer" => 10, "endGame" => 98 )
    ),


    10 => array(
        "name" => "startPlayerTurn",
        "description" => '',
        "type" => "game",
        "action" => "stStartPlayerTurn",
        "transitions" => array( "normalTurn" => 1101, "firstTurn" => 13, "advancedTurn" => 21, "resolveWar" => 28 )
    ),
    
    1101 => array(
    		"name" => "adjustPlayerActions",
        "description" => '',
        "type" => "game",
        "action" => "stAdjustPlayerActions",
        "transitions" => array( "" => 11 )
    ),
    
    11 => array(
    		"name" => "playerTurn",
    		"description" => clienttranslate('${actplayer} may do ${civil} civil actions, ${military} military actions, or pass'),
    		"descriptionmyturn" => clienttranslate('${you} may do ${civil} civil actions, ${military} military actions, or pass'),
    		"type" => "activeplayer",
    		"args" => "argPlayerTurn",
    		"action" => "stPlayerTurn",
    		"possibleactions" => array( "increasePopulation", "build", "destroy", "upgrade", "playCard", "pickCard", "copyTactic", "pass", "undo" ),
    		"transitions" => array( "endOfTurn" => 40, "endAction" => 12, "applyEvent" => 15, "buildChoice" => 14,
    		            "mustBuildCivil" => 200, "mustBuildProduction" => 201, "mustBuildWonder" => 205, "mustPlayTechnology" => 208,
    		            "mustUpgradeBuilding" => 209, "mustBuildMilitary" => 211, "1wonderForFree" => 212, "2wonderForFree" => 213, "3wonderForFree" => 214,
                        "homer" => 238, "chooseReservesGain" => 239, "churchill" => 241
    		 )
    ),

    12 => array(
        "name" => "endOfAction",
        "description" => '',
        "type" => "game",
        "action" => "stEndOfAction",
        "transitions" => array( "continue" => 1101, "continueFirstTurn" => 13, "no_more_action" => 40, "removeTokens" => 1201 )
    ),
    
    1201 => array( // associate to "end of action"
    		"name" => "lossBlueToken",
    		"description" => clienttranslate('Some players must choose from where to take token(s)'),
    		"descriptionmyturn" => clienttranslate('${you} must choose from where to take ${nbrblue} blue token(s)'),
    		"type" => "multipleactiveplayer",
    		"args" => "argLossTokens",
    		"action" => "stLossBlueToken",
    		"possibleactions" => array( "lossBlueToken" ),
    		"transitions" => array( "endEvent" => 1202, "continue" => 1201, "zombiePass" => 1202 )
    ),
    1202 => array( // associate to "end of action"
    		"name" => "lossYellowToken",
    		"description" => clienttranslate('Some players must choose from where to take token(s)'),
    		"descriptionmyturn" => clienttranslate('${you} must choose from where to take ${nbryellow} yellow token(s)'),
    		"type" => "multipleactiveplayer",
    		"args" => "argLossTokens",
    		"action" => "stLossYellowToken",
    		"possibleactions" => array( "lossYellowToken" ),
    		"transitions" => array( "endEvent" => 12, "continue" => 1202, "zombiePass" => 12 )
    ),

    
    13 => array(
    		"name" => "playerTurnFirstTurn",
    		"description" => clienttranslate('${actplayer} may do ${civil} civil actions, or pass'),
    		"descriptionmyturn" => clienttranslate('${you} may do ${civil} civil actions, or pass'),
    		"type" => "activeplayer",
    		"args" => "argPlayerTurn",
            "action" => "stPlayerTurn",
    		"possibleactions" => array(  "pickCard", "pass", "undo" ),
    		"transitions" => array( "endOfTurn" => 50, "endAction" => 12, "zombiePass" => 50 )
    ),
    
    14 => array(
    		"name" => "buildChoice",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose an action or cancel'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} can:'),
    		"type" => "activeplayer",
    		"args" => "argBuildChoice",
    		"possibleactions" => array( "buildChoice", "build", "cancel", "destroy", "upgrade" ),
    		"transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12 )
    ),

    15 => array(
        "name" => "pickEvent",
        "description" => '',
        "type" => "game",
        "action" => "stPickEvent",
        "transitions" => array( "endEvent" => 22, "bidTerritory" => 17,
            "freeWarrior" => 202, "freeTemple" => 203, "freeFoodResource" => 204, "lossPopulation" => 206, "lossBuilding" => 207,
            "freeFoodResourceCustom" => 216, 'lossPopulationMultiple' => 217, "payResourceFood" => 223, "checkLooseTokens" => 224,
            "pickCardsFromRow" => 225, "ravagesOfTime" => 230, "terrorism" => 232, "lossColony" => 234, "developmentOfCivilization" => 237,
            "discardMilitary" => 40
         )
    ),
    16 => array(
        "name" => "endEvent",
        "description" => '',
        "type" => "game",
        "action" => "stEndEvent",
        "transitions" => array( "endEvent" => 22, "endWar" => 21  )
    ),
    
    17 => array(
		"name" => "bidTerritory",
		"description" => clienttranslate('${card_name}: ${actplayer} may bid units to sacrifice for the new Territory'),
		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may bid units to sacrifice for the new Territory'),
		"type" => "activeplayer",
		"args" => "argBidTerritory",
		"possibleactions" => array( "bidTerritory" ),
		"transitions" => array( "bidTerritory" => 18, "pass" => 18 )
    ),
    18 => array(
        "name" => "bidTerritoryNextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stBidTerritoryNextPlayer",
        "transitions" => array( "endOfBid" => 19, "nextPlayer" => 17, "everyonePass" => 16  )
    ),
    19 => array(
		"name" => "bidTerritorySendUnit",
		"description" => clienttranslate('${card_name}: ${actplayer} must send at least one military unit in the colonization force (${strength}/${bid} strength points).'),
		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must send at least one military unit in the colonization force (${strength}/${bid} strength points).'),
		"type" => "activeplayer",
		"args" => "argSacrificeTerritory",
        "action" => "stSendColonizationForce",
		"possibleactions" => array( "sacrifice" ),
		"transitions" => array( "continue" => 8, "end" => 16, "zombiePass" => 16 )
    ),
    8 => array(
		"name" => "bidTerritorySendUnitOrBonus",
		"description" => clienttranslate('${card_name}: ${actplayer} must send more units or play colonization bonus cards (${strength}/${bid} strength points).'),
		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must send more units or play colonization bonus cards (${strength}/${bid} strength points).'),
		"type" => "activeplayer",
		"args" => "argSacrificeTerritory",
		"possibleactions" => array( "sacrifice", "useBonus", "undo" ),
		"transitions" => array( "continue" => 8, "end" => 16, "zombiePass" => 16 )
    ),

    // Advanced rules
    
    21 => array(
    		"name" => "playerTurnPolitic",
    		"description" => clienttranslate('${actplayer} may do 1 politic action'),
    		"descriptionmyturn" => clienttranslate('${you} may do 1 politic action'),
    		"type" => "activeplayer",
            "args" => "argPlayerTurnPolitic",
    		"action" => "stPlayerTurnPolitic",
    		"possibleactions" => array( "politicAction", "donothing", "concedeGame" ),
    		"transitions" => array( "endAction" => 22, "donothing" => 1101, "applyEvent" => 15,
    		            "aggressionOpponentMaySacrifice" => 25, "pactMayAccept" => 29,
    		            "christopherColumbus" => 215, "concedeGame" => 51, "endGameConcede" => 99, "zombiePass" => 1101 )
    ),

    22 => array( // associate to "end of political action"
    		"name" => "lossBlueToken",
    		"description" => clienttranslate('Some players must choose from where to take token(s)'),
    		"descriptionmyturn" => clienttranslate('${you} must choose from where to take ${nbrblue} blue token(s)'),
    		"type" => "multipleactiveplayer",
    		"args" => "argLossTokens",
    		"action" => "stLossBlueToken",
    		"possibleactions" => array( "lossBlueToken" ),
    		"transitions" => array( "endEvent" => 2201, "continue" => 22, "zombiePass" => 2201 )
    ),

    2201 => array( // associate to "end of political action"
    		"name" => "lossYellowToken",
    		"description" => clienttranslate('Some players must choose from where to take token(s)'),
    		"descriptionmyturn" => clienttranslate('${you} must choose from where to take ${nbryellow} yellow token(s)'),
    		"type" => "multipleactiveplayer",
    		"args" => "argLossTokens",
    		"action" => "stLossYellowToken",
    		"possibleactions" => array( "lossYellowToken" ),
    		"transitions" => array( "endEvent" => 2202, "continue" => 2201, "zombiePass" => 2202 )
    ),

    2202 => array( // associate to "end of political action"
        "name" => "playerTurnPolitic",
        "description" => clienttranslate('${actplayer} may play another political action using Julius Caesar'),
        "descriptionmyturn" => clienttranslate('${you} may play another political action using Julius Caesar'),
        "type" => "activeplayer",
        "action" => "stPlayerTurnPoliticCaesar",
        "possibleactions" => array( "politicAction", "donothing", "concedeGame" ),
        "transitions" => array( "endAction" => 22, "donothing" => 1101, "applyEvent" => 15,
            "aggressionOpponentMaySacrifice" => 25, "pactMayAccept" => 29,
            "christopherColumbus" => 215, "concedeGame" => 51, "endGameConcede" => 99, "zombiePass" => 1101 )
    ),

    /// Aggression / War

    25 => array(
    		"name" => "aggressionOpponentDefense",
    		"description" => "",
    		"type" => "game",
    		"action" => "stAggressionOpponentDefense",
    		"transitions" => array( "aggressionMaySacrifice" => 27, "aggressionResolve" => 28 )
    ),
    27 => array(
    		"name" => "aggressionMaySacrifice",
    		"description" => clienttranslate('${card_name}: ${actplayer} may play or discard up to ${quantity} military cards to defend against the aggression (${strength} strength points needed)'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may play or discard up to ${quantity} military cards to defend against the aggression (${strength} strength points needed)'),
    		"type" => "activeplayer",
    		"args" => "argAggressionStrengthDefender",
    		"possibleactions" => array( "donothing", "discardMilitaryCards", "discardToDefend" ),
    		"transitions" => array( "aggressionResolve" => 28, "donothing" => 28, "zombiePass" => 28 )
    ),

    28 => array(
        "name" => "aggressionResolve",
        "description" => '',
        "type" => "game",
        "action" => "stAggressionResolve",
        "transitions" => array( "end" => 22, "endWar" => 21,
            "lossPopulation" => 206, "stealFoodResourceChoice" => 219, "stealFoodResourceChoice5" => 228, "stealFoodResourceChoice7" => 229,
            "chooseBuildingToDestroy" => 220, "annex" => 233, "stealTechnology" => 236,  "infiltrate" => 240
         )
    ),

    29 => array(
    		"name" => "pactMayAcceptNext",
    		"description" => '',
    		"type" => "game",
    		"action" => "stPactMayAcceptNext",
    		"transitions" => array( "" => 30 )
    ),
    30 => array(
    		"name" => "pactMayAccept",
    		"description" => clienttranslate('${card_name}: ${actplayer} may accept the Pact (as ${a_or_b}) from ${proposer} (as ${b_or_a})'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may accept the Pact (as ${a_or_b}) from ${proposer} (as ${b_or_a})'),
    		"type" => "activeplayer",
    		"args" => "argPactMayAccept",
    		"possibleactions" => array( "acceptPact" ),
    		"transitions" => array( "acceptPact" => 16 )
    ),
     
    ///////////////////// End of player turn ////////////////////:

 /*   47 => array(
    		"name" => "endOfTurnConfirmUprising",
    		"description" => clienttranslate('${actplayer} must confirm his end of turn'),
    		"descriptionmyturn" => clienttranslate('${you} are going to face an uprising, are you sure to pass?'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "endOfTurnConfirm", "undo" ),
    		"transitions" => array( "endOfTurnConfirm" => 11 )
    ),
    48 => array(
    		"name" => "endOfTurnConfirmHunger",
    		"description" => clienttranslate('${actplayer} must confirm his end of turn'),
    		"descriptionmyturn" => clienttranslate('${you} are going to loose culture because of hunger, are you sure to pass?'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "endOfTurnConfirm", "undo" ),
    		"transitions" => array( "endOfTurnConfirm" => 49 )
    ),
    49 => array(
    		"name" => "endOfTurnConfirmCorruption",
    		"description" => clienttranslate('${actplayer} must confirm his end of turn'),
    		"descriptionmyturn" => clienttranslate('${you} are going to loose resource because of corruption, are you sure to pass?'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "endOfTurnConfirm", "undo" ),
    		"transitions" => array( "endOfTurnConfirm" => 50 )
    ),     */

    40 => array(
        "name" => "discardMilitaryCards",
        "description" => clienttranslate('${actplayer} must discard ${nbr} military cards'),
        "descriptionmyturn" => clienttranslate('${you} must discard ${nbr} military cards'),
        "type" => "activeplayer",
        "action" => "stDiscardMilitaryCards",
        "args" => "argDiscardMilitaryCards",
        "possibleactions" => array( "discardMilitaryCards" ),
        "transitions" => array( "discardMilitaryCards" => 50, "endEvent" => 16, "zombiePass" => 50, "endOfTurn" => 50 )
    ),
    
    50 => array(
        "name" => "endOfTurn",
        "description" => '',
        "type" => "game",
        "action" => "stEndOfTurn",
        "updateGameProgression" => true,
        "transitions" => array( "nextPlayer" => 10, "nextFirstPlayer" => 9 )
    ),
  
    51 => array(
        "name" => "concedeGame",
        "description" => '',
        "type" => "game",
        "action" => "stConcedeGame",
        "updateGameProgression" => true,
        "transitions" => array( "nextPlayer" => 10, "nextFirstPlayer" => 9 )
    ),
  
    98 => array(
        "name" => "finalScoring",
        "description" => '',
        "type" => "game",
        "action" => "stFinalScoring",
        "transitions" => array( "" => 99 )
    ),
    // Final state.
    // Please do not modify.
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ),

    
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////// Cards effect states ///////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////
    

    200 => array(
    		"name" => "mustBuildCivil",
    		"description" => clienttranslate('${card_name}: ${actplayer} must build or upgrade an urban building'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must build or upgrade an urban building'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "build", "upgrade", "upgradeChoice", "cancel" ),
    		"transitions" => array( "endAction" => 12, "buildChoice" => 14, "cancel" => 12, "zombiePass" => 12 )
    ),
    201 => array(
    		"name" => "mustBuildProduction",
    		"description" => clienttranslate('${card_name}: ${actplayer} must build or upgrade a mine or farm'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must build or upgrade a mine or farm'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "build", "upgrade", "upgradeChoice", "cancel" ),
    		"transitions" => array( "endAction" => 12, "buildChoice" => 14, "cancel" => 12, "zombiePass" => 12 )
    ),
    202 => array(
    		"name" => "freeWarrior",
    		"description" => clienttranslate('${card_name}: some players may build a Warrior unit for free'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may build a Warrior unit for free'),
    		"type" => "multipleactiveplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stBuildForFreeEvent",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    203 => array(
    		"name" => "freeTemple",
    		"description" => clienttranslate('${card_name}: some players may build a Temple (age A) for free'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may build a Temple (age A) for free'),
    		"type" => "multipleactiveplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stBuildForFreeEvent",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    204 => array(
    		"name" => "freeFoodResource",
    		"description" => clienttranslate('${card_name}: some players may gain ${quantity} foods or ${quantity} resources'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may gain ${quantity} foods or ${quantity} resources'),
    		"type" => "multipleactiveplayer",
    		"args" => "argGainFoodOrResources",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    205 => array(
    		"name" => "mustBuildWonder",
    		"description" => clienttranslate('${card_name}: ${actplayer} must build a Wonder step, or cancel'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must build a Wonder step, or cancel'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "build", "cancel" ),
    		"transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12,
    		"1wonderForFree" => 12, "2wonderForFree" => 12, "3wonderForFree" => 12 // Note: Engineering genius makes no combo with Masonry/Architecture/Engineering
    		 )
    ),
    206 => array(
    		"name" => "lossPopulation",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose which yellow token to lost'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose which yellow token to lost'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stLossPopulation",
    		"possibleactions" => array( "lossPopulation" ),
    		"transitions" => array( "endEvent" => 16, "zombiePass" => 16 )
    ),
    207 => array(
    		"name" => "lossBuilding",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose which building to lost'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose which building to lost'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stLossBuilding",
    		"possibleactions" => array( "lossBuilding" ),
    		"transitions" => array( "lossBuilding" => 16, "endEvent" => 16, "zombiePass" => 16 )
    ),
    208 => array(
    		"name" => "mustPlayTechnology",
    		"description" => clienttranslate('${card_name}: ${actplayer} must play a Technology card'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must play a Technology card'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "playCard", "cancel" ),
    		"transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12 )
    ),
    209 => array(
    		"name" => "mustUpgradeBuilding",
    		"description" => clienttranslate('${card_name}: ${actplayer} must upgrade a building or cancel'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must upgrade a building or cancel'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "upgrade", "upgradeChoice", "cancel" ),
    		"transitions" => array( "endAction" => 12, "cancel" => 12, "buildChoice" => 210, "zombiePass" => 12 )
    ),
    210 => array(
    		"name" => "buildChoice",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose an action or cancel'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} can:'),
    		"type" => "activeplayer",
    		"args" => "argBuildChoice",
    		"possibleactions" => array( "buildChoice",  "cancel", "upgrade" ),
    		"transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12 )
    ),
    211 => array(
    		"name" => "mustBuildMilitary",
    		"description" => clienttranslate('${card_name}: ${actplayer} must build a military unit or cancel'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must build a military unit or cancel'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "build", "cancel" ),
    		"transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12 )
    ),
    212 => array(
    		"name" => "wonderForFree",
    		"description" => clienttranslate('${card_name}: ${actplayer} may 1 build another stage of Wonder in the same action'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may 1 build another stage of Wonder in the same action'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stWonderForFree",
    		"possibleactions" => array( "wonderForFree", "doNotUseEffect" ),
    		"transitions" => array( "endAction" => 212, "cancel" => 12, "wonderForFree" => 12, "zombiePass" => 12 )
    ),
    213 => array(
    		"name" => "wonderForFree",
    		"description" => clienttranslate('${card_name}: ${actplayer} may build 2 another stages of Wonder in the same action'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may build 2 another stages of Wonder in the same action'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stWonderForFree",
    		"possibleactions" => array( "wonderForFree", "doNotUseEffect" ),
    		"transitions" => array( "endAction" => 213, "cancel" => 12, "wonderForFree" => 212, "zombiePass" => 12 )
    ),
    214 => array(
    		"name" => "wonderForFree",
    		"description" => clienttranslate('${card_name}: ${actplayer} may build 3 another stages of Wonder in the same action'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may build 3 another stages of Wonder in the same action'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stWonderForFree",
    		"possibleactions" => array( "wonderForFree", "doNotUseEffect" ),
    		"transitions" => array( "endAction" => 214, "cancel" => 12, "wonderForFree" => 213, "zombiePass" => 12 )
    ),
    215 => array(
    		"name" => "christopherColumbus",
    		"description" => clienttranslate('${card_name}: ${actplayer} must play a Territory card'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must play a Territory card'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "politicAction", "cancel" ),
    		"transitions" => array( "endAction" => 16, "cancel" => 21, "zombiePass" => 21 )
    ),
    216 => array(
    		"name" => "freeFoodResourceCustom",
    		"description" => clienttranslate('${card_name}: some players may gain 3 food and/or resources'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may choose to gain:'),
    		"type" => "multipleactiveplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    217 => array(
    		"name" => "lossPopulation",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose which yellow token to lost'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose which yellow token to lost'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"action" => "stLossPopulation",
    		"possibleactions" => array( "lossPopulation" ),
    		"transitions" => array( "endEvent" => 218, "zombiePass" => 16 )
    ),
    218 => array(
        "name" => "lossPopulationNext",
        "description" => '',
        "type" => "game",
        "action" => "stLossPopulationNext",
        "transitions" => array( "next" => 217, "endEvent" => 16 )
    ),
    219 => array(
    		"name" => "stealFoodResource",
    		"description" => clienttranslate('${card_name}: ${actplayer} may choose resources to steal'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may choose resources to steal:'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    220 => array(
    		"name" => "destroyBuilding",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose an urban building of ${player_name} to destroy'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose an urban building of ${player_name} to destroy'),
    		"type" => "activeplayer",
    		"args" => "argDestroyBuilding",
            "action" => "stDestroyBuilding",
    		"possibleactions" => array( "chooseOpponentTableauCard" ),
    		"transitions" => array( "chooseOpponentTableauCard" => 16, "zombiePass" => 16 )
    ),
    223 => array(
    		"name" => "payResourceFood",
    		"description" => clienttranslate('${card_name}: some players must pay 2 foods and/or resources'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must pay:'),
    		"type" => "multipleactiveplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    224 => array(
    		"name" => "lossBlueToken",
    		"description" => clienttranslate('Some players must choose from where to take token(s)'),
    		"descriptionmyturn" => clienttranslate('${you} must choose from where to take ${nbrblue} blue token(s)'),
    		"type" => "multipleactiveplayer",
    		"args" => "argLossTokens",
    		"action" => "stLossBlueToken",
    		"possibleactions" => array( "lossBlueToken" ),
    		"transitions" => array( "endEvent" => 324, "continue" => 224, "zombiePass" => 324 )
    ),
    324 => array(
    		"name" => "lossYellowToken",
    		"description" => clienttranslate('Some players must choose from where to take token(s)'),
    		"descriptionmyturn" => clienttranslate('${you} must choose from where to take ${nbryellow} yellow token(s)'),
    		"type" => "multipleactiveplayer",
    		"args" => "argLossTokens",
    		"action" => "stLossYellowToken",
    		"possibleactions" => array( "lossYellowToken" ),
    		"transitions" => array( "endEvent" => 16, "continue" => 324, "zombiePass" => 16 )
    ),
    225 => array(
    		"name" => "pickCardsFromRow",
    		"description" => clienttranslate('${card_name}: ${actplayer} may pick cards from row (${left} actions left)'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may pick cards from row (${left} actions left)'),
    		"type" => "activeplayer",
    		"args" => "argPickCardsFromRow",
    		"possibleactions" => array( "pickCard", "pickCardsSpecial", "donothing" ),
    		"transitions" => array( "endAction" => 226, "donothing" => 16, "zombiePass" => 16 )
    ),
    226 => array(
    		"name" => "pickCardsFromRowContinue",
    		"description" => clienttranslate('${card_name}: ${actplayer} may pick cards from row (${left} actions left)'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may pick cards from row (${left} actions left)'),
    		"type" => "activeplayer",
    		"args" => "argPickCardsFromRow",
    		"action" => "stPickCardsFromRowContinue",
    		"possibleactions" => array( "pickCard", "pickCardsSpecial", "donothing" ),
    		"transitions" => array( "endAction" => 226, "donothing" => 227, "zombiePass" => 227 )
    ),
    227 => array( // TODO: eventually: support states associated to end of age here
        "name" => "pickCardsFromRowRefill",
        "description" => '',
        "type" => "game",
        "action" => "stPickCardsFromRowRefill",
        "transitions" => array( "refillDone" => 16 )
    ),
    228 => array(
    		"name" => "stealFoodResource5",
    		"description" => clienttranslate('${card_name}: ${actplayer} may choose resources to steal'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may choose resources to steal:'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    229 => array(
    		"name" => "stealFoodResource7",
    		"description" => clienttranslate('${card_name}: ${actplayer} may choose resources to steal'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may choose resources to steal:'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "dualChoice" ),
    		"transitions" => array( "dualChoice" => 16, "zombiePass" => 16 )
    ),
    230 => array(
    		"name" => "ravagesOfTime",
    		"description" => clienttranslate('${card_name}: some players must choose which Wonder to ravage'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose which Wonder to ravage'),
    		"type" => "multipleactiveplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "chooseCard" ),
    		"transitions" => array( "endEvent" => 16, "zombiePass" => 16 )
    ),
    231 => array(
    		"name" => "destroyBuilding",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose an urban building of ${player_name} to destroy'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose an urban building of ${player_name} to destroy'),
    		"type" => "activeplayer",
    		"args" => "argDestroyBuilding",
    		"possibleactions" => array( "chooseOpponentTableauCard" ),
    		"transitions" => array( "chooseOpponentTableauCard" => 232, "zombiePass" => 16 )
    ),
    232 => array(
        "name" => "destroyBuildingNextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stDestroyBuildingNextPlayer",
        "transitions" => array( "next" => 231, "end" => 16 )
    ),
    233 => array(
    		"name" => "annex",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose a territory of ${player_name} to steal'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose a territory of ${player_name} to steal'),
    		"type" => "activeplayer",
    		"args" => "argDestroyBuilding",
    		"possibleactions" => array( "chooseOpponentTableauCard" ),
    		"transitions" => array( "chooseOpponentTableauCard" => 16, "zombiePass" => 16 )
    ),
    234 => array(
    		"name" => "lossColony",
    		"description" => clienttranslate('${card_name}: ${actplayer} must choose a territory to loose'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} must choose a territory to loose'),
    		"type" => "activeplayer",
    		"args" => "argCurrentEffectCard",
    		"possibleactions" => array( "chooseCard" ),
    		"transitions" => array( "endEvent" => 16, "zombiePass" => 16 )
    ),
    236 => array(
    		"name" => "stealTechnology",
    		"description" => clienttranslate('${card_name}: ${actplayer} may steal a Special technology from ${loser_name} or steal Science'),
    		"descriptionmyturn" => clienttranslate('${card_name}: ${you} may steal a Special technology from ${loser_name} or'),
    		"type" => "activeplayer",
    		"args" => "argWarOverResources",
    		"possibleactions" => array( "chooseOpponentTableauCard", "dualChoice" ),
    		"transitions" => array( "chooseOpponentTableauCard" => 236, "dualChoice" => 16, "zombiePass" => 16 )
    ),
    237 => array(
        "name" => "developmentOfCivilization",
        "description" => clienttranslate('${card_name}: some players may either: build a farm, mine or urban building; or develop a technology; or increase their population'),
        "descriptionmyturn" => clienttranslate('${card_name}: ${you} may either: build a farm, mine or urban building; or develop a technology; or'),
        "type" => "multipleactiveplayer",
        "args" => "argDevelopmentOfCivilization",
        "possibleactions" => array( "increasePopulation", "build", "playCard", "donothing" ),
        "transitions" => array( "endEvent" => 16, "donothing" => 16, "zombiePass" => 16 )
    ),
    238 => array(
        "name" => "homerGiveWonderHappyFace",
        "description" => clienttranslate('${card_name}: ${actplayer} may give a Wonder 1 extra happy face'),
        "descriptionmyturn" => clienttranslate('${card_name}: ${you} may give a Wonder 1 extra happy face'),
        "type" => "activeplayer",
        "args" => "argCurrentEffectCard",
        "possibleactions" => array( "chooseCard", "doNotUseEffect" ),
        "transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12 )
    ),
    239 => array(
        "name" => "chooseReservesGain",
        "description" => clienttranslate('${card_name}: ${actplayer} may gain ${quantity} foods or ${quantity} resources'),
        "descriptionmyturn" => clienttranslate('${card_name}: ${you} may gain ${quantity} foods or ${quantity} resources'),
        "type" => "activeplayer",
        "args" => "argGainFoodOrResources",
        "possibleactions" => array( "dualChoice", "cancel" ),
        "transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12)
    ),
    240 => array(
        "name" => "infiltrate",
        "description" => clienttranslate('${card_name}: ${actplayer} must remove ${player_name}’s leader or unfinished wonder from play'),
        "descriptionmyturn" => clienttranslate('${card_name}: ${you} must remove ${player_name}’s leader or unfinished wonder from play'),
        "type" => "activeplayer",
        "args" => "argDestroyBuilding",
        "possibleactions" => array( "chooseOpponentTableauCard" ),
        "transitions" => array( "endAggression" => 16, "zombiePass" => 16 )
    ),
    241 => array(
        "name" => "churchill",
        "description" => clienttranslate('${card_name}: ${actplayer} may gain 3 culture or 3 science and 3 resources for military purpose'),
        "descriptionmyturn" => clienttranslate('${card_name}: ${you} may gain 3 culture or 3 science and 3 resources for military purpose'),
        "type" => "activeplayer",
        "args" => "argCurrentEffectCard",
        "possibleactions" => array( "dualChoice", "cancel" ),
        "transitions" => array( "endAction" => 12, "cancel" => 12, "zombiePass" => 12)
    )
);


