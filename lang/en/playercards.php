<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for PlayerCards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activatelore'] = 'Activate';
$string['addlorecard'] = 'Add Lore card';
$string['addquestion'] = 'Add question';
$string['aicorrectresult'] = 'The AI answered correctly and gained {$a} life points.';
$string['aidifficultydefault'] = 'Default AI difficulty';
$string['aidifficultydefault_help'] = 'Difficulty the student can choose when starting a match against the AI (easy, normal or hard). This sets the option pre-selected by default.';
$string['aieventattack'] = 'AI attacked your {$a->target} with {$a->attacker}.';
$string['aieventattackdirect'] = 'AI attacked directly with {$a->attacker}, dealing {$a->damage} damage.';
$string['aieventdamagedealt'] = 'Dealt {$a} damage.';
$string['aieventmuster'] = 'AI mustered {$a}.';
$string['aieventtargetdestroyed'] = 'Your Guardian was destroyed.';
$string['aiturn'] = 'AI\'s turn';
$string['answerlines'] = 'Answer options';
$string['answerlines_help'] = 'One option per line. Mark the correct one with an asterisk (*) at the start of the line. Ignored when the question type is Description.';
$string['approve'] = 'Approve';
$string['attackposture'] = 'Attack posture';
$string['attemptsreport'] = 'PlayerCards attempts report: {$a}';
$string['attemptsreportnav'] = 'Attempts report';
$string['boostatk'] = 'Boost ATK';
$string['boostdef'] = 'Boost DEF';
$string['changeposturebtn'] = 'Change posture';
$string['classpromotion'] = 'Class Promotion';
$string['cointossai'] = 'The AI won the coin toss and goes first.';
$string['cointossyou'] = 'You won the coin toss and go first.';
$string['completionwins_desc'] = 'Win the match at least {$a} time(s)';
$string['completionwinsgroup'] = 'Student must win at least';
$string['confirmdeletelore'] = 'Delete this Lore card? This cannot be undone.';
$string['confirmdeletequestion'] = 'Delete this question? This cannot be undone.';
$string['contentheader'] = 'Lore content source';
$string['deckerrorinfoquizfloor'] = 'Deck needs at least {$a} Info/Quiz cards combined.';
$string['deckerrorlevelfloor'] = 'Deck needs at least {$a->min} level {$a->level} Guardian(s).';
$string['deckerrormaxcopies'] = 'This card allows at most {$a} copies per deck.';
$string['deckerrorsize'] = 'Deck must have between {$a->min} and {$a->max} cards (currently {$a->actual}).';
$string['declareattack'] = 'Select an enemy Guardian to attack';
$string['defenseposture'] = 'Defense posture';
$string['difficulty_easy'] = 'Easy';
$string['difficulty_hard'] = 'Hard';
$string['difficulty_medium'] = 'Medium';
$string['difficulty_normal'] = 'Normal';
$string['directattackbtn'] = 'Attack directly';
$string['effectapplied'] = 'Effect applied.';
$string['emptyguardianslot'] = 'Empty Guardian slot';
$string['emptyloreslot'] = 'Empty Lore slot';
$string['endturnbtn'] = 'End turn';
$string['error_alreadyattacked'] = 'This Guardian has already attacked this turn.';
$string['error_atleastonesource'] = 'Select at least one content source.';
$string['error_banksourcenotready'] = 'This card sources content from the Moodle question bank, which is not supported yet — only the own pool works for now.';
$string['error_completionwins'] = 'Enter a number of wins greater than zero.';
$string['error_emptyslot'] = 'That field slot has no Guardian.';
$string['error_hud_cost_qty'] = 'Enter a quantity of at least 1 when a PlayerHUD item is configured.';
$string['error_invaliddifficulty'] = 'Invalid AI difficulty.';
$string['error_invalideffecttarget'] = 'Choose a valid Guardian for this effect to target.';
$string['error_invalidhandcard'] = 'That card is not a Guardian in your hand.';
$string['error_invalidmatchtoken'] = 'This match is no longer active.';
$string['error_invalidposture'] = 'Invalid posture.';
$string['error_invalidsacrifice'] = 'Choose a level 1-3 Guardian of your own already in play to sacrifice.';
$string['error_invalidtarget'] = 'That field slot has no Guardian to attack.';
$string['error_matchfinished'] = 'This match has already finished.';
$string['error_maxturns'] = 'Turn limit cannot be negative.';
$string['error_mustbeattackposture'] = 'Only a Guardian in Attack posture can declare an attack.';
$string['error_musteralreadyused'] = 'You already mustered a Guardian this turn.';
$string['error_musttargetguardian'] = 'The opponent has a Guardian in play — target it instead of attacking directly.';
$string['error_needfieldsacrifice'] = 'At least one of the two sacrifices must already be in play, not both from hand.';
$string['error_needonecorrect'] = 'Exactly one answer option must be marked correct with a leading asterisk (*).';
$string['error_needtwoanswers'] = 'Enter at least two answer options.';
$string['error_needtwosacrifices'] = 'Class Promotion needs exactly 2 Guardians to sacrifice.';
$string['error_noactivedeck'] = 'You need an active deck before starting a match.';
$string['error_nobattlephaseturn1'] = 'The player who goes first has no Battle Phase on turn 1.';
$string['error_nocontentavailable'] = 'This card has no approved content available yet.';
$string['error_notmulliganphase'] = 'The mulligan decision is not available right now.';
$string['error_notquizcard'] = 'That is not a Quiz card.';
$string['error_notyourturn'] = 'It is not your turn.';
$string['error_posturealreadyused'] = 'You already changed a Guardian\'s posture this turn.';
$string['error_promotionnotauthorized'] = 'No Class Promotion is currently authorised — activate an enabling Lore card first.';
$string['error_promotiontrap'] = 'A Trap card cannot authorise a Class Promotion — use Info or Quiz instead.';
$string['error_quiztimerseconds'] = 'Quiz timer must be at least 5 seconds.';
$string['error_required'] = 'This field is required.';
$string['error_sacrificenotallowed'] = 'A level 1-3 Guardian is mustered for free — no sacrifice needed.';
$string['error_sacrificerequired'] = 'Mustering a level 4-5 Guardian requires sacrificing a level 1-3 Guardian in play.';
$string['error_slotoccupied'] = 'That field slot is already occupied.';
$string['error_summoningsickness'] = 'A Guardian cannot change posture the turn it was mustered.';
$string['error_unknowneffecttype'] = 'This card has an effect type the game does not recognise.';
$string['error_useactivatequiz'] = 'Use the Quiz activation action for this card instead.';
$string['grademethod'] = 'Grading method';
$string['grademethod_average'] = 'Average of all matches';
$string['grademethod_first'] = 'First match';
$string['grademethod_highest'] = 'Highest match';
$string['grademethod_last'] = 'Last match';
$string['guardian'] = 'Guardian';
$string['guardianslots'] = 'Guardian slots';
$string['howtoplay'] = 'How to play';
$string['howtoplaybody'] = 'PlayerCards is a collectible card game. Muster Guardians to battle, and activate Lore cards — Info, Quiz and Trap — to review course content and turn the match in your favour.';
$string['hud_card_cost_item'] = 'PlayerHUD item used as currency to buy cards';
$string['hud_card_cost_item_help'] = 'block_playerhud item ID used to charge students for buying cards in the shop. Leave as 0 to disable purchases. A proper item picker arrives in a later development phase.';
$string['hud_card_cost_qty'] = 'Base cost per card';
$string['hud_retry_cost_item'] = 'PlayerHUD item charged for a new match attempt';
$string['hud_retry_cost_item_help'] = 'block_playerhud item ID charged when a student starts a new match. Leave as 0 for free retries.';
$string['hud_retry_cost_qty'] = 'Quantity charged per retry';
$string['hudheader'] = 'PlayerHUD integration';
$string['keephand'] = 'Keep hand';
$string['lifepoints'] = 'Life points';
$string['lore'] = 'Lore';
$string['lorecontent'] = 'Content';
$string['lorecontent_help'] = 'Educational text (Info) or a description of the effect (Quiz/Trap).';
$string['loredeleted'] = 'Lore card deleted.';
$string['loredifficulty'] = 'Difficulty';
$string['loreeffecttype'] = 'Effect type';
$string['loreeffecttype_help'] = 'A short identifier for the effect this card applies (e.g. atk_buff, def_buff, lp_heal, lp_damage, destroy, enable_promotion). SCOPE.md 4.6.';
$string['loreeffectvalue'] = 'Effect value';
$string['loreinfo'] = 'Info';
$string['loremaxcopies'] = 'Maximum copies per deck';
$string['lorename'] = 'Card name';
$string['lorequestioncategory'] = 'Content category';
$string['lorequestioncategory_help'] = 'Category label (own pool) or question bank category ID (real bank) this card draws content from at random each time it is activated. SCOPE.md 4.6.';
$string['lorequestionsource'] = 'Content source';
$string['lorequiz'] = 'Quiz';
$string['loresaved'] = 'Lore card saved.';
$string['loreslots'] = 'Lore slots';
$string['loretrap'] = 'Trap';
$string['managelore'] = 'Manage Lore cards and questions: {$a}';
$string['managelorenav'] = 'Manage Lore cards and questions';
$string['matchendedlabel'] = 'Match ended: {$a}';
$string['matchheader'] = 'Match settings';
$string['maxturns'] = 'Turn limit';
$string['maxturns_help'] = 'Maximum number of individual turns before the match ends automatically, awarding victory to whoever has more life points. 0 means no limit.';
$string['modulename'] = 'PlayerCards: Guardians & Lore';
$string['modulename_help'] = 'The PlayerCards activity lets the teacher build a collectible card game where course content becomes playable Info and Quiz cards, while students duel against an AI opponent.';
$string['modulenameplural'] = 'PlayerCards activities';
$string['musterguardian'] = 'Muster';
$string['noattemptsyet'] = 'No completed matches yet.';
$string['opponenthand'] = 'Opponent\'s hand';
$string['playagain'] = 'Play again';
$string['playercards:addinstance'] = 'Add a new PlayerCards activity';
$string['playercards:managelore'] = 'Manage PlayerCards Lore cards and questions';
$string['playercards:view'] = 'Play PlayerCards';
$string['playercards:viewreports'] = 'View PlayerCards reports';
$string['pluginadministration'] = 'PlayerCards administration';
$string['pluginname'] = 'PlayerCards: Guardians & Lore';
$string['privacy:metadata:aidifficulty'] = 'The AI difficulty associated with this record: easy, normal or hard.';
$string['privacy:metadata:cardid'] = 'The ID of the Guardian or Lore card, depending on cardtype.';
$string['privacy:metadata:cardtype'] = 'Whether the card is a Guardian or a Lore card.';
$string['privacy:metadata:playercards_attempts'] = 'Stores each completed match a student plays in a PlayerCards activity.';
$string['privacy:metadata:playercards_attempts:lpremaining'] = 'The life points remaining when the match ended.';
$string['privacy:metadata:playercards_attempts:result'] = 'Whether the match was a win or a loss.';
$string['privacy:metadata:playercards_attempts:score'] = 'The grade contribution of this match attempt.';
$string['privacy:metadata:playercards_attempts:turnsplayed'] = 'The total number of turns played in the match.';
$string['privacy:metadata:playercards_deck_cards'] = 'Stores the cards making up a student\'s saved deck.';
$string['privacy:metadata:playercards_decks'] = 'Stores each deck a student saves in a PlayerCards activity.';
$string['privacy:metadata:playercards_decks:active'] = 'Whether this is the student\'s currently selected deck.';
$string['privacy:metadata:playercards_decks:name'] = 'The deck\'s name.';
$string['privacy:metadata:playercards_decks:size'] = 'The total number of cards in the deck.';
$string['privacy:metadata:playercards_lore'] = 'Stores each Lore card authored by a teacher for a PlayerCards activity.';
$string['privacy:metadata:playercards_lore:content'] = 'The Lore card\'s educational text or effect description.';
$string['privacy:metadata:playercards_lore:createdby'] = 'The ID of the teacher who authored this Lore card.';
$string['privacy:metadata:playercards_lore:name'] = 'The Lore card\'s name.';
$string['privacy:metadata:playercards_lore:subtype'] = 'The Lore card subtype: info, quiz or trap.';
$string['privacy:metadata:playercards_ownership'] = 'Stores the cards a student owns in a PlayerCards activity.';
$string['privacy:metadata:playercards_questions'] = 'Stores each item in PlayerCards\' own question pool, added by a teacher.';
$string['privacy:metadata:playercards_questions:addedby'] = 'The ID of the teacher who added this question pool item.';
$string['privacy:metadata:playercards_questions:answers'] = 'The answer options and which one is correct, when applicable.';
$string['privacy:metadata:playercards_questions:category'] = 'The free-form category label the teacher set for this item.';
$string['privacy:metadata:playercards_questions:qtype'] = 'The item type: multiple choice, true/false, or description.';
$string['privacy:metadata:playercards_questions:questiontext'] = 'The question or informational text.';
$string['privacy:metadata:preference:seenintro'] = 'Whether the automatic how-to-play introduction has already been shown.';
$string['privacy:metadata:quantity'] = 'The number of copies of this card.';
$string['privacy:metadata:timecreated'] = 'The time at which this record was created.';
$string['privacy:metadata:timemodified'] = 'The time at which this record was last modified.';
$string['privacy:metadata:userid'] = 'The ID of the user this record belongs to.';
$string['promotionavailable'] = 'Class Promotion available (+{$a})';
$string['qtype_description'] = 'Description (Info, no answer)';
$string['qtype_multichoice'] = 'Multiple choice';
$string['qtype_truefalse'] = 'True/False';
$string['questionapproved'] = 'Approved';
$string['questioncategorylabel'] = 'Category';
$string['questioncategorylabel_help'] = 'Free-form label grouping items that can be drawn by the same Lore card. SCOPE.md 4.6.';
$string['questiondeleted'] = 'Question deleted.';
$string['questionpool'] = 'PlayerCards\' own question pool';
$string['questionsaved'] = 'Question saved.';
$string['questionsource_bank'] = 'Real Moodle question bank';
$string['questionsource_own'] = 'PlayerCards\' own question pool';
$string['questiontextlabel'] = 'Question / content text';
$string['questiontype'] = 'Type';
$string['quiztimerseconds'] = 'Quiz answer timer (seconds)';
$string['quiztimerseconds_help'] = 'How long the opponent has to answer a Quiz card once activated. 15 to 30 seconds recommended.';
$string['redrawhand'] = 'Draw new hand';
$string['result'] = 'Result';
$string['resultloss'] = 'Loss';
$string['resultwin'] = 'Win';
$string['sacrificialmuster'] = 'Sacrificial muster';
$string['selectdifficulty'] = 'Difficulty';
$string['selecteffecttarget'] = 'Select a target for this effect';
$string['selectemptyloreslot'] = 'Select an empty Lore slot';
$string['selectpromotionsacrifice'] = 'Select 2 own Guardians to sacrifice (at least one already in play)';
$string['selectpromotiontarget'] = 'Select a Guardian to receive the bonus';
$string['selectsacrifice'] = 'Select a level 1-3 Guardian of your own in play to sacrifice';
$string['selectslottomuster'] = 'Select an empty slot to muster this Guardian';
$string['startmatch'] = 'Start match';
$string['summonedthisturn'] = 'Summoned this turn';
$string['turnnumberlabel'] = 'Turn {$a}';
$string['turnsplayed'] = 'Turns played';
$string['unapprove'] = 'Unapprove';
$string['wrongansweractivated'] = 'Wrong answer — effect activated';
$string['yourhand'] = 'Your hand';
$string['yourturn'] = 'Your turn';
