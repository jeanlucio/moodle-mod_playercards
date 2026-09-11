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
 * Renders the PlayerCards board and drives match start/mulligan (SCOPE.md 16, Fase 3
 * Etapa 1). Muster/combat/Lore actions are not wired up yet — they arrive in later
 * etapas, alongside their own Web Services.
 *
 * @module     mod_playercards/board
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getStrings, getString} from 'core/str';

const STRING_REQUESTS = [
    {key: 'selectdifficulty', component: 'mod_playercards'},
    {key: 'startmatch', component: 'mod_playercards'},
    {key: 'keephand', component: 'mod_playercards'},
    {key: 'redrawhand', component: 'mod_playercards'},
    {key: 'yourhand', component: 'mod_playercards'},
    {key: 'opponenthand', component: 'mod_playercards'},
    {key: 'guardianslots', component: 'mod_playercards'},
    {key: 'loreslots', component: 'mod_playercards'},
    {key: 'emptyguardianslot', component: 'mod_playercards'},
    {key: 'emptyloreslot', component: 'mod_playercards'},
    {key: 'actionscomingsoon', component: 'mod_playercards'},
    {key: 'lifepoints', component: 'mod_playercards'},
    {key: 'cointossyou', component: 'mod_playercards'},
    {key: 'cointossai', component: 'mod_playercards'},
    {key: 'yourturn', component: 'mod_playercards'},
    {key: 'aiturn', component: 'mod_playercards'},
    {key: 'difficulty_easy', component: 'mod_playercards'},
    {key: 'difficulty_normal', component: 'mod_playercards'},
    {key: 'difficulty_hard', component: 'mod_playercards'},
    {key: 'loreinfo', component: 'mod_playercards'},
    {key: 'lorequiz', component: 'mod_playercards'},
    {key: 'loretrap', component: 'mod_playercards'},
    {key: 'error', component: 'core'},
];

let cmid = 0;
let defaultDifficulty = 'normal';
let rootEl = null;
let currentToken = null;
let strings = {};

/**
 * Calls a Web Service function by name.
 *
 * @param {string} methodname Web service function name.
 * @param {object} args Arguments.
 * @returns {Promise}
 */
const callWs = (methodname, args) => Ajax.call([{methodname, args}])[0];

/**
 * Maps a raw card entry (as returned by the Web service) into the shape the Mustache
 * template expects — a single boolean flag standing in for the cardtype check the
 * logic-less template cannot do itself.
 *
 * @param {Array} hand Raw card entries.
 * @returns {Array}
 */
const hydrateHand = (hand) => hand.map((card) => ({
    uid: card.uid,
    isguardian: card.cardtype === 'guardian',
    name: card.name,
    atk: card.atk,
    def: card.def,
    level: card.level,
    subtypelabel: card.cardtype === 'lore' ? strings['lore' + card.subtype] : '',
}));

/**
 * Builds the template context for the lobby (no match in progress).
 *
 * @returns {object}
 */
const buildLobbyContext = () => ({
    showlobby: true,
    showmulligan: false,
    showmain: false,
    difficulties: [
        {value: 'easy', label: strings.difficulty_easy, selected: defaultDifficulty === 'easy'},
        {value: 'normal', label: strings.difficulty_normal, selected: defaultDifficulty === 'normal'},
        {value: 'hard', label: strings.difficulty_hard, selected: defaultDifficulty === 'hard'},
    ],
});

/**
 * Builds the template context for the mulligan decision screen.
 *
 * @param {object} state Match state from the Web service.
 * @returns {object}
 */
const buildMulliganContext = (state) => ({
    showlobby: false,
    showmulligan: true,
    showmain: false,
    cointossmessage: state.firstplayer === 'human' ? strings.cointossyou : strings.cointossai,
    hand: hydrateHand(state.humanhand),
});

/**
 * Builds the template context for the main board.
 *
 * @param {object} state Match state from the Web service.
 * @param {string} turnlabel Already-resolved "Turn N" string.
 * @returns {object}
 */
