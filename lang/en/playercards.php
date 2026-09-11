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

$string['actionscomingsoon'] = 'Summoning and combat actions arrive in an upcoming update.';
$string['activatelore'] = 'Activate';
$string['addlorecard'] = 'Add Lore card';
$string['addquestion'] = 'Add question';
$string['aidifficultydefault'] = 'Default AI difficulty';
$string['aidifficultydefault_help'] = 'Difficulty the student can choose when starting a match against the AI (easy, normal or hard). This sets the option pre-selected by default.';
$string['aiturn'] = 'AI\'s turn';
$string['answerlines'] = 'Answer options';
$string['answerlines_help'] = 'One option per line. Mark the correct one with an asterisk (*) at the start of the line. Ignored when the question type is Description.';
$string['approve'] = 'Approve';
$string['attackposture'] = 'Attack posture';
$string['attemptsreport'] = 'PlayerCards attempts report: {$a}';
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
$string['defenseposture'] = 'Defense posture';
$string['difficulty_easy'] = 'Easy';
$string['difficulty_hard'] = 'Hard';
$string['difficulty_medium'] = 'Medium';
$string['difficulty_normal'] = 'Normal';
$string['emptyguardianslot'] = 'Empty Guardian slot';
$string['emptyloreslot'] = 'Empty Lore slot';
$string['error_atleastonesource'] = 'Select at least one content source.';
$string['error_completionwins'] = 'Enter a number of wins greater than zero.';
$string['error_hud_cost_qty'] = 'Enter a quantity of at least 1 when a PlayerHUD item is configured.';
$string['error_invaliddifficulty'] = 'Invalid AI difficulty.';
$string['error_invalidmatchtoken'] = 'This match is no longer active.';
$string['error_maxturns'] = 'Turn limit cannot be negative.';
$string['error_needonecorrect'] = 'Exactly one answer option must be marked correct with a leading asterisk (*).';
$string['error_needtwoanswers'] = 'Enter at least two answer options.';
$string['error_noactivedeck'] = 'You need an active deck before starting a match.';
$string['error_notmulliganphase'] = 'The mulligan decision is not available right now.';
$string['error_promotiontrap'] = 'A Trap card cannot authorise a Class Promotion — use Info or Quiz instead.';
$string['error_quiztimerseconds'] = 'Quiz timer must be at least 5 seconds.';
$string['error_required'] = 'This field is required.';
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
$string['matchheader'] = 'Match settings';
$string['maxturns'] = 'Turn limit';
$string['maxturns_help'] = 'Maximum number of individual turns before the match ends automatically, awarding victory to whoever has more life points. 0 means no limit.';
$string['modulename'] = 'PlayerCards: Guardians & Lore';
$string['modulename_help'] = 'The PlayerCards activity lets the teacher build a collectible card game where course content becomes playable Info and Quiz cards, while students duel against an AI opponent.';
$string['modulenameplural'] = 'PlayerCards activities';
$string['musterguardian'] = 'Muster';
$string['noattemptsyet'] = 'No completed matches yet.';
$string['opponenthand'] = 'Opponent\'s hand';
$string['playercards:addinstance'] = 'Add a new PlayerCards activity';
$string['playercards:managelore'] = 'Manage PlayerCards Lore cards and questions';
$string['playercards:view'] = 'Play PlayerCards';
$string['playercards:viewreports'] = 'View PlayerCards reports';
$string['pluginadministration'] = 'PlayerCards administration';
$string['pluginname'] = 'PlayerCards: Guardians & Lore';
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
$string['startmatch'] = 'Start match';
$string['turnnumberlabel'] = 'Turn {$a}';
$string['turnsplayed'] = 'Turns played';
$string['unapprove'] = 'Unapprove';
$string['wrongansweractivated'] = 'Wrong answer — effect activated';
$string['yourhand'] = 'Your hand';
$string['yourturn'] = 'Your turn';
