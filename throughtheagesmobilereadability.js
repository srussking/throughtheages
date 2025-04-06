/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * throughtheagesmobilereadability implementation : © Gregory Isabelli <gisabelli@boardgamearena.com> & Romain Fromi <romain.fromi@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * throughtheagesmobilereadability.js
 *
 * throughtheagesmobilereadability user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock","ebg/zone"
],
function (dojo, declare) {
    return declare("bgagame.throughtheagesmobilereadability", ebg.core.gamegui, {
        constructor: function(){
            this.bThisGameSupportFastReplay = true;

            this.czf = 1;   // Note: czf = card zoom factor

        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
          console.log( gamedatas);
            this.card_row = null;
            this.playerHand = null;
            this.playerTableau = {};
            this.civilHand = {};
            this.cardZoneY = {};
            this.cardZoneB = {};
            this.tokenIdToCardId = {};
            this.playerNbr = 0;
            this.playerBoardRessNbr = {};
            this.playerBoardPopNbr = {};
            this.finalScoring = null;
            this.finalScoringUpper = null;
            this.playerTableauMargin = {};
            this.miniHand = {};
            this.workerPool = {};
            this.remainingCivil = 0;


            // Setting up player boards
            for( var player_id in gamedatas.players )
            {
                var player = gamedatas.players[player_id];
                         

                var player_board_div = $('player_board_'+player_id);
                
                player.id = player_id;
                dojo.place( this.format_block('jstpl_player_board', player ), player_board_div );

                this.playerBoardRessNbr[ player_id ] = 0;
                this.playerBoardPopNbr[ player_id ] = 0;
                this.playerTableauMargin[ player_id ] = 0;
                
                this.playerNbr++;
            }
            
            // Set up your game interface here, according to "gamedatas"

            var card_backgroundsize = null;
            var card_file = 'cards.jpg';
            this.czf = 1;

            var gamearea_size = dojo.coords('game_play_area');
            if( gamearea_size.w > 1380 )
            {
                this.czf = 1.25;
            }

            if( this.czf != 1 ){
                card_backgroundsize = '1000px 3656px';
                card_file = 'cards_big.jpg';
                dojo.addClass( 'game_play_area', 'tta_large');
            }


            // Init card stocks
            this.playerHand = new ebg.stock();
            this.playerHand.backgroundSize = card_backgroundsize;
            this.playerHand.create( this, $('player_hand'), 80*this.czf, 117*this.czf );
            this.playerHand.image_items_per_row = 10;
            this.playerHand.apparenceBorderWidth = '2px';
            this.playerHand.order_items = true;
            this.playerHand.setSelectionMode( 1 );
            this.playerHand.onItemCreate = dojo.hitch( this, 'setupNewCard' );
            this.playerHand.selectionApparance = 'class';
            dojo.connect( this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelection' );
            
            for( player_id in gamedatas.players )
            {
                this.playerTableau[ player_id ] = new ebg.stock();
                this.playerTableau[ player_id ].backgroundSize = card_backgroundsize;
                this.playerTableau[ player_id ].create( this, $('player_tableau_'+player_id), 80*this.czf, 117*this.czf );
                this.playerTableau[ player_id ].image_items_per_row = 10;
                this.playerTableau[ player_id ].apparenceBorderWidth = '2px';
                this.playerTableau[ player_id ].order_items = true;
                this.playerTableau[ player_id ].onItemCreate = dojo.hitch( this, 'setupNewCardOnTableau' );
                this.playerTableau[ player_id ].setSelectionMode( 1 );
                this.playerTableau[ player_id ].selectionApparance = 'class';
                if( player_id == this.player_id )
                {
                    dojo.connect( this.playerTableau[ player_id ], 'onChangeSelection', this, 'onPlayerTableauSelection' );
                }
                else
                {
                    dojo.connect( this.playerTableau[ player_id ], 'onChangeSelection', this, 'onOpponentTableauSelection' );
                }
            }

            // Clevus
            for( player_id in gamedatas.players )
            {
                this.civilHand[ player_id ] = new ebg.stock();
                this.civilHand[ player_id ].backgroundSize = card_backgroundsize;
                this.civilHand[ player_id ].create( this, $('civilhand_'+player_id), 80*this.czf, 117*this.czf );
                this.civilHand[ player_id ].image_items_per_row = 10;
                this.civilHand[ player_id ].apparenceBorderWidth = '2px';
                this.civilHand[ player_id ].order_items = true;
                this.civilHand[ player_id ].onItemCreate = dojo.hitch( this, 'setupNewCard' );
                this.civilHand[ player_id ].setSelectionMode( 0 );
                this.civilHand[ player_id ].selectionApparance = 'class';
            }

            this.commonTactics = new ebg.stock();
            this.commonTactics.backgroundSize = card_backgroundsize;
            this.commonTactics.create( this, $('common_tactics'), 80*this.czf, 117*this.czf );
            this.commonTactics.image_items_per_row = 10;
            this.commonTactics.apparenceBorderWidth = '2px';
            this.commonTactics.order_items = true;
            this.commonTactics.setSelectionMode(1);
            this.commonTactics.onItemCreate = dojo.hitch( this, 'setupNewCard' );
            this.commonTactics.selectionApparance = 'class';
            dojo.connect( this.commonTactics, 'onChangeSelection', this, 'onSelectCommonTactic' );

            this.finalScoring = new ebg.stock();
            this.finalScoring.backgroundSize = card_backgroundsize;
            this.finalScoring.create( this, $('final_scoring'), 80*this.czf, 117*this.czf );
            this.finalScoring.image_items_per_row = 10;
            this.finalScoring.apparenceBorderWidth = '2px';
            this.finalScoring.order_items = true;
            this.finalScoring.setSelectionMode( 0 );
            this.finalScoring.onItemCreate = dojo.hitch( this, 'setupNewCard' );

            this.finalScoringUpper = new ebg.stock();
            this.finalScoringUpper.backgroundSize = card_backgroundsize;
            this.finalScoringUpper.create( this, $('final_scoring_upper'), 80*this.czf, 117*this.czf );
            this.finalScoringUpper.image_items_per_row = 10;
            this.finalScoringUpper.apparenceBorderWidth = '2px';
            this.finalScoringUpper.order_items = true;
            this.finalScoringUpper.setSelectionMode( 0 );
            this.finalScoringUpper.onItemCreate = dojo.hitch( this, 'setupNewCard' );

            
            for( var card_id in this.gamedatas.card_types )
            {
                var card = this.gamedatas.card_types[ card_id ];
                var weight = this.getCardWeight( card_id );
                var image_id = toint( card_id )-1;
                
                if( toint( card_id ) > 1000 )   // Duplicata (ex: Pacts)
                {   image_id = toint( card_id ) - 1001 ;    }
                
                this.playerHand.addItemType( card_id, weight, g_gamethemeurl+'img/'+card_file, image_id );
                this.finalScoring.addItemType( card_id, weight, g_gamethemeurl+'img/'+card_file, image_id );
                this.finalScoringUpper.addItemType( card_id, weight, g_gamethemeurl+'img/'+card_file, image_id );
                if (card.category === 'Tactics') {
                    this.commonTactics.addItemType( card_id, weight, g_gamethemeurl+'img/'+card_file, image_id );
                }

                for( player_id in gamedatas.players )
                {
                    this.playerTableau[ player_id ].addItemType( card_id, weight, g_gamethemeurl+'img/'+card_file, image_id );
                    
                    // Clevus
                    if (card.category === 'Production'
                        || card.category === 'Urban'
                        || card.category === 'Military'
                        || card.category === 'Wonder'
                        || card.category === 'Leader'
                        || card.category === 'Govt'
                        || card.category === 'Special'
                        || card.category === 'Action')
                        this.civilHand[ player_id ].addItemType( card_id, weight, g_gamethemeurl+'img/'+card_file, image_id );
                }
            }
            // Clevus
            this.updateCivilCardsInHand( this.gamedatas.civil_cards_in_hand );
            
            // Mini hands
            for( player_id in this.gamedatas.players )
            {
                this.miniHand[ player_id ] = new ebg.stock();
                this.miniHand[ player_id ].create( this, $('minihand_'+player_id), 20, 30 );
                this.miniHand[ player_id ].order_items = true;
                this.miniHand[ player_id ].setSelectionMode( 0 );
                this.miniHand[ player_id ].onItemCreate = dojo.hitch( this, 'setupMiniHand' );
                
                this.miniHand[ player_id ].addItemType( 'Ac', 1, g_gamethemeurl+'img/minicards.png', 0 );
                this.miniHand[ player_id ].addItemType( 'Am', 2, g_gamethemeurl+'img/minicards.png', 1 );
                this.miniHand[ player_id ].addItemType( 'Ic', 3, g_gamethemeurl+'img/minicards.png', 2 );
                this.miniHand[ player_id ].addItemType( 'Im', 4, g_gamethemeurl+'img/minicards.png', 3 );
                this.miniHand[ player_id ].addItemType( 'IIc', 5, g_gamethemeurl+'img/minicards.png', 4 );
                this.miniHand[ player_id ].addItemType( 'IIm', 6, g_gamethemeurl+'img/minicards.png', 5 );
                this.miniHand[ player_id ].addItemType( 'IIIc', 7, g_gamethemeurl+'img/minicards.png', 6 );
                this.miniHand[ player_id ].addItemType( 'IIIm', 8, g_gamethemeurl+'img/minicards.png', 7 );
                
                this.miniHand[ player_id ].updateDisplay();
            }
            this.updateCardsInHand( this.gamedatas.cards_in_hand );

            // Worker pools
            for (player_id in this.gamedatas.players) {
                this.workerPool[player_id] = new ebg.stock();
                this.workerPool[player_id].create(this, $('workerpool_' + player_id), 20, 20);
                this.workerPool[player_id].setSelectionMode(0);
                this.workerPool[player_id].addItemType('worker', 1, g_gamethemeurl + 'img/icons.png', 6);
            }

            // Filling card row
            for( var i in this.gamedatas.card_row )
            {
                var card = this.gamedatas.card_row[i];
                this.placeCardOnCardRow(card);
            }
            
            // Filling player's hand
            for( var i in this.gamedatas.hand )
            {
                var card = this.gamedatas.hand[i];
                this.playerHand.addToStockWithId( card.type, card.id );
            }

            // Filling player's hand
            for( var i in this.gamedatas.adv_events )
            {
                var card = this.gamedatas.adv_events[i];
                this.finalScoring.addToStockWithId( card.type, card.id );
                this.finalScoringUpper.addToStockWithId( card.type, card.id );
            }

            // Filling player's tableaux
            for( var i in this.gamedatas.tableau )
            {
                var card = this.gamedatas.tableau[i];
                this.placeCardOnTableau( card, card.location_arg );
            }

            // Homer...
            for( var i in this.gamedatas.under_card )
            {
                var card = this.gamedatas.under_card[i];
                var targetCard = this.gamedatas.tableau[card.location_arg];
                var player_id = targetCard.location_arg;
                this.slideCardUnder(card, player_id);
            }

            // Filling Common Tactics Area
            for( var i in this.gamedatas.common_tactics )
            {
                var card = this.gamedatas.common_tactics[i];
                this.commonTactics.addToStockWithId(card.type, card.id);
            }
            
            // Tokens
            for( i in this.gamedatas.tokens )
            {
                var token = this.gamedatas.tokens[ i ];
                this.moveToken( token.card_id, token.player, token.id, token.type );
            }

            for( player_id in gamedatas.players )
            {
                this.updateHappyWarning( player_id );
                this.updateHungerWarning( player_id );
                this.updateCorruptionWarning( player_id );
            }
            
            for( i in this.gamedatas.war )
            {
                var war = this.gamedatas.war[i];
                this.showWar( war.id, war.attacker, war.defender, war.war_type );
            }
            
            this.setRemaining( this.gamedatas.age, this.gamedatas.remaining_cards, this.gamedatas.remaining_cards_mil );
            
            this.setEventDecks( this.gamedatas.events );
            
            // Tooltips
            this.addTooltipToClass( 'ttculture', _("Number of Culture (=Victory) points (+ production each turn)"), '' );
            this.addTooltipToClass( 'ttscience', _("Number of Science points (+ production each turn)"), '' );
            this.addTooltipToClass( 'ttstrength', _("Total strength of your Civilization"), '' );
            this.addTooltipToClass( 'ttcolonization', _("Colonization bonus"), '' );
            this.addTooltipToClass( 'tthappy', _("Total number of happy faces"), '' );
            this.addTooltipToClass( 'ttunhappy', _("Number of discontent workers (must be at most equal to your number of available of available workers)"), '' );
            this.addTooltipToClass( 'ttworkerpool', _("Worker pool (available workers)"), '' );

            this.addTooltipToClass( 'card_row_cross', _("This card will be discarded at the end of the turn"), '' );

            this.addTooltipToClass( 'playerboard_ress', _("Blue tokens bank. If too many blue tokens are out of bank at the end of the turn, you are losing resources due to corruption."), '' );
            this.addTooltipToClass( 'playerboard_food', _("Yellow tokens bank. You have to make sure that each zone is full of tokens or is covered by your happy faces."), '' );

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            this.adaptTableaux();
            dojo.connect(window, "onresize", this, dojo.hitch( this, function(evt){ this.adaptTableaux(); } ));
        },
        
        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            switch( stateName ) {
                case 'bidTerritory':
                    dojo.style('bidTerritory', 'display', 'block');
                    var card_type_id = toint(args.args.territory.type);
                    var artx = 197 * ((toint(card_type_id) - 1) % 10);
                    var arty = 287 * (Math.floor((toint(card_type_id) - 1) / 10));
                    dojo.style('territory', 'backgroundPosition', '-' + artx + 'px -' + arty + 'px');
                    this.setupNewCard($('territory'), card_type_id, args.args.territory.id)
                    for (var player_id in args.args.current_bids) {
                        var playerbid = args.args.current_bids[player_id];
                        if (playerbid === null) {
                            $('playerbid_' + player_id).innerHTML = '-';
                        } else if (playerbid == 0) {
                            $('playerbid_' + player_id).innerHTML = _("(pass)");
                        } else {
                            $('playerbid_' + player_id).innerHTML = playerbid;
                        }
                    }
                    break;

                case 'aggressionMaySacrifice':
                    dojo.style('aggressionInProgress', 'display', 'block');
                    var card_type_id = toint(args.args.card_type);
                    var artx = 197 * ((toint(card_type_id) - 1) % 10);
                    var arty = 287 * (Math.floor((toint(card_type_id) - 1) / 10));
                    dojo.style('aggression', 'backgroundPosition', '-' + artx + 'px -' + arty + 'px');
                    this.setupNewCard($('aggression'), card_type_id, args.args.card_type)
                    this.playerHand.setSelectionMode(2);
                    break;

                case 'pactMayAccept':
                    dojo.style('aggressionInProgress', 'display', 'block');
                    var card_type_id = toint(args.args.card_type);
                    var artx = 197 * ((toint(card_type_id) - 1) % 10);
                    var arty = 287 * (Math.floor((toint(card_type_id) - 1) / 10));
                    dojo.style('aggression', 'backgroundPosition', '-' + artx + 'px -' + arty + 'px');
                    this.setupNewCard($('aggression'), card_type_id, args.args.card_type)
                    break;

                case 'discardMilitaryCards':
                    this.playerHand.setSelectionMode(2);
                    break;

                case 'playerTurnPolitic':
                    if (this.isCurrentPlayerActive()) {
                        // Automatically reveal next event if player has Joan of Arc
                        if (args.args && args.args.joanOfArc) {
                            this.multipleChoiceDialog(_('Joan of Arc will reveal to you the next event'), [_('Ok')], dojo.hitch(this, function () {
                                this.ajaxcall('/throughtheagesmobilereadability/throughtheagesmobilereadability/politicActionActive.html', {
                                    lock: true,
                                    card_id: args.args.joanOfArc
                                }, this, function (result) {
                                });
                            }));
                        }
                    }
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            switch( stateName )
            {
            
            case 'bidTerritory':
                dojo.style( 'bidTerritory', 'display', 'none' );
                break;

            case 'aggressionMaySacrifice':
                dojo.style( 'aggressionInProgress', 'display', 'none' );
                this.playerHand.setSelectionMode( 1 );
                break;

            case 'pactMayAccept':
                dojo.style( 'aggressionInProgress', 'display', 'none' );
                break;
                
            case 'discardMilitaryCards':
                this.playerHand.setSelectionMode( 1 );
                break;
           
            case 'dummmy':
                break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function( stateName, args )
        {
            if( this.isCurrentPlayerActive() )
            {
                switch( stateName ) {
                    case 'playerTurn':
                        if (args.increasePopulationCost !== 9999) {
                            this.addActionButton('action_increasePopulation', _('Increase population') + ' (' + args.increasePopulationCost + '<div class="tta_smallicon smallfood imgtext"></div>)', 'onIncreasePopulation');
                        }
                        this.addActionButton('action_undo', _('Undo'), 'onUndo');
                        this.addActionButton('action_pass', _('Pass'), 'onPass');

                        this.remainingCivil = toint(args.civil);
                        this.remainingMilitary = toint(args.military);
                        this.hammurabi = args.hammurabi;

                        break;

                    case 'playerTurnPolitic':
                        this.addActionButton('action_pass', _('Do nothing'), 'onDoNothing');
                        this.addActionButton('action_concedeGame', _('Leave and concede the game'), 'onConcedeGame', false, false, 'gray');
                        break;

                    case 'playerTurnFirstTurn':
                        this.addActionButton('action_undo', _('Undo'), 'onUndo');
                        this.addActionButton('action_pass', _('Pass'), 'onPass');
                        break;

                    case 'freeWarrior':
                    case 'freeTemple':
                        this.addActionButton('dualChoice1', _('Yes'), 'onDualChoice');
                        this.addActionButton('dualChoice0', _('No'), 'onDualChoice');
                        break;

                    case 'freeFoodResource':
                    case 'chooseReservesGain':
                        this.addActionButton('dualChoice1', args.quantity + ' <div class="tta_icon food"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice0', args.quantity + ' <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;

                    case 'freeFoodResourceCustom':
                        this.addActionButton('dualChoice0', '3 <div class="tta_icon food"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice1', '2 <div class="tta_icon food"></div> + 1 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice2', '1 <div class="tta_icon food"></div> + 2 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice3', '3 <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;

                    case 'stealFoodResource':
                        this.addActionButton('dualChoice0', '3 <div class="tta_icon food"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice1', '2 <div class="tta_icon food"></div> + 1 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice2', '1 <div class="tta_icon food"></div> + 2 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice3', '3 <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;
                    case 'stealFoodResource5':
                        this.addActionButton('dualChoice0', '5 <div class="tta_icon food"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice1', '4 <div class="tta_icon food"></div> + 1 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice2', '3 <div class="tta_icon food"></div> + 2 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice3', '2 <div class="tta_icon food"></div> + 3 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice4', '1 <div class="tta_icon food"></div> + 4 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice5', '5 <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;
                    case 'stealFoodResource7':
                        this.addActionButton('dualChoice0', '7 <div class="tta_icon food"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice1', '6 <div class="tta_icon food"></div> + 1 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice2', '5 <div class="tta_icon food"></div> + 2 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice3', '4 <div class="tta_icon food"></div> + 3 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice4', '3 <div class="tta_icon food"></div> + 4 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice5', '2 <div class="tta_icon food"></div> + 5 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice6', '1 <div class="tta_icon food"></div> + 6 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice7', '7 <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;
                    case 'stealFoodResourceX':
                        var nbr = toint(args.nbr);

                        this.addActionButton('dualChoice0', nbr + '<div class="tta_icon food"></div>', 'onDualChoice');

                        for (var i = 1; i <= (nbr - 1); i++) {
                            this.addActionButton('dualChoice' + i, (nbr - i) + '<div class="tta_icon food"></div> + ' + i + '<div class="tta_icon resource"></div>', 'onDualChoice');
                        }

                        this.addActionButton('dualChoice' + nbr, nbr + '<div class="tta_icon resource"></div>', 'onDualChoice');
                        break;
                    case 'stealTechnology':
                        this.addActionButton('dualChoice0', dojo.string.substitute(_('Steal ${nbr} science'), args), 'onDualChoice');
                        break;
                    case 'payResourceFood':
                        this.addActionButton('dualChoice0', '2 <div class="tta_icon food"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice1', '1 <div class="tta_icon food"></div> + 1 <div class="tta_icon resource"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice2', '2 <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;

                    case 'buildChoice':
                        if (args.possible.build == 1) {
                            this.addActionButton('buildChoice0', _('Build'), 'onBuildChoice');
                        }
                        if (args.possible.destroy == 1) {
                            this.addActionButton('buildChoice1', _('Destroy'), 'onBuildChoice');
                        }
                        for (var baseBuilding_type in args.possible.upgrade) {
                            var baseBuilding_id = args.possible.upgrade[baseBuilding_type];
                            this.addActionButton('buildChoice2' + baseBuilding_id, dojo.string.substitute(_('Upgrade from ${building}'), {building: _(this.gamedatas.card_types[baseBuilding_type].name)}), 'onBuildChoice');
                        }
                        break;

                    case 'bidTerritory':
                        for (var i = (toint(args.min_bid) + 1); i <= args._private.max_bid; i++) {
                            this.addActionButton('bidTerritory' + i, i + '', 'onBidTerritory');
                        }
                        this.addActionButton('bidTerritory0', _('Pass'), 'onBidTerritory');
                        break;

                    case 'bidTerritorySendUnitOrBonus':
                        this.addActionButton('action_undo', _('Undo'), 'onUndo');
                        break;

                    case 'discardMilitaryCards':
                        this.addActionButton('discard', _('Discard selected'), 'onDiscard');
                        break;

                    case 'wonderForFree':
                        this.addActionButton('wonderForFree', _('Build another step'), 'onWonderForFree');
                        break;

                    case 'aggressionMaySacrifice':
                        this.addActionButton('discard', _('Discard selected'), 'onDiscard');
                        this.addActionButton('action_cancel', _("Do not defend"), 'onDoNothing');
                        break;

                    case 'aggressionChooseOpponent':

                        for (var i in this.gamedatas.players) {
                            var player = this.gamedatas.players[i];

                            if (player.id != this.player_id) {
                                this.addActionButton('aggressionChooseOpponent' + i, player.name, 'onAggressionChooseOpponent');
                            }
                        }

                        break;

                    case 'pickCardsFromRow':
                        if (toint(args.left) == 5) {
                            this.addActionButton('action_cancel', _("Do not use and keep my next Political action"), 'onDoNothing');
                        }
                        break;
                    case 'pickCardsFromRowContinue':
                        this.addActionButton('action_donothing', _("I'm done"), 'onDoNothing');
                        break;
                    case 'lossBlueToken':
                        $('nbrblue_toloose').innerHTML = args.nbr[this.player_id].blue;
                        break;
                    case 'lossYellowToken':
                        $('nbryellow_toloose').innerHTML = args.nbr[this.player_id].yellow;
                        break;
                    case 'pactMayAccept':
                        this.addActionButton('acceptPact0', _('Refuse'), 'onAcceptPact');
                        this.addActionButton('acceptPact1', _('Accept'), 'onAcceptPact');
                        break;
                    case 'developmentOfCivilization':
                        this.addActionButton('action_increasePopulation', _('Increase population') + ' (' + args.increasePopulationCost[this.player_id] + '<div class="tta_smallicon smallfood imgtext"></div>)', 'onIncreasePopulation');
                        this.addActionButton('action_donothing', _("Do nothing"), 'onDoNothing');
                        break;
                    case 'churchill':
                        this.addActionButton('dualChoice1', '3 <div class="tta_icon culture"></div>', 'onDualChoice');
                        this.addActionButton('dualChoice2', '3 <div class="tta_icon science"></div> + 3 <div class="tta_icon resource"></div>', 'onDualChoice');
                        break;
                }




                // Generic actions depending on possible actions
                if( this.checkPossibleActions( 'cancel' ) )
                {
                    var text;
                    switch (stateName) {
                        case 'buildChoice':
                        case 'churchill':
                            text = _('Cancel');
                            break;
                        default:
                            text = _('Do not use');
                            break;
                    }
                    this.addActionButton( 'action_cancel', text, 'onCancel' );
                }
                if( this.checkPossibleActions( 'doNotUseEffect' ) )
                {
                    this.addActionButton( 'action_cancel', _('Do not use'), 'onDoNotUseEffect' );
                }
                
            }
            
            
        },

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

        
        getCardWeight: function( card_type_id )
        {
            // Order of cards:
            // 1. Govt
            // 2. Leader
            // 3. Production
            // 4. Special tech
            // 5. Military
            // 6. Urban
            // 7. Wonder
            // 8. Others
            
            // then, order by card type (2 first characters), then by card age
            
            var card_type = this.gamedatas.card_types[ card_type_id ];
            
            var card_type_type_id_bonus = 0;
            
            if( card_type.type == 'Farm' )            {   card_type_type_id_bonus = 1; }
            else if( card_type.type == 'Mine' )       {   card_type_type_id_bonus = 2; }

            else if( card_type.type == 'Leader' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Wonder' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Govt' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Event' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Action' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Bonus' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Colonization' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Civil' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Territory' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Aggression' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Construction' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Pact' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'War' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Military' )       {   card_type_type_id_bonus = 0; }

            else if( card_type.type == 'Lab' )       {   card_type_type_id_bonus = 1; }
            else if( card_type.type == 'Temple' )       {   card_type_type_id_bonus = 2; }
            else if( card_type.type == 'Arena' )       {   card_type_type_id_bonus = 3; }
            else if( card_type.type == 'Library' )       {   card_type_type_id_bonus = 4; }
            else if( card_type.type == 'Theater' )       {   card_type_type_id_bonus = 5; }


            else if( card_type.type == 'Tactics' )       {   card_type_type_id_bonus = 0; }
            else if( card_type.type == 'Infantry' )       {   card_type_type_id_bonus = 1; }
            else if( card_type.type == 'Cavalry' )       {   card_type_type_id_bonus = 2; }
            else if( card_type.type == 'Artillery' )       {   card_type_type_id_bonus = 3; }
            else if( card_type.type == 'Air Force' )       {   card_type_type_id_bonus = 4; }

            var card_type_age_bonus = 0;
            
            if( card_type.age == 'A' )  {   card_type_age_bonus = 1;    }
            else if( card_type.age == 'I' )  {   card_type_age_bonus = 2;    }
            else if( card_type.age == 'II' )  {   card_type_age_bonus = 3;    }
            else if( card_type.age == 'III' )  {   card_type_age_bonus = 4;    }
            
            var card_type_category_bonus = 0;

            if( card_type.category == 'Govt' )
            {    card_type_category_bonus = 1;     }
            else if( card_type.category == 'Leader' )
            {    card_type_category_bonus = 2;     }
            else if( card_type.category == 'Production' )
            {    card_type_category_bonus = 3;     }
            else if( card_type.category == 'Urban' )
            {    card_type_category_bonus = 4;     }

            else if( card_type.category == 'Special' )
            {    card_type_category_bonus = 5;     }
            else if( card_type.category == 'Action' )
            {    card_type_category_bonus = 6;     }
            else if( card_type.category == 'Wonder' )
            {    card_type_category_bonus = 7;     }
            else if( card_type.category == 'Military' )
            {    card_type_category_bonus = 8;     }
            
            // From now: military cards
            
            else if( card_type.category == 'Tactics' )
            {    card_type_category_bonus = 9;     }
            else if( card_type.category == 'Event' )
            {    card_type_category_bonus = 10;     }
            else if( card_type.category == 'Aggression' )
            {    card_type_category_bonus = 11;     }
            else if( card_type.category == 'War' )
            {    card_type_category_bonus = 12;     }
            else if( card_type.category == 'Pact' )
            {    card_type_category_bonus = 13;     }
            else
            {    card_type_category_bonus = 14;     }
            
            return 1000*card_type_category_bonus + 100*card_type_type_id_bonus + 10*card_type_age_bonus;
        },
        
        placeCardOnCardRow: function(card)
        {
            var card_row_part = 1;
            if (card.location_arg > 5) {
                card_row_part = 2;
            }
            if (card.location_arg > 9) {
                card_row_part = 3;
            }
            
            var backy = Math.floor( (toint( card.type )-1) / 10 )* 100;
            var backx = Math.floor( (toint( card.type )-1) % 10 )* 100;

            if( ! $('card_'+card.id ) )
            {
                // Card does not exists => create it
                
                dojo.place( this.format_block( 'jstpl_card', {
                    id: card.id,
                    type: card.type,
                    backx: backx,
                    backy: backy
                    
                } ), 'card_row_'+card_row_part );
            }

            this.attachToNewParent( 'card_'+card.id, 'card_row_'+card_row_part );
            this.slideToObject( 'card_'+card.id, 'card_row_place_'+card.location_arg ).play();
            dojo.connect( $('card_'+card.id), 'onclick', this, 'onPickCard'  );
            this.setupNewCard( $('card_'+card.id), card.type, card.id );
            this.displayCardRowCost(card);
        },
        
        placeCardOnTableau: function( card, player_id, from )
        {
            var loc;

            // Special location on tableau (bottom)
            if( this.gamedatas.card_types[ card.type ]['category'] == 'Govt' )
            {     loc = 'card_place_govt_'+player_id;            }
            else if( this.gamedatas.card_types[ card.type ]['category'] == 'Leader' )
            {     loc = 'card_place_leader_'+player_id;            }
            
            // Special location on tableau (stacks)
            var card_type = this.gamedatas.card_types[ card.type ]['type'];
            var stack_name = null;
            
            if( card_type == 'Mine' )  {    stack_name = 'mine';    }
            else if (card_type == 'Farm') { stack_name = 'farm';    }
            else if (card_type == 'Lab') { stack_name = 'lab';    }
            else if (card_type == 'Temple') { stack_name = 'temple';    }
            else if (card_type == 'Arena') { stack_name = 'arena';    }
            else if (card_type == 'Theater') { stack_name = 'theater';    }
            else if (card_type == 'Library') { stack_name = 'library';    }
            else if (card_type == 'Infantry') { stack_name = 'infantry';    }
            else if (card_type == 'Cavalry') { stack_name = 'cavalry';    }
            else if (card_type == 'Artillery') { stack_name = 'artillery';    }

            if( stack_name )
            {
                this.ensureStackIsVisible( player_id, stack_name, this.gamedatas.card_types[ card.type ].age );
                loc = 'place_'+stack_name+'_'+this.gamedatas.card_types[ card.type ]['age']+'_'+player_id;
                dojo.style( loc, 'display', 'block' );
            }

            this.playerTableau[ player_id ].addToStockWithId( card.type, card.id, from, loc );
            
            if( typeof loc != 'undefined' )
            {
                // Ensure each card is at the correct zorder
                
                dojo.style( 'player_tableau_'+player_id+'_item_'+card.id, 'zIndex',  1+this.ageCharToNum( this.gamedatas.card_types[ card.type ].age ) );
            }
            
            // If this is a Wonder => mark it as "in building" or "ravaged"
            if (toint(card.type_arg) === 1) {
                dojo.style('cardcontentmask_' + card.id, 'display', 'block');
            } else if (toint(card.type_arg) === 2) {
                dojo.addClass('player_tableau_' + player_id + '_item_' + card.id, 'ravaged');
                dojo.addClass('player_tableau_' + player_id + '_item_' + card.id, 'age' + this.gamedatas.card_types[card.type]['age']);
            }
            
            if( this.gamedatas.card_types[ card.type ]['category'] == 'Pact' )
            {
                // If this is a proposed Pact duplicata => mark it
                if( toint( card.type_arg ) == 11 )
                {
                    dojo.style( 'cardcontentmask_'+card.id, 'display', 'block' );
                }

                dojo.style( 'pactmarker_'+card.id, 'display', 'block' );
                
                if( player_id == this.player_id )
                {
                    dojo.style( 'pactcancel_'+card.id, 'display', 'block' );
                    dojo.connect( $('pactcancel_'+card.id), 'onclick', this, 'onCancelPact' );
                }
                                
                // Pact mask
                if( toint( card.type ) < 1000 ) // Original pact card (= A player)
                {
                    dojo.addClass( 'pactmarker_'+card.id, 'pact_a' );
                }
                else if( toint( card.type ) > 1000 ) // Pact duplicata card (= B player)
                {
                    dojo.addClass( 'pactmarker_'+card.id, 'pact_b' );
                }
            }
            
            if( typeof this.gamedatas.card_types[ card.type ].activable != 'undefined' )
            {
                dojo.style( 'activecard_'+card.id, 'display', 'block' );
                dojo.connect( $('activecard_'+card.id), 'onclick', this, 'onActiveCard' );
            }
            if( typeof this.gamedatas.card_types[ card.type ].activablepolitic != 'undefined' )
            {
                dojo.style( 'activecard_'+card.id, 'display', 'block' );
                dojo.connect( $('activecard_'+card.id), 'onclick', this, 'onActiveCardPolitic' );
            }
            
            this.adaptTableaux();
        },

        slideCardUnder: function(card, player_id)
        {
            this.playerTableau[ player_id ].addToStockWithId( card.type, card.id, undefined, 'player_tableau_' + player_id + '_item_' + card.location_arg );
            dojo.addClass('player_tableau_' + player_id + '_item_' + card.id, 'under_card');
            this.attachToNewParent('player_tableau_' + player_id + '_item_' + card.id, 'player_tableau_' + player_id + '_item_' + card.location_arg);
            // Hack to ensure Homer's happy face is visible
            var h = dojo.style('player_stacks_'+player_id, 'height' );
            if (h < 137*this.czf) {
                dojo.style( 'player_stacks_'+player_id, 'height', (137*this.czf)+'px' );
            }
        },
        
        onCancelPact: function( evt )
        {
            dojo.stopEvent( evt );
            if( this.checkAction( 'politicAction' ) )
            {
                // pactcancel_<id>
                var pact_id = evt.currentTarget.id.substr( 11 );
            
                this.confirmationDialog( _('This will cancel this pact: are you sure?'), dojo.hitch( this, function() {
                    this.ajaxcall( '/throughtheagesmobilereadability/throughtheagesmobilereadability/cancelPact.html', { lock: true, card_id: pact_id }, this, function( result ) {} );
                } ) );
            }
        },
        
        onConcedeGame: function( evt )
        {
            dojo.stopEvent( evt );
            if( this.checkAction( 'concedeGame' ) )
            {
                this.confirmationDialog( _('You are going to leave this game and concede the victory to your opponents. Are you sure?'), dojo.hitch( this, function() {
                    this.ajaxcall( '/throughtheagesmobilereadability/throughtheagesmobilereadability/concedeGame.html', { lock: true }, this, function( result ) {} );
                } ) );
            }
        },

        onActiveCard: function( evt )
        {
            // activecard_<id>
            dojo.stopEvent( evt );
            if( this.checkAction( 'build' ) )
            {
                // activecard_<id>
                var card_id = evt.currentTarget.id.substr( 11 );
            
                this.ajaxcall( '/throughtheagesmobilereadability/throughtheagesmobilereadability/build.html', { lock: true, card_id: card_id }, this, function( result ) {} );
            }
        },

        onActiveCardPolitic: function( evt )
        {
            // activecard_<id>
            dojo.stopEvent( evt );
            if( this.checkAction( 'politicAction' ) )
            {
                // activecard_<id>
                var card_id = evt.currentTarget.id.substr( 11 );
            
                this.ajaxcall( '/throughtheagesmobilereadability/throughtheagesmobilereadability/politicActionActive.html', { lock: true, card_id: card_id }, this, function( result ) {} );
            }
        },
        
        ensureStackIsVisible: function( player_id, stack, age )
        {
            // Height
            var h = dojo.style('player_stacks_'+player_id, 'height' );
            if( age == 'III' && h<(255*this.czf) )
            {   h = 255*this.czf;    }
            else if( age == 'II' && h<(210*this.czf) )
            {   h = 210*this.czf;    }
            else if( age == 'I' && h<(165*this.czf) )
            {   h = 165*this.czf;    }
            else if( age == 'A' && h<(120*this.czf) )
            {   h = 120*this.czf;    }
            
            dojo.style( 'player_stacks_'+player_id, 'height', h+'px' );
            
            // Stack visibility
            if( dojo.style( 'stack_'+stack+'_'+player_id, 'display' ) == 'none' )
            {
                var w = this.playerTableauMargin[ player_id ];
                dojo.style( 'stack_'+stack+'_'+player_id, 'display', 'block' );
                w = w+(85*this.czf);
                this.playerTableauMargin[ player_id ] = w;
                dojo.style( 'player_stacks_'+player_id, 'width', w+'px' );
                
                if( dojo.hasClass( 'player_tableau_wrap_'+player_id, 'go_to_new_line' ) )
                {
                
                }
                else
                {
                    // Apply it only if we are not in
                    dojo.style( 'player_tableau_wrap_'+player_id, 'marginLeft', w+'px' );
                }
            }
        },

        adaptTableaux: function()
        {
            dojo.query( '.player_tableau_wrap' ).forEach( dojo.hitch( this, function( node ){
            
                // player_tableau_wrap_<id>
                var player_id = node.id.substr( 20 );
                
                if( dojo.hasClass( 'player_tableau_wrap_'+player_id, 'go_to_new_line' ) )
                {
                    // The tableau is on a new line => should we get it back on the right ?

            /*        if( dojo.style( 'player_tableau_'+player_id, 'height' ) <= 300 )
                    {   // Yes !
                        dojo.removeClass( node, 'go_to_new_line' );
                        
                        var w = this.playerTableauMargin[ player_id ];
                        dojo.style( 'player_tableau_wrap_'+player_id, 'marginLeft', w+'px' );

                        this.playerTableau[ player_id ].updateDisplay();
                        
                        alert('switch to right' );
                    }*/

                }
                else
                {
                    // The tableau is on the right => should we put it on new line ?

                    if( dojo.style( 'player_tableau_'+player_id, 'height' ) > 300 )
                    {   // Yes !300
                        dojo.addClass( node, 'go_to_new_line' );
                        dojo.style( node, 'marginLeft', '0px' );

                         //this.playerTableau[ player_id ].updateDisplay();
//                        alert('switch to new line' );
                    }

                }
                
                this.playerTableau[ player_id ].updateDisplay();
                
            } ) );
        },

        updateCardsInHand: function( cards_in_hand )
        {
            for( var player_id in cards_in_hand )
            {
                var current = {'Ac':0,'Am':0,'Ic':0,'Im':0,'IIc':0,'IIm':0,'IIIc':0,'IIIm':0};
                var items = this.miniHand[ player_id ].getAllItems();
                for( var i in items )
                {
                    current[ items[i].type ]++;
                }

                for( var type in cards_in_hand[ player_id ] )
                {
                    var nbr = cards_in_hand[ player_id ][type];
                    var cur = current[ type ];

                    while( cur < nbr )
                    {
                        this.miniHand[ player_id ].addToStock( type );
                        cur++;
                    }
                    while( cur > nbr )
                    {
                        this.miniHand[ player_id ].removeFromStock( type );
                        cur--;
                    }
                }
            }
        },

        // Clevus
        updateCivilCardsInHand: function( civil_cards_in_hand )
        {
            for( var player_id in civil_cards_in_hand )
            {
                this.civilHand[player_id].removeAll();
                for ( var i in civil_cards_in_hand[player_id] )
                {
                    var card = civil_cards_in_hand[player_id][i];
                    this.civilHand[player_id].addToStockWithId( card.type, card.id );
                }
            }
        },

        getCardTooltip: function( card_type_id )
        {
            var card = dojo.clone( this.gamedatas.card_types[ card_type_id ] );
            
            card.name = _(card.name );
            
            var image_id = toint( card_type_id )-1;
            if( toint( card_type_id ) > 1000 )
            {   image_id = toint( card_type_id )-1001;  }

            card.artx=197 * ( image_id %10 );
            card.arty=287 * ( Math.floor( image_id/10 ) );

            card.age = _("Age")+' '+card.age;
            
            if( card.category == 'Special' )
            {   card.type = card.category;  }

            if( card.category == 'Govt' )
            {   card.category = _("Government");   }
            else if( card.category == 'Action' )
            {   card.category = _("Action card");   }
            else if( card.category == 'Special' )
            {   card.category = _("Special technology");   }
            else if( card.category == 'Urban' )
            {   card.category = _("Urban building")+' ('+_(card.type)+')';   }
            else if( card.category == 'Military' )
            {   card.category = _("Military unit")+' ('+_(card.type)+')';   }
            else if( card.category == 'Production' )
            {   card.category = _("Production building")+' ('+_(card.type)+')';   }
            else
            {   card.category = _(card.category);    }
            
            card.cards_in_play = _("Cards in play");
            card.nbr_cards = card[ 'qt'+this.playerNbr ];
            
            card.techcost_label = _("Technology cost");
            card.techcost_visibility = card.techcost > 0 ? 'block' : 'none';

            card.resscost_label = _("Construction cost");
            card.resscost_visibility = card.resscost > 0 ? 'block' : 'none';

            if( card.category == 'Wonder' )
            {
                var output = '';
                for( var i in card.resscost )
                {
                    if( output != '' )
                    {   output += ' / '; }
                    
                    output += card.resscost[i];
                }
                card.resscost = output;
                card.resscost_visibility = 'block';
            }
            
            var txt = '';

            if( card.aget )
            {
                if( card.bget == '' )
                {
                    // Symetrical pact
                    txt += '<p>'+_("Both civilization get")+': '+_(card.aget)+'</p>';
                }
                else
                {
                    // Asym pact
                    txt += '<p>'+_("Civilization A gets")+': '+_(card.aget)+'</p>';
                    txt += '<p>'+_("Civilization B gets")+': '+_(card.bget)+'</p>';
                }
            }
            
            var per = '';
            if( card.resscost > 0 )
            {   per = ' / '+_("per yellow token");  }
            

            if( card.CA && card.CA != '?' )
            {
                txt += '<p>+'+card.CA+' '+_('Civil actions')+'</p>';
            }

            if( card.MA && card.MA != '?' )
            {
                if( card.category == 'War' || card.category == 'Aggression' )
                {
                    txt += '<p>'+_('Military action cost')+': '+card.MA+'</p>';
                }
                else
                {
                    txt += '<p>+'+card.MA+' '+_('Military actions')+'</p>';
                }
            }

            if( card.ress && card.ress != '?' )
            {
                txt += '<p>+'+card.ress+' '+_('Resource production')+per+'</p>';
            }
            if( card.food && card.food != '?' )
            {
                txt += '<p>+'+card.food+' '+_('Food production')+per+'</p>';
            }
            if( card.culture && card.culture != '?' )
            {
                txt += '<p>+'+card.culture+' '+_('Culture production')+per+'</p>';
            }
            if( card.happy && card.happy != '?'  )
            {
                txt += '<p>+'+card.happy+' '+_('Happy face')+per+'</p>';
            }
            if( card.strength && card.strength != '?' )
            {
                txt += '<p>+'+card.strength+' '+_('Strength')+per+'</p>';
            }
         
            if( card.science && card.science != '?' )
            {
                txt += '<p>+'+card.science+' '+_('Science production')+per+'</p>';
            }

            if( card.artybon )
            {
                txt += '<p>+'+card.artybon+' '+_('strength per army')+'</p>';
            }
            if( card.artybonplus )
            {
                txt += '<p>+'+card.artybonplus+' '+_('strength per army with obsolete units')+'</p>';
            }

            if( card.def && card.def != '?' )
            {
                txt += '<p>+'+card.def+' '+_('Defense bonus')+per+'</p>';
            }
            if( card.colo && card.colo != '?' )
            {
                txt += '<p>+'+card.colo+' '+_('Colonization bonus')+per+'</p>';
            }

            if( card.loser )
            {
                txt += '<p>'+_("War loser")+': '+_(card.loser)+'</p>';
            }
            if( card.winner )
            {
                txt += '<p>'+_("War winner")+': '+_(card.winner)+'</p>';
            }

            if( card.rival )
            {
                txt += '<p>'+_("Aggression target")+': '+_(card.rival)+'</p>';
            }
            if( card.aggressor )
            {
                txt += '<p>'+_("Aggressor")+': '+_(card.aggressor)+'</p>';
            }
            
            if( card.tokendelta )
            {
                if( toint( card.tokendelta.blue ) > 0 )
                {
                    txt += '<p>+'+card.tokendelta.blue+' '+_('blue tokens')+per+'</p>';
                }
                if( toint( card.tokendelta.yellow ) > 0 )
                {
                    txt += '<p>+'+card.tokendelta.yellow+' '+_('yellow tokens')+per+'</p>';
                }
            }
                        
            if( typeof card.text != 'undefined' )
            {
                txt += '<br/>' + _(card.text);
            }
            card.text = txt;
                     
            return this.format_block( 'jstpl_card_tooltip', card );
        },

        setupNewCard: function( card_div, card_type_id, card_id )
        {
            var card = this.gamedatas.card_types[ card_type_id ];
            var html = this.getCardTooltip( card_type_id );
           
            this.addTooltipHtml( card_div.id, html, 0 );
        },
        
       setupMiniHand: function( card_div, card_type_id, card_id )
        {
            var args = {
                age: card_type_id.substr( 0, card_type_id.length-1 ),
                type: card_type_id.slice(-1)=='m' ? _('Military'):_('Civil')
            };
            this.addTooltip( card_div.id, dojo.string.substitute( _("Card in hand (${type}, Age ${age})"), args ), '' );
        },
        
        setupNewCardOnTableau: function( card_div, card_type_id, card_div_id, card_type_arg )
        {
            this.setupNewCard( card_div, card_type_id, card_div_id );
        
            // Extract card_id from card_div_id (player_tableau_X_item_<card_id>)
            var parts = card_div_id.split( '_' );
            var card_id = parts[4];
        
            // Adding card content:
        
            dojo.place( this.format_block( 'jstpl_tableaucard_content', {
                            id:card_id,
                            cancel: _('Cancel'),
                            activate: _('Activate')
                       } ), card_div.id );
                       
            // Add zone component on card
            this.cardZoneY[ card_id ] = new ebg.zone();
            this.cardZoneY[ card_id ].create( this, $('yellowzone_'+card_id), 20, 20 );
            this.cardZoneY[ card_id ].setPattern( 'horizontalfit' );
            this.cardZoneB[ card_id ] = new ebg.zone();
            this.cardZoneB[ card_id ].create( this, $('bluezone_'+card_id), 20, 20 );
            this.cardZoneB[ card_id ].setPattern( 'horizontalfit' );
        },
 
        
        addBoardToken: function( token_id, token_type, token_player, from )
        {
            var placeName = 'food';
            var tokenToAddNo = 0;
            
            if( token_type == 'yellow' )
            {
                this.playerBoardPopNbr[ token_player ] ++;
                tokenToAddNo = this.playerBoardPopNbr[ token_player ];
            }
            else
            {
                this.playerBoardRessNbr[ token_player ] ++;
                tokenToAddNo = this.playerBoardRessNbr[ token_player ];
                placeName = 'ress';
            }

            dojo.place( this.format_block( 'jstpl_boardtoken', {
                no: tokenToAddNo, type: token_type, player_id: token_player
            } ), 'playerboard_'+placeName+'_'+token_player );
            
            if( typeof from != 'undefined' )
            {
                if( from !== null )
                {
                    this.placeOnObject( 'boardtoken_'+token_player+'_'+token_type+'_'+tokenToAddNo, from );
                }
            }
            
//            if( $('boardtoken_'+token_player+'_'+token_type+'_'+tokenToAddNo) === null )
//            {
//                alert( 'boardtoken_'+token_player+'_'+token_type+'_'+tokenToAddNo );
//            }
//            if( $( 'playerboard_'+token_player+'_'+placeName+'place_'+tokenToAddNo ) === null )
//            {
//                alert( 'playerboard_'+token_player+'_'+placeName+'place_'+tokenToAddNo  );
//            }
            
            this.slideToObject( 'boardtoken_'+token_player+'_'+token_type+'_'+tokenToAddNo, 'playerboard_'+token_player+'_'+placeName+'place_'+tokenToAddNo ).play();
        },
        
        moveToken: function( card_id, token_player, token_id, token_type )
        {
            // Place a token at its correct place on the interface
            // Create it if it does not exits
            // Remove it from its ancient place
            // this.tokenIdToCardId keep the track of the old locations of the tokens
            // For food: adjust "worker pool" counter
        
        
            // 1. Try to get where to take the token
            var from = null;
            
            if( typeof this.tokenIdToCardId[ token_id ] != 'undefined' )
            {
                // This token has been registered already in the interface
                
                
                if( this.tokenIdToCardId[ token_id ] === null )
                {
                    // Token bank

                    if( token_type == 'yellow' )
                    {
                        var nextTokenNo = this.playerBoardPopNbr[ token_player ];
                        this.playerBoardPopNbr[ token_player ]--;
                    }
                    else
                    {
                        var nextTokenNo = this.playerBoardRessNbr[ token_player ];
                        this.playerBoardRessNbr[ token_player ]--;
                    }
                    
                    from = $('boardtoken_'+token_player+'_'+token_type+'_'+nextTokenNo);
                }
                else if( toint( this.tokenIdToCardId[ token_id ] ) === 0 )
                {
                    // Worker pool
                    from = $('workerpool_'+token_player);
                    this.workerPool[token_player].removeFromStock('worker');
                    this.updateHappyWarning( token_player );
                }
                else
                {
                    // Token on some card
                    from = $('token_'+token_id);
                }
            }
            
            // 2. Add the token to its target place
            
            if( card_id === null )
            {
                // This token goes to the bank
                this.addBoardToken( token_id, token_type, token_player, from );
            }
            else if( toint( card_id ) === 0 )
            {
                // This token goes on worker pool
                this.workerPool[token_player].addToStock('worker');
                this.updateHappyWarning( token_player );
            }
            else if( toint( card_id ) === 9999 )
            {
                // Special code to say "this token i removed"
                // => do not add it anywhere
            }
            else
            {
                // This token goes on some card
                if( ! $('token_'+token_id ) )
                {
                    dojo.place( this.format_block( 'jstpl_token', {
                        id: token_id, type: token_type
                    } ), 'cardcontent_'+card_id );
                }
                
                if( from !== null )
                {
                    this.placeOnObject( 'token_'+token_id, from );
                }
                
                if( token_type == 'yellow' )
                {
                    this.cardZoneY[ card_id ].placeInZone( 'token_'+token_id );
                }
                else
                {
                    this.cardZoneB[ card_id ].placeInZone( 'token_'+token_id );
                }
            }
            
            
            // 3. Clean what need to be cleaned afterwards
            if( typeof this.tokenIdToCardId[ token_id ] != 'undefined' )
            {

                if( this.tokenIdToCardId[ token_id ] === null )
                {
                    if( card_id !== null )
                    {
                        // This token was from the bank, and is no more in the bank.
                        // => remove board token from the bank
                        if( card_id === 0 )
                        {
                            // Slide it to worker pool before the end
                            var anim = this.slideToObject( from, 'workerpool_'+token_player );
                            dojo.connect( anim, 'onEnd', this, function(node){  dojo.destroy( node ); } );
                            anim.play();
                        }
                        else
                        {
                            dojo.destroy( from );
                        }
                    }
                }
                else if( toint( this.tokenIdToCardId[ token_id ] ) === 0 )
                {
                    // This token was from worker pool
                    // => nothing to be done
                }
                else
                {
                    // This token was on a card
                    // => we must remove it from this card zone if its not on a card at now
                    if( token_type == 'yellow' )
                    {
                        this.cardZoneY[ this.tokenIdToCardId[ token_id ] ].removeFromZone( 'token_'+token_id, (card_id!=0 ? false : true) );
                    }
                    else
                    {
                        this.cardZoneB[ this.tokenIdToCardId[ token_id ] ].removeFromZone( 'token_'+token_id, (card_id!=0 ? false : true) );
                    }
                    
                    // If the token is no more on a card, we must also remove the <div>
                    if( card_id === null )
                    {
                        dojo.destroy( 'token_'+token_id );
                    }
                    else if( toint( card_id ) == 9999 )
                    {
                        dojo.destroy( 'token_'+token_id );
                    }
                    else if( toint( card_id ) == 0 )
                    {
                        // ... and slide it to worker pool first
                        var anim = this.slideToObject( from, 'workerpool_'+token_player );
                        dojo.connect( anim, 'onEnd', this, function(node){  dojo.destroy( node ); } );
                        anim.play();
                        
                    }
                }

            }
        
            // 4. In case of a blue token, update the total number of food/resources in player panel
            if( token_type == 'blue' )
            {
                if( typeof this.tokenIdToCardId[ token_id ] != 'undefined' )
                {
                    if( this.tokenIdToCardId[ token_id ] > 0 )
                    {
                        // Token removed from a card => decrease counter
                        var card_type = this.playerTableau[ token_player ].getItemTypeById( this.tokenIdToCardId[ token_id ] );
                        
                        if( card_type !== null )
                        {
                            if( this.gamedatas.card_types[ card_type ].food && this.gamedatas.card_types[ card_type ].ress!='?' )
                            {   $('food_'+token_player).innerHTML = toint( $('food_'+token_player).innerHTML ) - toint( this.gamedatas.card_types[ card_type ].food );   }
                            if( this.gamedatas.card_types[ card_type ].ress && this.gamedatas.card_types[ card_type ].ress!='?' )
                            {   $('resource_'+token_player).innerHTML = toint( $('resource_'+token_player).innerHTML ) - toint( this.gamedatas.card_types[ card_type ].ress );   }

                            if( this.gamedatas.card_types[ card_type ].type == 'Lab' )
                            {
                                // Special: Bill Gates
                                $('resource_'+token_player).innerHTML = toint( $('resource_'+token_player).innerHTML ) - this.ageCharToNum( this.gamedatas.card_types[ card_type ].age );
                            }
                        }
                        
                    }
                }
                
                if( card_id > 0 && toint( card_id )!=9999 )
                {
                    // Token placed on a card => increase counter
                    var card_type = this.playerTableau[ token_player ].getItemTypeById( card_id );
                    if( typeof this.gamedatas.card_types[ card_type ].food != 'undefined'  && this.gamedatas.card_types[ card_type ].food!='?' )
                    {   $('food_'+token_player).innerHTML = toint( $('food_'+token_player).innerHTML ) + toint( this.gamedatas.card_types[ card_type ].food );   }
                    
                    if( typeof this.gamedatas.card_types[ card_type ].ress != 'undefined' && this.gamedatas.card_types[ card_type ].ress!='?' )
                    {   $('resource_'+token_player).innerHTML = toint( $('resource_'+token_player).innerHTML ) + toint( this.gamedatas.card_types[ card_type ].ress );   }
                    
                    if( this.gamedatas.card_types[ card_type ].type == 'Lab' )
                    {
                        // Special: Bill Gates
                        $('resource_'+token_player).innerHTML = toint( $('resource_'+token_player).innerHTML ) + this.ageCharToNum( this.gamedatas.card_types[ card_type ].age );
                    }
                }
            }
        
        
            // 5. Finally, register this token at this new place
            this.tokenIdToCardId[ token_id ] = card_id;
        },

        ageCharToNum: function( age )
        {
            if( age=='A' ) {    return 0;   }
            else if( age == 'I' )   {   return 1;   }
            else if( age == 'II' )   {   return 2;   }
            else if( age == 'III' )   {   return 3;   }
        },

        hasUprising: function (player_id)
        {
            return toint( $('unhappy_'+player_id).innerHTML ) > this.workerPool[player_id].count();
        },
        
        updateHappyWarning: function( player_id )
        {
            if (this.hasUprising(player_id)) {
                dojo.addClass('unhappy_' + player_id, 'unhappywarning');
            } else {
                dojo.removeClass('unhappy_' + player_id, 'unhappywarning');
            }
            // Update happy place
            var happyplace = Math.min( 8, toint( $('happy_'+player_id).innerHTML ) );
            dojo.query( '#playerboard_food_'+player_id+' .happyplace' ).removeClass( 'happyplace_current' );
            dojo.addClass( 'happyplace_'+player_id+'_'+happyplace, 'happyplace_current' );
        },

        getHungerLoss: function (player_id)
        {
            var stock = parseInt($('food_' + player_id).innerHTML);
            var production = parseInt($('food_prod_' + player_id).innerHTML);
            return - 4 * Math.min(stock + production, 0);
        },
        
        updateHungerWarning: function (player_id)
        {
            var hungerLoss = this.getHungerLoss(player_id);
            if (hungerLoss > 0 && player_id.toString() === this.player_id.toString()) {
                dojo.addClass('food_indicator_' + player_id, 'hunger-warning');
                this.addTooltip('food_indicator_' + player_id, dojo.string.substitute(_("Hunger: you will lose ${quantity} victory points!"), {quantity: hungerLoss}), '' );
            } else {
                dojo.removeClass('food_indicator_' + player_id, 'hunger-warning');
                this.addTooltip('food_indicator_' + player_id, _("Number of available food units (+ production each turn)"), '' );
            }
        },

        getCorruptionLoss: function (player_id)
        {
            var tokensInBank = dojo.query('#playerboard_ress_' + player_id + ' .token_blue').length;
            return tokensInBank > 10 ? 0 : tokensInBank > 5 ? 2 : tokensInBank > 0 ? 4 : 6;
        },

        updateCorruptionWarning: function (player_id)
        {
            var corruptionLoss = this.getCorruptionLoss(player_id);
            if (corruptionLoss > 0 && player_id.toString() === this.player_id.toString()) {
                dojo.addClass('resource_indicator_' + player_id, 'corruption-warning');
                this.addTooltip( 'resource_indicator_' + player_id, dojo.string.substitute(_("Corruption: you will lose ${quantity} resources!"), {quantity: corruptionLoss}), '' );
            } else {
                dojo.removeClass('resource_indicator_' + player_id, 'corruption-warning');
                this.addTooltip( 'resource_indicator_' + player_id, _("Number of available resource units (+ production each turn)"), '' );
            }
        },
        
        getAgeName: function( age )
        {
            if( age == 'A' )
            {   return _('Antiquity');   }
            else if( age == 'I' )
            {   return _('Middle Ages'); }
            else if( age == 'II' )
            {   return _('Age of Exploration'); }
            else if( age == 'III' )
            {   return _('Modern Age'); }
            else if( age == 'IV' )
            {   return _('Last turn'); }
            else
            {   return ''; }
        },
        
        setRemaining: function( age, remaining_cards, remaining_cards_mil )
        {
            this.addTooltip('remaining_civil_cards',
                dojo.string.substitute( _('${age}: ${nbr} civil cards left'), {age: this.getAgeName(age), nbr: remaining_cards}), '');
            dojo.attr('remaining_civil_cards', 'data-value', age);
            $('remaining_civil_cards').innerHTML = remaining_cards;
            
            this.addTooltip('remaining_military_cards',
                dojo.string.substitute( _('${age}: ${nbr} military cards left'), {age: this.getAgeName(age), nbr: remaining_cards_mil}), '');
            dojo.attr('remaining_military_cards', 'data-value', age);
            $('remaining_military_cards').innerHTML = remaining_cards_mil;
        },
        
        showWar: function( id, attacker, defender, card_type )
        {
            var opponent='';
            if( attacker==this.player_id )
            {
                opponent = this.gamedatas.players[ defender ].name;
            }
            else if( defender == this.player_id )
            {
                opponent = this.gamedatas.players[ attacker ].name;
            }
            else
            {
                // Is not part of this war
                return ;
            }
            
            var text = dojo.string.substitute( _("You are at war with ${name}"), {name:opponent} );
            dojo.place(  this.format_block( 'jstpl_warwarning', {
                id:id,
                text: text
            }), 'warwarnings' );
           
            var html = this.getCardTooltip( card_type );
            this.addTooltipHtml( 'warwarning_'+id, html, 0 );
        },
        
        setEventDecks: function( events )
        {
            this.setEventDeck( events.events, 'current_events', _('Current Events deck') );
            this.setEventDeck( events.future_events, 'future_events', _('Future Events deck') );
        },
        setEventDeck: function(compo, target, tooltipText)
        {
            var txt = '';
            var topCard = this.gamedatas.age;
            var total = toint(compo.A) + toint(compo.I) + toint(compo.II) + toint(compo.III);
            
            if (toint(compo.III) > 0) {
              txt = ' &bull; '+compo.III+' '+_("from Age III");
              topCard = 'III';
            }
            if (toint(compo.II) > 0) {
              txt = ' &bull; '+compo.II+' '+_("from Age II")+txt;
              topCard = 'II';
            }
            if (toint(compo.I) > 0) {
              txt = ' &bull; '+compo.I+' '+_("from Age I")+txt;
              topCard = 'I';
            }
            if (toint(compo.A) > 0) {
              txt = ' &bull; '+compo.A+' '+_("from Age A")+txt;
              topCard = 'A';
            }
            
            this.addTooltip(target, tooltipText + ":" + txt.substr(7), '');
            dojo.attr(target, 'data-value', topCard);
            $(target).innerHTML = total;
        },

        displayCardRowCost: function (card) {
            if (card.costToPick === undefined) {
                card.costToPick = card.location_arg <= 5 ? 1 : card.location_arg <= 9 ? 2 : 3;
            }
            dojo.removeClass('card_row_cost_' + card.location_arg, 'hidden');
            dojo.attr('card_row_cost_' + card.location_arg, 'data-value', card.costToPick);
            var tooltip;
            if (isNaN(card.costToPick)) {
                switch (this.gamedatas.card_types[card.type].category) {
                    case 'Leader':
                        tooltip = _("You cannot take a another leader from that age.");
                        break;
                    case 'Wonder':
                        tooltip = _("You cannot take a wonder if you currently have an unfinished wonder in play.");
                        break;
                    default:
                        tooltip = _("You cannot picked twice the same technology.");
                        break;
                }
            } else if (card.costToPick === 0) {
                tooltip = _("You can pick this card for free!");
            } else {
                tooltip = dojo.string.substitute( _("This card costs ${quantity} civil actions to pick."), {quantity: card.costToPick});
                if (this.gamedatas.card_types[card.type].category == 'Wonder') {
                    tooltip += " " + _("Wonders cost one extra civil action for each wonder you have completed.");
                }
            }
            this.addTooltip('card_row_cost_' + card.location_arg, tooltip, '' );
        },

        removeCardRowCost: function (place) {
            dojo.addClass('card_row_cost_' + place, 'hidden');
        },

        proposeRevolution: function (card) {
            this.futureGovt = card;
            this.previousTitleHTML = dojo.byId("pagemaintitletext").innerHTML;
            dojo.byId("pagemaintitletext").innerHTML = dojo.string.substitute(_("${you} must choose:"), {you: '<span id="pagemaintitletext"><span style="font-weight:bold;color:#' + this.gamedatas.players[this.player_id].color + ';">' + _('You') + '</span>'});
            dojo.query("#generalactions a").forEach(function(actionButton) {
                dojo.setStyle(actionButton, "display", "none");
            });
            this.addActionButton( 'revolution_button', _('Revolution'), 'onProposeRevolution' );
            this.addActionButton( 'peacefulChange_button', _('Peaceful Change'), 'onProposeRevolution' );
            this.addActionButton( 'changeGovtCancel', _('Do nothing'), 'onCancelGovtChange' );
        },

        onCancelGovtChange: function () {
            dojo.byId("pagemaintitletext").innerHTML = this.previousTitleHTML;
            dojo.destroy('revolution_button');
            dojo.destroy('peacefulChange_button');
            dojo.destroy('changeGovtCancel');
            dojo.query("#generalactions a").forEach(function(actionButton) {
                dojo.setStyle(actionButton, "display", "inline-block");
            });
        },

        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
        
        onPickCard: function( evt )
        {
            dojo.stopEvent( evt );
            if( ! this.checkAction( 'pickCard' ) )
            {   return; }
            
            // card_<id>
            var card_id = evt.currentTarget.id.substr( 5 );

            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/pickCard.html", {
                                                                    lock: true,
                                                                    id: card_id
                                                                 }, this, function( result ) {}, function( is_error) {} );

        },
        
        onIncreasePopulation: function()
        {
            if( ! this.checkAction( 'increasePopulation' ) )
            {   return; }

            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/increasePopulation.html", { lock: true}, this, function( result ) {}, function( is_error) {} );
        
        },
        
        onUndo: function()
        {
            if( ! this.checkAction( 'undo' ) )
            {   return; }
            
            this.confirmationDialog( _('This will cancel all your moves from the beginning of your turn. Are you sure?'), dojo.hitch( this, function() {
                    this.ajaxcall( '/throughtheagesmobilereadability/throughtheagesmobilereadability/undo.html', { lock: true }, this, function( result ) {} );
                } ) );
        },

        
        onPass: function()
        {
            if( ! this.checkAction( 'pass' ) )
            {   return; }

            var warnings = [];
            // Check remaining civil actions
            if (toint(this.remainingCivil) > 0) {
                warnings.push(_('You have some remaining civil actions, are you sure?'));
            } else if (this.hammurabi && this.remainingMilitary > 0) {
                warnings.push(_('You may still use a military action as a civil action using Hammurabi'));
            }
            if (this.hasUprising(this.player_id)) {
                warnings.push(_('You are going to face an uprising'));
            } else {
                var hungerLoss = this.getHungerLoss(this.player_id);
                if (hungerLoss > 0) {
                    warnings.push(dojo.string.substitute(_('You are going to lose ${quantity} points of culture because your population is hungry'), {quantity: hungerLoss}));
                }
                var corruptionLoss = this.getCorruptionLoss(this.player_id);
                if (corruptionLoss > 0) {
                    warnings.push(dojo.string.substitute(_('You are going to lose ${quantity} resources because of corruption'), {quantity: corruptionLoss}));
                }
            }
            if (warnings.length) {
                this.confirmationDialog(warnings.join('<br/>'), dojo.hitch( this, this.doPass));
            } else {
                this.doPass();
            }
        },

        doPass: function()
        {
            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/pass.html", { lock: true}, this, function( result ) {}, function( is_error) {} );
        },

        onDoNothing: function()
        {
            if( ! this.checkAction( 'donothing' ) )
            {   return; }

            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/donothing.html", { lock: true}, this, function( result ) {}, function( is_error) {} );
        },

        onCancel: function()
        {
            if( ! this.checkAction( 'cancel' ) )
            {   return; }

            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/cancel.html", { lock: true}, this, function( result ) {}, function( is_error) {} );
        },
        onDoNotUseEffect: function()
        {
            if( ! this.checkAction( 'doNotUseEffect' ) )
            {   return; }

            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/doNotUseEffect.html", { lock: true}, this, function( result ) {}, function( is_error) {} );
        },
        
        onPlayerHandSelection: function()
        {
            if( this.checkAction( 'discardMilitaryCards', true ) )
            {
                // Can select multiple
            }
            else
            {
                var selected = this.playerHand.getSelectedItems();
                if( selected.length == 1 )
                {
                    var card_id = selected[0].id;
                    var card_type = selected[0].type;

                    if( this.checkAction( 'playCard', true ) )
                    {
                        // Play a civil card
                        if (this.gamedatas.card_types[card_type]['type'] === 'Govt') {
                            // Peaceful change or Revolution?
                            this.proposeRevolution(selected[0]);
                        } else {
                            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/playCard.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                        }
                    }
                    else if( this.checkAction( 'sacrifice', true ) )
                    {
                        // Use a bonus card
                        this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/useBonus.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                    }
                    else if( this.checkAction( 'politicAction' ) )
                    {
                        // Play a military card for a polical action

                        // If this is an aggression/war/pact, we must choose the target player
                        if( this.gamedatas.card_types[ card_type ].category == 'Aggression'
                         || this.gamedatas.card_types[ card_type ].category == 'War'
                         || this.gamedatas.card_types[ card_type ].category == 'Pact' )
                        {
                            if( $('targetChoiceDlg' ) )
                            {
                                dojo.destroy( 'targetChoiceDlg' );
                            }

                            this.targetChoiceDlg = new ebg.popindialog();
                            this.targetChoiceDlg.create( 'targetChoiceDlg' );
                            this.targetChoiceDlg.setTitle( _("Choose the target player") );

                            var html = '<div id="targetChoiceDlg">'+_("Choose the target player")+':<br/><ul>';
                            var nbr_choice = 0;
                            var last_choice = 0;
                            var i_am_a = true;
                            
                            for( var player_id in this.gamedatas.players )
                            {
                                if( player_id != this.player_id )
                                {
                                    var player = this.gamedatas.players[ player_id ];
                                    
                                    if( this.gamedatas.card_types[ card_type ].category != 'Pact' )
                                    {
                                        html += '<li ><a href="#" class="playerchoice" id="chooseplayer_'+player_id+'">'+player.name+'</a></li>';
                                        nbr_choice++;
                                    }
                                    else
                                    {
                                        html += '<li ><a href="#" class="playerchoice" id="Ahooseplayer_'+player_id+'">'+player.name+' ('+_("as B, and I as A")+')</a></li>';
                                        html += '<li ><a href="#" class="playerchoice" id="Bhooseplayer_'+player_id+'">'+player.name+' ('+_("as A, and I as B")+')</a></li>';
                                        nbr_choice += 2;
                                    }
                                                                    
                                    last_choice=player_id;
                                }
                            }
                            html += '</ul><br/>';
                            
                            html += '<a class="bgabutton bgabutton_blue" id="btn_cancel"><span>'+_('Cancel')+'</span></a>';
                            html += "</div>";

                            if( nbr_choice == 1 )
                            {
                                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/playMilitaryCard.html", { lock: true, card_id: card_id, target: last_choice }, this, function( result ) {}, function( is_error) {} );
                            }
                            else
                            {
                                this.targetChoiceDlg.setContent( html );
                                this.targetChoiceDlg.show();

                                dojo.connect( $('btn_cancel'), 'onclick', this, function(evt){
                                    evt.preventDefault();
                                    this.targetChoiceDlg.destroy();
                                } );
                                
                                dojo.query( '.playerchoice' ).connect( 'onclick', this, dojo.hitch( this, function( evt ){
                                    evt.preventDefault();

                                    var player_id = evt.currentTarget.id.substr( 13 );
                                    
                                    var me_as_a = false;

                                    if( evt.currentTarget.id.substr( 0,1 ) == 'A' )
                                    {
                                        me_as_a = true;
                                    }
                                    
                                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/playMilitaryCard.html", { lock: true, card_id: card_id, target: player_id, me_as_a:me_as_a }, this, function( result ) {}, function( is_error) {} );

                                    this.targetChoiceDlg.destroy();
                                } ));
                            }
                        }
                        else
                        {
                            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/playMilitaryCard.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                        }
                    }

                    
                    this.playerHand.unselectAll();
                }
            }
        },
        
        onPlayerTableauSelection: function()
        {
            var selected = this.playerTableau[ this.player_id ].getSelectedItems();
            if( selected.length == 1 )
            {
                var card_id = selected[0].id;

                if( this.checkAction( 'build', true) && !this.checkAction( 'buildChoice', true) )
                {
                    // Build a new building
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/build.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'politicAction', true ) )
                {
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/politicActionActive.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'sacrifice', true ) )
                {
                    // Sacrifice a unit
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/sacrifice.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'lossBuilding', true ) )
                {
                    // Destroy a building
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/lossBuilding.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'lossPopulation', true ) )
                {
                    // Population loss
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/lossPopulation.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'lossBlueToken', true ) )
                {
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/lossBlueToken.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'lossYellowToken', true ) )
                {
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/lossYellowToken.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'chooseCard', true ) )
                {
                    // Ravages of time, Homer, Declaration of independence...
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/chooseCard.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                else if( this.checkAction( 'upgradeChoice' ) )
                {
                    // Upgrade a mine, farm or urban building
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/upgradeChoices.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                
                
                this.playerTableau[ this.player_id ].unselectAll();
            }
        },
        
        onLoseUnused: function()
        {
            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/lossYellowToken.html", { lock: true, card_id: 0 }, this, function( result ) {}, function( is_error) {} );
        },
        
        onOpponentTableauSelection: function( evt )
        {
            // player_tableau_<player_id>
            var opponent_id = evt.substr( 15 );
        
            var selected = this.playerTableau[ opponent_id ].getSelectedItems();
            if( selected.length == 1 )
            {
                var card_id = selected[0].id;

                if( this.checkAction( 'chooseOpponentTableauCard' ) )
                {
                    // Build a new building
                    this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/chooseOpponentTableauCard.html", { lock: true, card_id: card_id }, this, function( result ) {}, function( is_error) {} );
                }
                
                this.playerTableau[ opponent_id ].unselectAll();
            }
        },

        onDualChoice: function( evt )
        {
            if( this.checkAction( 'dualChoice' ) )
            {
                // dualChoice<id>
                var choice = evt.currentTarget.id.substr( 10 );
                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/dualChoice.html", { choice: choice, lock: true }, this, function( result ) {
                } );
            }
        },
        
        onBuildChoice: function( evt )
        {
            if( this.checkAction( 'buildChoice' ) )
            {
                // buildChoice<id>
                var choice = evt.currentTarget.id.substr( 11, 1 );
                
                var from = 0;
                if( choice == 2 )
                {
                    from = evt.currentTarget.id.substr( 12 );
                }
                
                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/buildChoice.html", { choice: choice, from:from, lock: true }, this, function( result ) {
                } );
            }
        },
        
        onBidTerritory: function( evt )
        {
            if( this.checkAction( 'bidTerritory' ) )
            {
                // bidTerritory<id>
                var bid = evt.currentTarget.id.substr( 12 );
                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/bidTerritory.html", { bid: bid, lock: true }, this, function( result ) {
                } );
            }
        },
        
        onDiscard: function()
        {
            if( this.checkAction( 'discardMilitaryCards' ) )
            {
                var selected = this.playerHand.getSelectedItems();

                var id_string = '';
                for( var i in selected )
                {
                    id_string += selected[i].id+';';
                }

                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/discardMilitaryCards.html", { cards: id_string, lock: true }, this, function( result ) {
                } );

                
            }
        },
        
        onProposeRevolution: function( evt )
        {
            if( this.checkAction( 'playCard' ) )
            {
                var choice = evt.currentTarget.id.split('_')[0];
                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/playCard.html", { lock: true, card_id: this.futureGovt['id'], 'specialMode': choice }, this, function( result ) {}, function( is_error) {} );
            }
        },
        
        onAcceptPact: function( evt )
        {
            if( this.checkAction( 'acceptPact' ) )
            {
                // acceptPact<id>
                var choice = evt.currentTarget.id.substr( 10 );
                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/acceptPact.html", { choice: choice, lock: true }, this, function( result ) {
                } );
            }
        },
        
        onWonderForFree: function( evt )
        {
            if( this.checkAction( 'wonderForFree' ) )
            {
                this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/wonderForFree.html", { lock: true }, this, function( result ) {
                } );
            }
        },
        
        onAggressionChooseOpponent: function( evt )
        {
            // aggressionChooseOpponent<id>
            var player_id = evt.currentTarget.id.substr( 24 );
            this.ajaxcall( "/throughtheagesmobilereadability/throughtheagesmobilereadability/aggressionChooseOpponent.html", { lock: true, player_id:player_id }, this, function( result ) {
            } );
        },

        onSelectCommonTactic: function () {
            var selected = this.commonTactics.getSelectedItems();
            if (selected.length === 1) {
              if (this.checkAction('copyTactic')) {
                    this.ajaxcall("/throughtheagesmobilereadability/throughtheagesmobilereadability/copyTactic.html", {
                        lock: true,
                        card_id: selected[0].id
                    }, this, function (result) {
                    }, function (is_error) {
                    });
                }
                this.commonTactics.unselectAll();
            }
        },

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your throughtheagesmobilereadability.game.php file.
        
        */
        setupNotifications: function()
        {
            dojo.subscribe( 'pickCard', this, "notif_pickCard" );
            dojo.subscribe( 'pickCardsSecret', this, "notif_pickCardsSecret" );
            dojo.subscribe( 'scoreProgress', this, "notif_scoreProgress" );
            dojo.subscribe( 'card_row_remove', this, "notif_card_row_remove" );
            dojo.subscribe( 'card_row_update', this, "notif_card_row_update" );
            dojo.subscribe( 'card_row_costs_update', this, "notif_card_row_costs_update" );
            dojo.subscribe( 'moveTokens', this, "notif_moveTokens" );
            dojo.subscribe( 'adjustIndicators', this, "notif_adjustIndicators" );
            dojo.subscribe( 'discardFromTableau', this, "notif_discardFromTableau" );
            dojo.subscribe( 'playCard', this, "notif_playCard" );
            dojo.subscribe( 'copyTactic', this, "notif_copyTactic" );
            dojo.subscribe( 'tacticMadeAvailable', this, "notif_tacticMadeAvailable" );
            dojo.subscribe( 'slideCardUnder', this, "notif_slideCardUnder" );
            dojo.subscribe( 'removeFromHand', this, "notif_removeFromHand" );
            dojo.subscribe( 'discardFromHand', this, "notif_discardFromHand" );
            dojo.subscribe( 'pickCardToTableau', this, "notif_pickCardToTableau" );
            dojo.subscribe( 'updateSciencePoints', this, "notif_updateSciencePoints" );
            dojo.subscribe( 'newAge', this, "notif_newAge" );
            dojo.subscribe( 'lastTurn', this, "notif_lastTurn" );
            dojo.subscribe( 'revealEvent', this, "notif_revealEvent" );
            dojo.subscribe( 'revealTerritory', this, "notif_revealTerritory" );
            dojo.subscribe( 'war', this, "notif_war" );
            dojo.subscribe( 'warOver', this, "notif_warOver" );
            dojo.subscribe( 'ravage', this, "notif_ravage" );
            dojo.subscribe( 'finalScoring', this, "notif_finalScoring" );
            

            dojo.subscribe( 'buildWonder', this, "notif_buildWonder" );
            dojo.subscribe( 'bidTerritory', this, "notif_bidTerritory" );
            
            dojo.subscribe( 'endGameBegins', this, "notif_endGameBegins" );
            dojo.subscribe( 'eventDeckChange', this, "notif_eventDeckChange" );
            dojo.subscribe( 'updateCardsInHand', this, "notif_updateCardsInHand" );
            dojo.subscribe( 'updateCivilCardsInHand', this, "notif_updateCivilCardsInHand" ); // Clevus
            dojo.subscribe( 'concedeGame', this, "notif_concedeGame" );

        },
        
        
        notif_pickCard: function( notif )
        {
            this.removeCardRowCost(notif.args.card.location_arg);

            if( notif.args.player_id == this.player_id )
            {
                if( $( 'card_'+notif.args.card.id ) )
                {
                    // Move to hand
                    this.playerHand.addToStockWithId( notif.args.card.type, notif.args.card.id, 'card_'+notif.args.card.id );
                    dojo.destroy( 'card_'+notif.args.card.id );
                }
                else
                {
                    this.playerHand.addToStockWithId( notif.args.card.type, notif.args.card.id );
                }
            }
            else
            {
                if( $( 'card_'+notif.args.card.id ) )
                {
                    // Slide to player panel
                    var anim = this.slideToObject( 'card_'+notif.args.card.id, 'overall_player_board_'+notif.args.player_id );
                    dojo.connect( anim, 'onEnd', dojo.destroy );
                    anim.play();
                }
            }
        },
        
        notif_pickCardsSecret: function( notif )
        {
            for( var i in notif.args.cards )
            {
                this.playerHand.addToStockWithId( notif.args.cards[i].type, notif.args.cards[i].id );
            }
        },
        
        notif_playCard: function( notif )
        {
            if( notif.args.player_id == this.player_id )
            {
                if( $('player_hand_item_'+notif.args.card.id) )
                {
                    // Move from hand to tableau
                    this.placeCardOnTableau( notif.args.card, notif.args.player_id, 'player_hand_item_'+notif.args.card.id );
                    this.playerHand.removeFromStockById( notif.args.card.id );
                }
                else
                {
                    this.placeCardOnTableau( notif.args.card, notif.args.player_id );
                }
            }
            else
            {
                // Slide from player panel
                this.placeCardOnTableau( notif.args.card, notif.args.player_id, 'overall_player_board_'+notif.args.player_id );
            }
        },

        notif_copyTactic: function (notif)
        {
            this.placeCardOnTableau( notif.args.card, notif.args.player_id, 'common_tactics_item_'+notif.args.card.id );
        },

        notif_tacticMadeAvailable: function (notif)
        {
            this.commonTactics.addToStockWithId(notif.args.card.type, notif.args.card.id);
        },

        notif_slideCardUnder: function( notif )
        {
            this.gamedatas.under_card = [notif.args.card];
            this.slideCardUnder(notif.args.card, notif.args.player_id);
        },

        notif_pickCardToTableau: function( notif )
        {
            // Note: card goes directly from card row to tableau
            this.placeCardOnTableau( notif.args.card, notif.args.player_id, 'card_'+notif.args.card.id );
            dojo.destroy( 'card_'+notif.args.card.id );
            this.removeCardRowCost(notif.args.card.location_arg);

            // If this is a Wonder => mark it as "in building"
            if( toint( notif.args.card.type_arg ) == 1 )
            {
                dojo.style( 'cardcontentmask_'+notif.args.card.id, 'display', 'block' );
            }
        },
        
        notif_scoreProgress: function( notif )
        {
            var culture = this.scoreCtrl[ notif.args.player_id ].incValue( notif.args.culture );
            if( culture < 0 ) {
                culture = 0
                this.scoreCtrl[ notif.args.player_id ].toValue( 0 );
            }
            $('culture_points_' + notif.args.player_id).innerHTML = culture;

            var science = toint($('science_points_' + notif.args.player_id).innerHTML) + toint(notif.args.science)
            $('science_points_' + notif.args.player_id).innerHTML = science;

        },

        notif_card_row_remove: function( notif )
        {
            // Remove cards from card_row
            for( var i in notif.args.card_ids )
            {
                var card_id = notif.args.card_ids[ i ];
                this.fadeOutAndDestroy( 'card_'+card_id );
            }
        },

        notif_card_row_update: function (notif) {
            for (var j in notif.args.cards) {
                this.placeCardOnCardRow(notif.args.cards[j]);
            }

            this.setRemaining(notif.args.age, notif.args.remaining, notif.args.remainingMil);
        },

        notif_card_row_costs_update: function (notif) {
            for (var i = 1; i <= 13; i++) {
                this.removeCardRowCost(i);
            }
            for (var j = 0; j < notif.args.length; j++) {
                this.displayCardRowCost(notif.args[j]);
            }
        },

        notif_moveTokens: function( notif )
        {
            for( var i in notif.args.tokens )
            {
                var token = notif.args.tokens[i];
                this.moveToken( token.card_id, token.player, token.id, token.type  );
            }
        },
        notif_adjustIndicators: function( notif )
        {
            $('culture_'+notif.args.player_id).innerHTML = notif.args.indicators.culture;
            $('science_'+notif.args.player_id).innerHTML = notif.args.indicators.science;
            $('food_prod_'+notif.args.player_id).innerHTML = notif.args.indicators.foodProduction;
            $('resource_prod_'+notif.args.player_id).innerHTML = notif.args.indicators.resourceProduction;
            $('strength_'+notif.args.player_id).innerHTML = notif.args.indicators.strength;
            $('colonization_'+notif.args.player_id).innerHTML = notif.args.indicators.colonizationModifier;
            $('happy_'+notif.args.player_id).innerHTML = notif.args.indicators.happy;
            $('unhappy_'+notif.args.player_id).innerHTML = notif.args.indicators.discontent;
            
            this.updateHappyWarning( notif.args.player_id );
            this.updateHungerWarning( notif.args.player_id );
            this.updateCorruptionWarning( notif.args.player_id );
        },

        notif_discardFromTableau: function( notif )
        {
            this.playerTableau[notif.args.player_id].removeFromStockById( notif.args.card_id );
        },
        notif_removeFromHand: function( notif )
        {
            if( notif.args.player_id == this.player_id )
            {
                this.playerHand.removeFromStockById( notif.args.card.id );
            }
        },
        notif_discardFromHand: function( notif )
        {
            this.playerHand.removeFromStockById( notif.args.card_id );
        },
        notif_updateSciencePoints: function( notif )
        {
            $('science_points_'+notif.args.player_id).innerHTML = notif.args.points;
        },
        notif_newAge: function( notif )
        {
            this.showMessage( dojo.string.substitute( _('${age} begins!'), { age: this.getAgeName( notif.args.age_char ) } ), 'info' );
        },
        notif_endGameBegins: function( notif )
        {
            this.showMessage( _('No more cards: the end of game begins!'), 'info' );
        },
        notif_lastTurn: function( notif )
        {
            this.showMessage( _('This is the last turn!'), 'info' );
        },
        
        notif_revealEvent: function( notif )
        {
            // Show a dialog with the new event card

            if( $('eventDlg' ) )
            {
                dojo.destroy( 'eventDlg' );
            }

            this.eventDlg = new ebg.popindialog();
            this.eventDlg.create( 'eventDlg' );
            this.eventDlg.setTitle( notif.args.event_name );
            this.eventDlg.setMaxWidth( 500 );
            var card_type_id = toint( notif.args.card.type );
            var artx=197 * ( ( toint( card_type_id )-1 )%10 );
            var arty=287 * ( Math.floor( ( toint( card_type_id )-1 )/10 ) );

            var html = this.format_block( 'jstpl_eventDlg', {
                artx:artx,
                arty:arty,
                close_label: _('Close'),
                card_text: _( this.gamedatas.card_types[ card_type_id ].text )
            } );

            this.eventDlg.setContent( html );
            this.eventDlg.show();

            dojo.connect( $('closeDlg'), 'onclick', this, function(evt){
                evt.preventDefault();
                this.eventDlg.destroy();
            } );
            
        },
        notif_revealTerritory: function( notif )
        {
        },
        notif_buildWonder: function( notif )
        {
            dojo.style( 'cardcontentmask_'+notif.args.card_id, 'display', 'none' );
        },
        notif_bidTerritory: function( notif )
        {
        },

        notif_war: function( notif )
        {
            this.showWar( notif.args.card_id, notif.args.player_id, notif.args.target_id, notif.args.card_type );
        },
        notif_warOver: function( notif )
        {
            if( $('warwarning_'+notif.args.id) )
            {
                dojo.destroy( 'warwarning_'+notif.args.id );
            }
        },

        notif_ravage: function (notif) {
            var player_id = notif.args.player_id;
            var card = notif.args.card;
            dojo.addClass('player_tableau_' + player_id + '_item_' + card.id, 'ravaged');
            dojo.addClass('player_tableau_' + player_id + '_item_' + card.id, 'age' + this.gamedatas.card_types[card.type]['age']);
        },

        notif_eventDeckChange: function( notif )
        {
            this.setEventDecks( notif.args.decks );
        },
        notif_finalScoring: function( notif )
        {
            // Filling player's hand
            dojo.style( 'final_scoring_upper_wrap', 'display', 'block' );
            for( var i in notif.args.cards )
            {
                var card = notif.args.cards[i];
                this.finalScoring.addToStockWithId( card.type, card.id );
                this.finalScoringUpper.addToStockWithId( card.type, card.id );
            }
        },
        notif_updateCardsInHand: function( notif )
        {
            this.updateCardsInHand( notif.args.cards_in_hand );
        },
        // Clevus
        notif_updateCivilCardsInHand: function( notif )
        {
            this.updateCivilCardsInHand( notif.args.civil_cards_in_hand );
        },
        notif_concedeGame: function (notif)
        {
            if (notif.args.remainingPlayers === 3) {
                dojo.setStyle("card_row_cross_3", "display", "block");
            } else if (notif.args.remainingPlayers === 2) {
                dojo.setStyle("card_row_cross_2", "display", "block");
            }
        }
   });
});
