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
 * stats.inc.php
 *
 * throughtheagesmobilereadability game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/

$stats_type = array(

    // Statistics global to table
    "table" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),


    ),
    
    // Statistics existing for each player
    "player" => array(

        "points_culture_progression" => array("id"=> 10,
                    "name" => totranslate("Points: culture progression"),
                    "type" => "int" ),

        "points_cards_effects" => array("id"=> 11,
                    "name" => totranslate("Points: cards effects"),
                    "type" => "int" ),

        "points_final_scoring" => array("id"=> 12,
                    "name" => totranslate("Points: final scoring (events age III)"),
                    "type" => "int" ),

        "points_hunger" => array("id"=> 13,
                    "name" => totranslate("Points: losses because of Hunger"),
                    "type" => "int" ),

        "points_events" => array("id"=> 14,
                    "name" => totranslate("Points: placing events"),
                    "type" => "int" ),


        "score_end_of_I" => array("id"=> 15,
                    "name" => totranslate("Score at the end of age I"),
                    "type" => "int" ),

        "score_end_of_II" => array("id"=> 16,
                    "name" => totranslate("Score at the end of age II"),
                    "type" => "int" ),


        "tech_discovered" => array("id"=> 17,
                    "name" => totranslate("Technologies discovered"),
                    "type" => "int" ),
        "wonder_achieved" => array("id"=> 18,
                    "name" => totranslate("Wonders achieved"),
                    "type" => "int" ),
        "govt_change" => array("id"=> 19,
                    "name" => totranslate("Governement changes"),
                    "type" => "int" ),
        "event_placed" => array("id"=> 20,
                    "name" => totranslate("Events placed on Future events"),
                    "type" => "int" ),
        "tactic_played" => array("id"=> 21,
                    "name" => totranslate("Tactic cards played"),
                    "type" => "int" ),
        "special_played" => array("id"=> 22,
                    "name" => totranslate("Special technologies played"),
                    "type" => "int" ),

        "aggression_played" => array("id"=> 23,
                    "name" => totranslate("War or aggression played"),
                    "type" => "int" ),
        "aggression_targeted" => array("id"=> 24,
                    "name" => totranslate("War or aggression endured"),
                    "type" => "int" ),
        "aggression_won" => array("id"=> 25,
                    "name" => totranslate("War or aggression won (or repelled)"),
                    "type" => "int" ),

        "pacts" => array("id"=> 26,
                    "name" => totranslate("Pacts (accepted)"),
                    "type" => "int" ),

        "territory" => array("id"=> 27,
                    "name" => totranslate("Territory colonized"),
                    "type" => "int" ),


        "uprising" => array("id"=> 28,
                    "name" => totranslate("Uprising turn"),
                    "type" => "int" ),

        "pick_card_action" => array("id"=> 29,
                    "name" => totranslate("Actions used to pick cards from card row"),
                    "type" => "int" ),
        "military_cards_picked" => array("id"=> 30,
                    "name" => totranslate("Military cards picked"),
                    "type" => "int" ),
        "build_action" => array("id"=> 31,
                    "name" => totranslate("Build a civil building"),
                    "type" => "int" ),
        "build_military_action" => array("id"=> 32,
                    "name" => totranslate("Build a military unit"),
                    "type" => "int" ),
        "action_card" => array("id"=> 33,
                    "name" => totranslate("Action card played"),
                    "type" => "int" ),


        "final_pop" => array("id"=> 34,
                    "name" => totranslate("Final population"),
                    "type" => "int" ),
        "final_strength" => array("id"=> 35,
                    "name" => totranslate("Final strength"),
                    "type" => "int" ),

        "total_science_prod" => array("id"=> 36,
                    "name" => totranslate("Total science production (during Maintenance)"),
                    "type" => "int" ),
        "total_food_prod" => array("id"=> 37,
                    "name" => totranslate("Total food production (during Maintenance)"),
                    "type" => "int" ),
        "total_resource_prod" => array("id"=> 38,
                    "name" => totranslate("Total resource production (during Maintenance)"),
                    "type" => "int" ),


        "total_corruption_lost" => array("id"=> 39,
                    "name" => totranslate("Total corruption lost"),
                    "type" => "int" ),
        "total_food_consumption" => array("id"=> 40,
                    "name" => totranslate("Total food consumption"),
                    "type" => "int" ),

    
    )

);
