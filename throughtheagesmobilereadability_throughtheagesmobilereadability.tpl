{OVERALL_GAME_HEADER}

<div id="bidTerritory" class="whiteblock">
    <h3>{TERRITORY_TO_COLONIZE}:</h3>
    <div id="territory" class="cardart"></div>
    <div id="territory_playersbid">
        <!-- BEGIN playerbid -->
        <p>
            <span style="color:#{PLAYER_COLOR}">{PLAYER_NAME}</span>: <span id="playerbid_{PLAYER_ID}">-</span>
        </p>
        <!-- END playerbid -->
    </div>
    <div class="clear"></div>
</div>


<div id="aggressionInProgress" class="whiteblock">
    <div id="aggression" class="cardart"></div>
    <div class="clear"></div>
</div>

<div id="warwarnings">
</div>

<div class="whiteblock" id="final_scoring_upper_wrap" style="display:none">
    <h3>{FINAL_SCORING}</h3>
    <div id="final_scoring_upper">
    </div>
</div>

<div id="card_row">
    <div id="card_row_1" class="card_row_part">
        <div class="card_row_cross card_row_cross_4" id="card_row_cross_4">✗</div>
        <div class="card_row_cross card_row_cross_3" id="card_row_cross_3" style="display:{NB_3_PLAYERS_VISIBILITY}">✗</div>
        <div class="card_row_cross card_row_cross_2" id="card_row_cross_2" style="display:{NB_2_PLAYERS_VISIBILITY}">✗</div>

        <div id="card_row_place_1" class="card_row_place">
            <span id="card_row_cost_1" class="card_row_cost hidden" data-value="1"></span>
        </div>
        <div id="card_row_place_2" class="card_row_place">
            <span id="card_row_cost_2" class="card_row_cost hidden" data-value="1"></span>
        </div>
        <div id="card_row_place_3" class="card_row_place">
            <span id="card_row_cost_3" class="card_row_cost hidden" data-value="1"></span>
        </div>
        <div id="card_row_place_4" class="card_row_place">
            <span id="card_row_cost_4" class="card_row_cost hidden" data-value="1"></span>
        </div>
        <div id="card_row_place_5" class="card_row_place">
            <span id="card_row_cost_5" class="card_row_cost hidden" data-value="1"></span>
        </div>
    </div>
    <div id="card_row_2" class="card_row_part">
        <div id="card_row_place_6" class="card_row_place">
            <span id="card_row_cost_6" class="card_row_cost hidden" data-value="2"></span>
        </div>
        <div id="card_row_place_7" class="card_row_place">
            <span id="card_row_cost_7" class="card_row_cost hidden" data-value="2"></span>
        </div>
        <div id="card_row_place_8" class="card_row_place">
            <span id="card_row_cost_8" class="card_row_cost hidden" data-value="2"></span>
        </div>
        <div id="card_row_place_9" class="card_row_place">
            <span id="card_row_cost_9" class="card_row_cost hidden" data-value="2"></span>
        </div>
    </div>
  <div id="card_row_3" class="card_row_part">
      <div id="card_row_place_10" class="card_row_place">
          <span id="card_row_cost_10" class="card_row_cost hidden" data-value="3"></span>
      </div>
      <div id="card_row_place_11" class="card_row_place">
          <span id="card_row_cost_11" class="card_row_cost hidden" data-value="3"></span>
      </div>
      <div id="card_row_place_12" class="card_row_place">
          <span id="card_row_cost_12" class="card_row_cost hidden" data-value="3"></span>
      </div>
      <div id="card_row_place_13" class="card_row_place">
          <span id="card_row_cost_13" class="card_row_cost hidden" data-value="3"></span>
      </div>
  </div>
</div>

<div id="hand-row">
    <div id="age_events_info" class="whiteblock">
        <div id="remaining_civil_cards" class="card-back civil">?</div>
        <div id="remaining_military_cards" class="card-back military">?</div>
        <div id="future_events" class="card-back military">?</div>
        <div id="current_events" class="card-back military">?</div>
    </div>
    <div id="myhand" class="whiteblock seasons_rightpanel">
        <h3>{MY_HAND}</h3>
        <div id="player_hand">
        </div>
    </div>
</div>

<br class="clear"/>

