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
 * Renders the PlayerCards board and drives match start/mulligan/muster/posture/combat
 * (SCOPE.md 16, Fase 3 Etapas 1-2). Lore actions (set_lore/activate_lore/activate_quiz/
 * class_promotion) arrive in Etapa 3, alongside their own Web Services.
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
    {key: 'attackposture', component: 'mod_playercards'},
    {key: 'defenseposture', component: 'mod_playercards'},
    {key: 'selectslottomuster', component: 'mod_playercards'},
    {key: 'selectsacrifice', component: 'mod_playercards'},
    {key: 'changeposturebtn', component: 'mod_playercards'},
    {key: 'declareattack', component: 'mod_playercards'},
    {key: 'directattackbtn', component: 'mod_playercards'},
    {key: 'summonedthisturn', component: 'mod_playercards'},
    {key: 'cancel', component: 'core'},
    {key: 'error', component: 'core'},
];

/** @var {number} Guardian level up to which mustering is free (SCOPE.md 4.2). */
const FREE_MUSTER_MAX_LEVEL = 3;

let cmid = 0;
let defaultDifficulty = 'normal';
let rootEl = null;
let currentToken = null;
let currentState = null;
let currentPanel = 'lobby';
let strings = {};

// Ephemeral client-only selection state for the muster/posture/attack flow — never sent
// to the server until the player completes an action; cleared after every WS call and
// on cancel.
let selectedHandUid = null;
let selectedPosture = 'attack';
let selectedSacrificeSlot = null;
let selectedAttackerSlot = null;

/**
 * Calls a Web Service function by name.
 *
 * @param {string} methodname Web service function name.
 * @param {object} args Arguments.
 * @returns {Promise}
 */
const callWs = (methodname, args) => Ajax.call([{methodname, args}])[0];

/**
 * Clears every piece of client-only selection state (muster/attack in progress).
 *
 * @returns {void}
 */
const clearSelection = () => {
    selectedHandUid = null;
    selectedPosture = 'attack';
    selectedSacrificeSlot = null;
    selectedAttackerSlot = null;
};

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
 * Maps a raw field zone (5 slots, each occupied or not) into the shape the template's
 * slot loop expects, adding the slot's own index.
 *
 * @param {Array} zone Raw slot entries from match state.
 * @returns {Array}
 */
