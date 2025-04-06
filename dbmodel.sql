
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- throughtheagesmobilereadability implementation : © Gregory Isabelli <gisabelli@boardgamearena.com> & Romain Fromi <romain.fromi@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

ALTER TABLE `player`
  ADD  `player_science_points` SMALLINT UNSIGNED NOT NULL DEFAULT  '0',
  ADD `player_strength` SMALLINT UNSIGNED NOT NULL DEFAULT '1',
  ADD `player_science` SMALLINT UNSIGNED NOT NULL DEFAULT '1',
  ADD `player_culture` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_happy` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_discontent` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_bid` SMALLINT UNSIGNED NULL DEFAULT NULL,
  ADD `player_blue_toloose` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_yellow_toloose` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_pickedleader_A` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_pickedleader_I` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_pickedleader_II` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_pickedleader_III` TINYINT UNSIGNED NOT NULL DEFAULT '0',
  ADD `player_tactic` SMALLINT UNSIGNED,
  ADD `player_civil_actions` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD `player_civil_actions_used` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD `player_military_actions` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD `player_military_actions_used` TINYINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(20) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `token` (
  `token_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `token_player_id` int(10) unsigned NOT NULL,
  `token_card_id` int(10) unsigned DEFAULT NULL,
  `token_type` enum('blue','yellow') NOT NULL,
  PRIMARY KEY (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `currenteffects` (
  `effect_card_type` mediumint(8) unsigned NOT NULL,
  `effect_duration` enum('endAction','endTurn','custom') NOT NULL,
  `effect_arg` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `effect_card_type` (`effect_card_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `war` (
  `war_card_id` int(10) unsigned NOT NULL,
  `war_attacker` int(10) unsigned NOT NULL,
  `war_defender` int(10) unsigned NOT NULL,
  `war_winner` int(10) unsigned DEFAULT NULL,
  `war_force` tinyint(3) unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `pact` (
  `pact_card_id` int(10) unsigned NOT NULL,
  `pact_a` int(10) unsigned NOT NULL,
  `pact_b` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