<!-- BEGIN player -->
    <div class="whiteblock">
        <h3 style="color:#{PLAYER_COLOR}">{PLAYER_NAME}<span class="firstplayernotice">{FIRSTPLAYER}</span></h3>


        <div class="player_cards_up">
            
            <div id="player_stacks_{PLAYER_ID}" class="player_stacks">
                <div id="stack_farm_{PLAYER_ID}" class="stack">
                    <div id="place_farm_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_farm_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_farm_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_farm_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_mine_{PLAYER_ID}" class="stack">
                    <div id="place_mine_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_mine_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_mine_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_mine_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_lab_{PLAYER_ID}" class="stack">
                    <div id="place_lab_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_lab_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_lab_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_lab_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_temple_{PLAYER_ID}" class="stack">
                    <div id="place_temple_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_temple_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_temple_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_temple_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_arena_{PLAYER_ID}" class="stack">
                    <div id="place_arena_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_arena_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_arena_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_arena_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_theater_{PLAYER_ID}" class="stack">
                    <div id="place_theater_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_theater_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_theater_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_theater_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_library_{PLAYER_ID}" class="stack">
                    <div id="place_library_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_library_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_library_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_library_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_infantry_{PLAYER_ID}" class="stack">
                    <div id="place_infantry_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_infantry_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_infantry_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_infantry_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_cavalry_{PLAYER_ID}" class="stack">
                    <div id="place_cavalry_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_cavalry_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_cavalry_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_cavalry_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
                <div id="stack_artillery_{PLAYER_ID}" class="stack">
                    <div id="place_artillery_A_{PLAYER_ID}" class="card_place card_place_A"></div>
                    <div id="place_artillery_I_{PLAYER_ID}" class="card_place card_place_I"></div>
                    <div id="place_artillery_II_{PLAYER_ID}" class="card_place card_place_II"></div>
                    <div id="place_artillery_III_{PLAYER_ID}" class="card_place card_place_III"></div>
                </div>
            
            </div>
            
            <div id="player_tableau_wrap_{PLAYER_ID}" class="player_tableau_wrap">
                <div id="player_tableau_{PLAYER_ID}" class="player_tableau"></div>
            </div>
            
        </div>
        
        <div style="margin-bottom:15px" id="playerboard_{PLAYER_ID}" class="playerboard clear">


            <div id="card_place_govt_{PLAYER_ID}" class="card_place card_place_govt">
            </div>
            <div id="card_place_leader_{PLAYER_ID}" class="card_place card_place_leader">
            </div>


            <div id="playerboard_ress_{PLAYER_ID}" class="playerboard_ress">
                <!-- BEGIN ressplace -->
                <div id="playerboard_{PLAYER_ID}_ressplace_{NO}" class="tokenplace tokenplace_ress_{NO}"></div>
                <!-- END ressplace -->
            </div>
            <div id="playerboard_food_{PLAYER_ID}" class="playerboard_food">
                <div id="happyplace_{PLAYER_ID}_0" class="happyplace happyplace_0"></div>
                <div id="happyplace_{PLAYER_ID}_1" class="happyplace happyplace_1"></div>
                <div id="happyplace_{PLAYER_ID}_2" class="happyplace happyplace_2"></div>
                <div id="happyplace_{PLAYER_ID}_3" class="happyplace happyplace_3"></div>
                <div id="happyplace_{PLAYER_ID}_4" class="happyplace happyplace_4"></div>
                <div id="happyplace_{PLAYER_ID}_5" class="happyplace happyplace_5"></div>
                <div id="happyplace_{PLAYER_ID}_6" class="happyplace happyplace_6"></div>
                <div id="happyplace_{PLAYER_ID}_7" class="happyplace happyplace_7"></div>
                <div id="happyplace_{PLAYER_ID}_8" class="happyplace happyplace_8"></div>
                <!-- BEGIN foodplace -->
                <div id="playerboard_{PLAYER_ID}_foodplace_{NO}" class="tokenplace tokenplace_food_{NO}"></div>
                <!-- END foodplace -->
            </div>
        </div>
        
        <div id="civilhand_{PLAYER_ID}" class="civilhand">
        </div>

    </div>
<!-- END player -->

<div class="whiteblock">
    <h3>{COMMON_TACTICS_AREA}</h3>
    <div id="common_tactics">
    </div>
</div>

<div class="whiteblock" id="final_scoring_wrap" style="display:{FINAL_SCORING_VISIBILITY}">
    <h3>{FINAL_SCORING}</h3>
    <div id="final_scoring">
    </div>
</div>

<script type="text/javascript">

// Javascript HTML templates

var jstpl_player_board = '\
    <div class="tta_board" />\
        <div class="boardblock">\
            <div class="nolinebreak"><div class="tta_icon culture ttculture">\
                <span id="culture_points_${id}">${score}</span>\
                <span class="production">+<span id="culture_${id}">${culture}</span></span>\
            </div></div>\
            <div class="nolinebreak"><div class="tta_icon science ttscience">\
                <span id="science_points_${id}">${science_points}</span>\
                <span class="production">+<span id="science_${id}">${science}</span></span>\
            </div></div>\
            <div class="nolinebreak"><div id="food_indicator_${id}" class="tta_icon food ttfood">\
                <span id="food_${id}">0</span>\
                <span class="production"><span id="food_prod_${id}">${foodProduction}</span></span>\
            </div></div>\
            <div class="nolinebreak"><div id="resource_indicator_${id}" class="tta_icon resource ttresource">\
                <span id="resource_${id}">0</span>\
                <span class="production"><span id="resource_prod_${id}">${resourceProduction}</span></span>\
            </div></div>\
        </div>\
        <div class="boardblock">\
            <div class="nolinebreak"><div class="tta_icon strength ttstrength"><span id="strength_${id}">${strength}</span></div></div>\
            <div class="nolinebreak"><div class="tta_icon colonization ttcolonization"><span id="colonization_${id}">${colonizationModifier}</span></div></div>\
            <div class="nolinebreak"><div class="tta_icon happy tthappy"><span id="happy_${id}">${happy}</span></div></div>\
            <div class="nolinebreak"><div class="tta_icon unhappy ttunhappy"><span id="unhappy_${id}">${discontent}</span></div></div>\
        </div>\
        <div class="boardblock workerpool_wrapper ttworkerpool">\
            <div id="workerpool_${id}"></div>\
        </div>\
        <div class="boardblock">\
            <div id="minihand_${id}" class="minihand"></div>\
        </div>\
    </div>';

