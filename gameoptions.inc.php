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
 * gameoptions.inc.php
 *
 * throughtheagesmobilereadability game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in throughtheagesmobilereadability.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(

    // note: game variant ID should start at 100 (ie: 100, 101, 102, ...). The maximum is 199.
    100 => array(
                'name' => totranslate('Game version'),
                'values' => array(
                            1 => array( 'name' => totranslate('Handbook'), 'tmdisplay' => totranslate('Handbook (for beginners)') ),
                            2 => array( 'name' => totranslate('Complete'), 'tmdisplay' => totranslate('Complete game'), 'nobeginner' => true  ),
                        )
            ),
    101 => array(
                'name' => totranslate('Peaceful Variant'),
                'values' => array(
                        1 => array( 'name' => totranslate('Off') ),
                        2 => array( 'name' => totranslate('On'), 'tmdisplay' => totranslate('Peaceful Variant') ),
                )
        )

);
