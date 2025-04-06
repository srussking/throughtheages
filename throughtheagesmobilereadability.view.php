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
 * throughtheagesmobilereadability.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in throughtheagesmobilereadability_throughtheagesmobilereadability.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */
  
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_throughtheagesmobilereadability_throughtheagesmobilereadability extends game_view
  {
    function getGameName() {
        return "throughtheagesmobilereadability";
    }
  	function build_page( $viewArgs )
  	{
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count( $players );
        $firstPlayer = reset( $players );
        $firstPlayer_id = $firstPlayer['player_id'];

        $nbr_non_eliminated = 0;
        foreach ($players as $player) {
            if ($player['player_eliminated'] == 0 && $player['player_zombie'] == 0)
                $nbr_non_eliminated++;
        }


        /*********** Place your code below:  ************/

        $this->page->begin_block( "throughtheagesmobilereadability_throughtheagesmobilereadability", "ressplace" );
        $this->page->begin_block( "throughtheagesmobilereadability_throughtheagesmobilereadability", "foodplace" );
        $this->page->begin_block( "throughtheagesmobilereadability_throughtheagesmobilereadability", "player" );
        $this->page->begin_block( "throughtheagesmobilereadability_throughtheagesmobilereadability", "playerbid" );

        global $g_user;

        $this->tpl['CURRENT_PLAYER_ID'] = $g_user->get_id();

        if( isset( $players[ $g_user->get_id() ] ) )
        {
            // Place player tableau first
            $player = $players[ $g_user->get_id() ];

            for( $i=1;$i<=50;$i++ )
            {
                $this->page->insert_block( 'ressplace', array( 'PLAYER_ID' => $g_user->get_id(), 'NO' => $i ) );
            }
            for( $i=1;$i<=50;$i++ )
            {
                $this->page->insert_block( 'foodplace', array( 'PLAYER_ID' => $g_user->get_id(), 'NO' => $i ) );
            }

            $firstPlayer_label = ($firstPlayer_id==$g_user->get_id()) ? '('.self::_('First player').')': '';

            $this->page->insert_block( "player", array( "PLAYER_ID" => $player['player_id'],
                                                        "PLAYER_NAME" => $player['player_name'],
                                                        "FIRSTPLAYER" => $firstPlayer_label,
                                                            "PLAYER_COLOR" => $player['player_color'] ) );

            // Order to display boards
            $player_order = array($g_user->get_id() );
            $player_order_beforeplayer = array();
            $bBeforePlayer = true;
            foreach( $players as $player )
            {
                if( $player['player_id'] == $g_user->get_id() )
                    $bBeforePlayer = false;
                else if( $bBeforePlayer )
                    $player_order[] = $player['player_id']; // Push at the end
                else if( ! $bBeforePlayer )
                    $player_order_beforeplayer[] = $player['player_id']; // Push at the end
            }

            $player_order = array_merge( $player_order_beforeplayer, $player_order );
        }
        else
        {
            $player_order = array_keys( $players );
        }

        foreach( $player_order as $player_id )
        {
            $player = $players[ $player_id ];
            if( $player['player_id'] != $g_user->get_id() )
            {
                $this->page->reset_subblocks( 'ressplace' );
                $this->page->reset_subblocks( 'foodplace' );

                for( $i=1;$i<=50;$i++ )
                {
                    $this->page->insert_block( 'ressplace', array( 'PLAYER_ID' => $player['player_id'], 'NO' => $i ) );
                }
                for( $i=1;$i<=50;$i++ )
                {
                    $this->page->insert_block( 'foodplace', array( 'PLAYER_ID' => $player['player_id'], 'NO' => $i ) );
                }

                $firstPlayer_label = ($firstPlayer_id==$player['player_id']) ? '('.self::_('First player').')': '';

                $this->page->insert_block( "player", array( "PLAYER_ID" => $player['player_id'],
                                                            "PLAYER_NAME" => $player['player_name'],
                                                            "FIRSTPLAYER" => $firstPlayer_label,
                                                            "PLAYER_COLOR" => $player['player_color'] ) );
            }

            $this->page->insert_block( "playerbid", array( "PLAYER_ID" => $player['player_id'],
                                                        "PLAYER_NAME" => $player['player_name'],
                                                        "PLAYER_COLOR" => $player['player_color'] ) );

        }

        $this->tpl['MY_HAND'] = self::_("My hand");

        $this->tpl['TERRITORY_TO_COLONIZE'] = self::_("Territory to colonize");

        $this->tpl['AGGRESSION'] = self::_("Aggression");

        $this->tpl['COMMON_TACTICS_AREA'] = self::_("Common tactics area");

        $this->tpl['FINAL_SCORING'] = self::_("Final scoring");

        if( $this->game->getGameStateValue( 'game_version' ) == 2 )
            $this->tpl['FINAL_SCORING_VISIBILITY'] = 'block';
        else
            $this->tpl['FINAL_SCORING_VISIBILITY'] = 'none';


        $this->tpl['NB_3_PLAYERS_VISIBILITY'] = ( $nbr_non_eliminated <= 3 ) ? 'block' : 'none';
        $this->tpl['NB_2_PLAYERS_VISIBILITY'] = ( $nbr_non_eliminated <= 2 ) ? 'block' : 'none';

        /*********** Do not change anything below this line  ************/
  	}
  }
  

