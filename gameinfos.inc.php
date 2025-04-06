<?php

/*
    From this file, you can edit the various meta-information of your game.

    Once you modified the file, don't forget to click on "Reload game informations" from the Control Panel in order in can be taken into account.

    See documentation about this file here:
    http://en.doc.boardgamearena.com/Game_meta-information:_gameinfos.inc.php

*/

$gameinfos = array(


// Game designer (or game designers, separated by commas)
'designer' => 'Vlaada Chvátil',

// Game artist (or game artists, separated by commas)
'artist' => 'Richard Cortes & Paul Niemeyer',

// Year of FIRST publication of this game. Can be negative.
'year' => 2006,

// Game publisher
'publisher' => 'Czech Games Edition',

// Url of game publisher website
'publisher_website' => 'http://czechgames.com/',

// Board Game Geek ID of the publisher
'publisher_bgg_id' => 7345,

// Board game geek ID of the game
'bgg_id' => 25613,


// Players configuration that can be played (ex: 2 to 4 players)
'players' => array( 2,3,4 ),

// Suggest players to play with this number of players. Must be null if there is no such advice, or if there is only one possible player configuration.
'suggest_player_number' => 3,

// Discourage players to play with these numbers of players. Must be null if there is no such advice.
'not_recommend_player_number' => null,
// 'not_recommend_player_number' => array( 2, 3 ),      // <= example: this is not recommended to play this game with 2 or 3 players


// Estimated game duration, in minutes (used only for the launch, afterward the real duration is computed)
'estimated_duration' => 240,

// Time in second add to a player when "giveExtraTime" is called (speed profile = fast)
'fast_additional_time' => 120,

// Time in second add to a player when "giveExtraTime" is called (speed profile = medium)
'medium_additional_time' => 160,

// Time in second add to a player when "giveExtraTime" is called (speed profile = slow)
'slow_additional_time' => 250,

// If you are using a tie breaker in your game (using "player_score_aux"), you must describe here
// the formula used to compute "player_score_aux". This description will be used as a tooltip to explain
// the tie breaker to the players.
// Note: if you are NOT using any tie breaker, leave the empty string.
//
// Example: 'tie_breaker_description' => totranslate( "Number of remaining cards in hand" ),
'tie_breaker_description' => "",

// Game is "beta". A game MUST set is_beta=1 when published on BGA for the first time, and must remains like this until all bugs are fixed.
'is_beta' => 1,

// Is this game cooperative (all players wins together or loose together)
'is_coop' => 0,


// Complexity of the game, from 0 (extremely simple) to 5 (extremely complex)
'complexity' => 5,

// Luck of the game, from 0 (absolutely no luck in this game) to 5 (totally luck driven)
'luck' => 2,

// Strategy of the game, from 0 (no strategy can be setup) to 5 (totally based on strategy)
'strategy' => 4,

// Diplomacy of the game, from 0 (no interaction in this game) to 5 (totally based on interaction and discussion between players)
'diplomacy' => 3,


// Favorite colors support : if set to "true", support attribution of favorite colors based on player's preferences (see reattributeColorsBasedOnPreferences PHP method)
'favorite_colors_support' => true,


'presentation' => array(
    totranslate("This is your chance to make history! You begin with a small tribe and the will to build a great civilization."),
    totranslate("Expand your farms and mines to gain the resources to build your cities. This lays the groundwork for technological advancements, better governments, and great wonders."),
    totranslate("Choose wise leaders whose legacy will lead your people to greatness. Strengthen your army to protect your borders and to expand your territory. And shape history with your political skill."),
    totranslate("What story will you tell?")
),



// Games categories
//  You can attribute a maximum of FIVE "tags" for your game.
//  Each tag has a specific ID (ex: 22 for the category "Prototype", 101 for the tag "Science-fiction theme game")
//  Please see the "Game meta information" entry in the BGA Studio documentation for a full list of available tags:
//  http://en.doc.boardgamearena.com/Game_meta-information:_gameinfos.inc.php
//  IMPORTANT: this list should be ORDERED, with the most important tag first.
//  IMPORTANT: it is mandatory that the FIRST tag is 1, 2, 3 and 4 (= game category)
'tags' => array( 4, 102, 200 ),

'db_undo_support' => true
);