const buildMainContext = (state, turnlabel) => ({
    showlobby: false,
    showmulligan: false,
    showmain: true,
    turnlabel,
    activeplayerlabel: state.activeplayer === 'human' ? strings.yourturn : strings.aiturn,
    humanlp: state.lifepoints.human,
    ailp: state.lifepoints.ai,
    aihandcount: state.aihandcount,
    humandeckcount: state.humandeckcount,
    aideckcount: state.aideckcount,
    hand: hydrateHand(state.humanhand),
    guardianslotlabel: strings.emptyguardianslot,
    loreslotlabel: strings.emptyloreslot,
    fieldslots: [1, 2, 3, 4, 5],
});

/**
 * Attaches click handlers to whatever controls the current panel rendered.
 *
 * @returns {void}
 */
const bindEvents = () => {
    const startbtn = rootEl.querySelector('#playercards-startmatch-btn');
    if (startbtn) {
        startbtn.addEventListener('click', onStartMatch);
    }

    const keepbtn = rootEl.querySelector('#playercards-keephand-btn');
    if (keepbtn) {
        keepbtn.addEventListener('click', () => onMulligan(true));
    }

    const redrawbtn = rootEl.querySelector('#playercards-redrawhand-btn');
    if (redrawbtn) {
        redrawbtn.addEventListener('click', () => onMulligan(false));
    }
};

/**
 * Renders a template context into the board container.
 *
 * @param {object} context Template context.
 * @returns {Promise<void>}
 */
const render = async(context) => {
    const {html, js} = await Templates.renderForPromise('mod_playercards/board_panel', context);
    Templates.replaceNodeContents(rootEl, html, js);
    bindEvents();
};

/**
 * Renders whatever panel matches the given match state.
 *
 * @param {object} state Match state from the Web service.
 * @returns {Promise<void>}
 */
const showState = async(state) => {
    currentToken = state.hasmatch ? state.token : null;

    if (!state.hasmatch) {
        await render(buildLobbyContext());
        return;
    }

    if (state.phase === 'mulligan') {
        await render(buildMulliganContext(state));
        return;
    }

    const turnlabel = await getString('turnnumberlabel', 'mod_playercards', state.turnnumber);
    await render(buildMainContext(state, turnlabel));
};

/**
 * Starts a new match with the difficulty currently selected in the lobby.
 *
 * @returns {Promise<void>}
 */
const onStartMatch = async() => {
    const select = rootEl.querySelector('#playercards-difficulty-select');
    const difficulty = select ? select.value : defaultDifficulty;

    try {
        const state = await callWs('mod_playercards_start_match', {cmid, difficulty});
        await showState(state);
    } catch (error) {
        Notification.alert(strings.error, error.message);
    }
};

/**
 * Resolves the mulligan decision.
 *
 * @param {boolean} keep Whether to keep the opening hand.
 * @returns {Promise<void>}
 */
const onMulligan = async(keep) => {
    try {
        const state = await callWs('mod_playercards_mulligan', {cmid, token: currentToken, keep});
        await showState(state);
    } catch (error) {
        Notification.alert(strings.error, error.message);
    }
};

/**
 * Initialises the board for one course module.
 *
 * @param {number} moduleid Course module id.
 * @param {string} instancedifficulty Instance's configured default AI difficulty.
 * @returns {Promise<void>}
 */
export const init = async(moduleid, instancedifficulty) => {
    cmid = moduleid;
    defaultDifficulty = instancedifficulty;
    rootEl = document.getElementById('playercards-board');
    if (!rootEl) {
        return;
    }

    const values = await getStrings(STRING_REQUESTS);
    STRING_REQUESTS.forEach((request, index) => {
        strings[request.key] = values[index];
    });

    try {
        const state = await callWs('mod_playercards_get_state', {cmid});
        await showState(state);
    } catch (error) {
        Notification.exception(error);
    }
};