var jstpl_player_board_temp = '\
    <div class="tta_board" />\
        <div class="boardblock">\
            <div class="nolinebreak"><div class="tta_icon culture ttculture" id="ttculture${id}"></div> <span id="culture_${id}" class="ttculture">${culture}</span></div>\
            <div class="nolinebreak"><div class="tta_icon science_points ttscience_points" id="ttscience_points${id}"></div> <span id="science_points_${id}" class="ttscience_points">${science_points}</span></div>\
            <div class="nolinebreak"><div class="tta_icon science ttscience" id="ttscience${id}"></div> <span id="science_${id}" class="ttscience">${science}</span></div>\
            <div class="nolinebreak"><div class="tta_icon happy tthappy" id="tthappy${id}"></div> <span id="happy_${id}" class="tthappy">${happy}</span></div>\
            <div class="nolinebreak"><div class="tta_icon strength ttstrength" id="ttstrength${id}"></div> <span id="strength_${id}" class="ttstrength">${strength}</span></div>\
        </div>\
        <div class="boardblock">\
            <div class="nolinebreak"><div class="tta_icon food ttfood" id="ttfood_${id}"></div> <span id="food_${id}" class="ttfood">0</span></div>\
            <div class="nolinebreak"><div class="tta_icon resource ttresource" id="ttresource_${id}"></div> <span id="resource_${id}" class="ttresource">0</span></div>\
            <div class="nolinebreak"><div class="tta_icon workerpool ttworkerpool" id="ttworkerpool${id}"></div> <span id="workerpool_${id}" class="ttworkerpool">0</span></div>\
            <div class="nolinebreak"><div class="tta_icon unhappy ttunhappy" id="ttunhappy${id}"></div> <span id="unhappy_${id}" class="ttunhappy">${discontent}</span></div>\
        </div>\
        <div class="boardblock">\
            <div id="minihand_${id}" class="minihand"></div>\
        </div>\
    </div>';

var jstpl_card = '<div id="card_${id}" class="card" style="background-position: -${backx}% -${backy}%;"></div>';

var jstpl_tableaucard_content = '<div id="cardcontent_${id}"><div id="cardcontentmask_${id}" class="cardcontentmask"></div><div id="yellowzone_${id}" class="yellowzone"></div><div id="bluezone_${id}" class="bluezone"></div><div id="pactmarker_${id}" class="pactmarker"></div><a id="pactcancel_${id}" class="pactcancel" href="#">${cancel}</a><a id="activecard_${id}" class="activecard" href="#">${activate}</a></div>';

var jstpl_token = '<div id="token_${id}" class="mtoken mtoken_${type}"></div>';

var jstpl_card_tooltip = '<div class="cardtooltip">\
                            <h3>${name}</h3>\
                            <hr/>\
                            <b>${category}</b>\<br/>\
                            <div style="display:${techcost_visibility}">${techcost_label}: ${techcost}<div class="tta_smallicon smallscience imgtext"></div></div>\
                            <div style="display:${resscost_visibility}">${resscost_label}: ${resscost} <div class="tta_smallicon smallress imgtext"></div></div>\
                            ${text}\
                            <div class="cardartwrap"><div class="cardart" style="background-position: -${artx}px -${arty}px;"></div></div>\
                            <i>${age} &bull; ${cards_in_play}: ${nbr_cards}</i>\
                          </div>';



var jstpl_boardtoken='<div id="boardtoken_${player_id}_${type}_${no}" class="token boardtoken token_${type}"></div>';

var jstpl_eventDlg = '<div id="eventDlg">\
            <p>${card_text}</p>\
           <div class="cardartwrap"><div class="cardart" style="background-position: -${artx}px -${arty}px;"></div></div>\
            <a href="#" id="closeDlg" class="bgabutton bgabutton_blue"><span>${close_label}</span></a>&nbsp;\
        </div>';

var jstpl_warwarning = '<div class="warwarning" id="warwarning_${id}">${text}</div>';

</script>

{OVERALL_GAME_FOOTER}
