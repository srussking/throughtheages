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
 * throughtheagesmobilereadability.action.php
 *
 * throughtheagesmobilereadability main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/myAction.html", ...)
 *
 */


  class action_throughtheagesmobilereadability extends APP_GameAction
  {
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "throughtheagesmobilereadability_throughtheagesmobilereadability";
            self::trace( "Complete reinitialization of board game" );
      }
  	}


    public function pickCard()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "id", AT_posint, true );
        $this->game->pickCard( $card_id );
        self::ajaxResponse( );
    }

    function increasePopulation()
    {
        self::setAjaxMode();
        $this->game->increasePopulation( );
        self::ajaxResponse( );
    }


    function pass()
    {
        self::setAjaxMode();
        $bConfirm = self::isArg( "confirm" );
        $this->game->pass( $bConfirm );
        self::ajaxResponse( );
    }

    function doNothing()
    {
        self::setAjaxMode();
        $this->game->doNothing( );
        self::ajaxResponse( );
    }

    function concedeGame()
    {
        self::setAjaxMode();
        $this->game->concedeGame( );
        self::ajaxResponse( );
    }

    function cancel()
    {
        self::setAjaxMode();
        $this->game->cancel( );
        self::ajaxResponse( );
    }

    function doNotUseEffect()
    {
        self::setAjaxMode();
        $this->game->doNotUseEffect( );
        self::ajaxResponse( );
    }

    function discardMilitaryCards()
    {
        self::setAjaxMode();

        $cards_raw = self::getArg( "cards", AT_numberlist, true );

        // Removing last ';' if exists
        if( substr( $cards_raw, -1 ) == ';' )
            $cards_raw = substr( $cards_raw, 0, -1 );
        if( $cards_raw == '' )
            $cards = array();
        else
            $cards = explode( ';', $cards_raw );

        $this->game->discardMilitaryCards( $cards );
        self::ajaxResponse( );
    }


    public function build()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->build( $card_id );
        self::ajaxResponse( );
    }

    public function wonderForFree()
    {
        self::setAjaxMode();
        $this->game->wonderForFree(  );
        self::ajaxResponse( );
    }

    public function upgradeChoices()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->upgradeChoices( $card_id );
        self::ajaxResponse( );
    }


    public function playCard()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $specialMode = self::getArg( "specialMode", AT_enum, false, null, array('revolution', 'peacefulChange') );
        $this->game->playCard( $card_id, $specialMode );
        self::ajaxResponse( );
    }

    public function playMilitaryCard()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $target_id = self::getArg( "target", AT_posint, false, null );
        $me_as_a = self::getArg( "me_as_a", AT_bool, false, false );
        $this->game->playMilitaryCard( $card_id, true, $target_id, $me_as_a );
        self::ajaxResponse( );
    }

    public function dualChoice()
    {
        self::setAjaxMode();
        $choice = self::getArg( "choice", AT_posint, true );
        $this->game->choice( $choice );
        self::ajaxResponse( );
    }

    public function buildChoice()
    {
        self::setAjaxMode();

        $choice = self::getArg( "choice", AT_posint, true );
        $from = self::getArg( "from", AT_posint, true );
        $this->game->buildChoice( $choice, $from );
        self::ajaxResponse( );
    }


    public function lossBuilding()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->lossBuilding( $card_id );
        self::ajaxResponse( );
    }

    public function lossPopulation()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->lossPopulation( $card_id );
        self::ajaxResponse( );
    }

    public function lossBlueToken()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->lossBlueToken( $card_id );
        self::ajaxResponse( );
    }

    public function lossYellowToken()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->lossYellowToken( $card_id );
        self::ajaxResponse( );
    }

    public function chooseCard()
    {
      self::setAjaxMode();
      $card_id = self::getArg( "card_id", AT_posint, true );
      $this->game->chooseCard( $card_id );
      self::ajaxResponse( );
    }

    public function bidTerritory()
    {
        self::setAjaxMode();
        $bid = self::getArg( "bid", AT_posint, true );
        $this->game->bidTerritory( $bid );
        self::ajaxResponse( );
    }

    public function sacrifice()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );

        $this->game->sacrifice( $card_id );
        self::ajaxResponse( );
    }

    public function useBonus()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );

        $this->game->useBonus( $card_id );
        self::ajaxResponse( );
    }


    public function proposeRevolution()
    {
        self::setAjaxMode();

        $choice = self::getArg( "choice", AT_posint, true );
        $this->game->proposeRevolution( $choice );
        self::ajaxResponse( );
    }

    public function acceptPact()
    {
        self::setAjaxMode();

        $choice = self::getArg( "choice", AT_posint, true );
        $this->game->acceptPact( $choice );
        self::ajaxResponse( );
    }

    public function cancelPact()
    {
        self::setAjaxMode();

        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->cancelPact( $card_id );
        self::ajaxResponse( );    }

    public function politicActionActive()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );

        $this->game->politicActionActive( $card_id );
        self::ajaxResponse( );
    }

    public function chooseOpponentTableauCard()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );

        $this->game->chooseOpponentTableauCard( $card_id );
        self::ajaxResponse( );
    }

    public function aggressionChooseOpponent()
    {
        self::setAjaxMode();
        $player_id = self::getArg( "player_id", AT_posint, true );

        $this->game->aggressionChooseOpponent( $player_id );
        self::ajaxResponse( );
    }

    public function copyTactic()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "card_id", AT_posint, true );
        $this->game->copyTactic( $card_id );
        self::ajaxResponse( );
    }

    public function undo()
    {
        self::setAjaxMode();
        $this->game->undo(  );
        self::ajaxResponse( );
    }

  }
  
  