const hydrateZone = (zone) => zone.map((slot, index) => ({
    index,
    occupied: slot.occupied,
    name: slot.name,
    atk: slot.atk,
    def: slot.def,
    posturelabel: slot.posture === 'attack' ? strings.attackposture : strings.defenseposture,
    sicklabel: slot.sick ? strings.summonedthisturn : '',
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
    aislots: hydrateZone(state.aifield),
    humanslots: hydrateZone(state.humanfield),
});

/**
 * Finds a hand card's raw entry by uid.
 *
 * @param {string} uid Card uid.
 * @returns {object|null}
 */
const findHandCard = (uid) => currentState.humanhand.find((card) => card.uid === uid) ?? null;

/**
 * Whether a given own field slot holds a Guardian eligible as sacrifice material
 * (level 1-3, SCOPE.md 4.2).
 *
 * @param {number} index Field slot index.
 * @returns {boolean}
 */
const isSacrificeEligible = (index) => {
    const slot = currentState.humanfield[index];
    return slot.occupied && slot.level <= FREE_MUSTER_MAX_LEVEL;
};

/**
 * Renders the contextual action bar (posture choice during muster, or
 * attack/change-posture/cancel buttons for a selected own Guardian) and refreshes
 * selection-highlight classes on the rendered slots/hand cards. Runs after every
 * render() and after every selection change — none of this needs a server round-trip.
 *
 * @returns {void}
 */
const refreshSelectionUi = () => {
    if (currentPanel !== 'main' || !rootEl) {
        return;
    }

    rootEl.querySelectorAll('.playercards-card, .playercards-slot').forEach((el) => {
        el.classList.remove('playercards-selected', 'playercards-target');
    });

    const actionbar = rootEl.querySelector('#playercards-actionbar');
    if (!actionbar) {
        return;
    }

    if (selectedHandUid !== null) {
        const handcard = findHandCard(selectedHandUid);
        const handEl = rootEl.querySelector(`.playercards-hand .playercards-card[data-uid="${selectedHandUid}"]`);
        if (handEl) {
            handEl.classList.add('playercards-selected');
        }

        const needsSacrifice = handcard !== null && handcard.level > FREE_MUSTER_MAX_LEVEL;
        if (needsSacrifice && selectedSacrificeSlot === null) {
            currentState.humanfield.forEach((slot, index) => {
                if (isSacrificeEligible(index)) {
                    rootEl.querySelector(`.playercards-slot[data-zone="human"][data-slot="${index}"]`)
                        ?.classList.add('playercards-target');
                }
            });
            actionbar.textContent = strings.selectsacrifice;
            return;
        }

        if (selectedSacrificeSlot !== null) {
            rootEl.querySelector(`.playercards-slot[data-zone="human"][data-slot="${selectedSacrificeSlot}"]`)
                ?.classList.add('playercards-selected');
        }

        currentState.humanfield.forEach((slot, index) => {
            if (!slot.occupied || index === selectedSacrificeSlot) {
                rootEl.querySelector(`.playercards-slot[data-zone="human"][data-slot="${index}"]`)
                    ?.classList.add('playercards-target');
            }
        });

        actionbar.innerHTML = '';
        actionbar.appendChild(buildPostureToggle());
        actionbar.appendChild(buildTextNode(strings.selectslottomuster));
        actionbar.appendChild(buildCancelButton());
        return;
    }

    if (selectedAttackerSlot !== null) {
        const attackerEl = rootEl.querySelector(`.playercards-slot[data-zone="human"][data-slot="${selectedAttackerSlot}"]`);
        attackerEl?.classList.add('playercards-selected');

        const attacker = currentState.humanfield[selectedAttackerSlot];
        const aiHasGuardian = currentState.aifield.some((slot) => slot.occupied);
        const canAttack = attacker.posture === 'attack' && !attacker.sick && !attacker.attackedthisturn;
        const canChangePosture = !attacker.sick && !currentState.postureusedthisturn;

        if (canAttack) {
            if (aiHasGuardian) {
                currentState.aifield.forEach((slot, index) => {
                    if (slot.occupied) {
                        rootEl.querySelector(`.playercards-slot[data-zone="ai"][data-slot="${index}"]`)
                            ?.classList.add('playercards-target');
                    }
                });
            }
        }

        actionbar.innerHTML = '';
        if (canAttack && !aiHasGuardian) {
            actionbar.appendChild(buildActionButton(strings.directattackbtn, onDirectAttack));
        } else if (canAttack) {
            actionbar.appendChild(buildTextNode(strings.declareattack));
        }
        if (canChangePosture) {
            actionbar.appendChild(buildActionButton(strings.changeposturebtn, onChangePosture));
        }
        actionbar.appendChild(buildCancelButton());
        return;
    }

    actionbar.innerHTML = '';
};

/**
 * Builds the small Attack/Defense posture toggle shown while a muster is in progress.
 *
 * @returns {HTMLElement}
 */
const buildPostureToggle = () => {
    const wrapper = document.createElement('span');
    wrapper.className = 'playercards-posture-toggle';

    [['attack', strings.attackposture], ['defense', strings.defenseposture]].forEach(([value, label]) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm ' + (selectedPosture === value ? 'btn-primary' : 'btn-outline-secondary');
        btn.textContent = label;
        btn.addEventListener('click', () => {
            selectedPosture = value;
            refreshSelectionUi();
        });
        wrapper.appendChild(btn);
    });

    return wrapper;
};

/**
 * Builds a plain text node wrapped in a span, for instructional text in the action bar.
 *
 * @param {string} text Text content.
 * @returns {HTMLElement}
 */
const buildTextNode = (text) => {
    const span = document.createElement('span');
    span.className = 'playercards-actionbar-hint';
    span.textContent = text;
    return span;
};

/**
 * Builds a generic action button for the action bar.
 *
 * @param {string} label Button label.
 * @param {Function} onClick Click handler.
 * @returns {HTMLElement}
 */
const buildActionButton = (label, onClick) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-primary';
    btn.textContent = label;
    btn.addEventListener('click', onClick);
    return btn;
};

/**
 * Builds the cancel-selection button, always available once something is selected.
 *
 * @returns {HTMLElement}
 */
const buildCancelButton = () => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-secondary';
    btn.textContent = strings.cancel;
    btn.addEventListener('click', () => {
        clearSelection();
        refreshSelectionUi();
    });
    return btn;
};

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

    if (currentPanel !== 'main') {
        return;
    }

    rootEl.querySelectorAll('.playercards-hand .playercards-card').forEach((el) => {
        el.addEventListener('click', () => onHandCardClick(el.dataset.uid));
    });

    rootEl.querySelectorAll('.playercards-slot[data-zone="human"]').forEach((el) => {
        el.addEventListener('click', () => onHumanSlotClick(parseInt(el.dataset.slot, 10)));
    });

    rootEl.querySelectorAll('.playercards-slot[data-zone="ai"]').forEach((el) => {
        el.addEventListener('click', () => onAiSlotClick(parseInt(el.dataset.slot, 10)));
    });

    refreshSelectionUi();
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
    currentState = state;
    currentToken = state.hasmatch ? state.token : null;
    clearSelection();

    if (!state.hasmatch) {
        currentPanel = 'lobby';
        await render(buildLobbyContext());
        return;
    }

    if (state.phase === 'mulligan') {
        currentPanel = 'mulligan';
        await render(buildMulliganContext(state));
        return;
    }

    currentPanel = 'main';
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
 * Handles clicking a card in the player's own hand — selects it (Guardians only) to
 * start a muster, or clears the selection if the same card is clicked again.
 *
 * @param {string} uid Card uid.
 * @returns {void}
 */
const onHandCardClick = (uid) => {
    const card = findHandCard(uid);
    if (card === null || card.cardtype !== 'guardian') {
        return;
    }

    selectedAttackerSlot = null;

    if (selectedHandUid === uid) {
        clearSelection();
    } else {
        selectedHandUid = uid;
        selectedPosture = 'attack';
        selectedSacrificeSlot = null;
    }

    refreshSelectionUi();
};

/**
 * Handles clicking one of the player's own field slots — either a step in an in-progress
 * muster (choosing the sacrifice, then the target slot), or selecting an existing
 * Guardian to attack/change posture.
 *
 * @param {number} index Field slot index.
 * @returns {Promise<void>}
 */
const onHumanSlotClick = async(index) => {
    if (selectedHandUid !== null) {
        const handcard = findHandCard(selectedHandUid);
        const slot = currentState.humanfield[index];

        if (handcard.level > FREE_MUSTER_MAX_LEVEL && selectedSacrificeSlot === null) {
            if (slot.occupied && isSacrificeEligible(index)) {
                selectedSacrificeSlot = index;
                refreshSelectionUi();
            }
            return;
        }

        if (slot.occupied && index !== selectedSacrificeSlot) {
            return;
        }

        try {
            const state = await callWs('mod_playercards_muster_guardian', {
                cmid,
                token: currentToken,
                handuid: selectedHandUid,
                fieldslot: index,
                posture: selectedPosture,
                sacrificefieldslot: selectedSacrificeSlot ?? -1,
            });
            await showState(state);
        } catch (error) {
            Notification.alert(strings.error, error.message);
        }
        return;
    }

    const slot = currentState.humanfield[index];
    if (!slot.occupied || slot.sick) {
        clearSelection();
        refreshSelectionUi();
        return;
    }

    selectedAttackerSlot = selectedAttackerSlot === index ? null : index;
    refreshSelectionUi();
};

/**
 * Handles clicking one of the AI's field slots — declares an attack against it when an
 * own attacker is currently selected.
 *
 * @param {number} index Field slot index.
 * @returns {Promise<void>}
 */
const onAiSlotClick = async(index) => {
    if (selectedAttackerSlot === null || !currentState.aifield[index].occupied) {
        return;
    }

    try {
        const state = await callWs('mod_playercards_declare_attack', {
            cmid,
            token: currentToken,
            attackerslot: selectedAttackerSlot,
            targetslot: index,
        });
        await showState(state);
    } catch (error) {
        Notification.alert(strings.error, error.message);
    }
};

/**
 * Declares a direct attack with the currently selected Guardian.
 *
 * @returns {Promise<void>}
 */
const onDirectAttack = async() => {
    try {
        const state = await callWs('mod_playercards_declare_attack', {
            cmid,
            token: currentToken,
            attackerslot: selectedAttackerSlot,
            targetslot: -1,
        });
        await showState(state);
    } catch (error) {
        Notification.alert(strings.error, error.message);
    }
};

/**
 * Changes the posture of the currently selected Guardian.
 *
 * @returns {Promise<void>}
 */
const onChangePosture = async() => {
    try {
        const state = await callWs('mod_playercards_change_posture', {
            cmid,
            token: currentToken,
            fieldslot: selectedAttackerSlot,
        });
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
