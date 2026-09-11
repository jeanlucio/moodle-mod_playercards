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
 * AMD module for the PlayerCards automatic how-to-play intro.
 *
 * @module     mod_playercards/intro
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {getString} from 'core/str';

const SELECTORS = {
    HOWTOPLAY_BTN: '#playercards-howtoplay-btn',
};

/**
 * Opens the how-to-play modal.
 *
 * @param {number} cmid Course module id.
 * @param {boolean} markSeenOnClose Whether closing the modal should mark the intro as seen.
 * @returns {Promise<void>}
 */
const showIntroModal = async(cmid, markSeenOnClose) => {
    const [title, body] = await Promise.all([
        getString('howtoplay', 'mod_playercards'),
        getString('howtoplaybody', 'mod_playercards'),
    ]);

    const modal = await Modal.create({
        title,
        body: `<p>${body}</p>`,
        show: true,
        removeOnClose: true,
    });

    if (markSeenOnClose) {
        modal.getRoot().on(window.jQuery ? 'modal:hidden' : 'hidden.bs.modal', async() => {
            try {
                await Ajax.call([{
                    methodname: 'mod_playercards_mark_intro_seen',
                    args: {cmid},
                }])[0];
            } catch (error) {
                Notification.exception(error);
            }
        });
    }
};

/**
 * Initialises the how-to-play button and, on first visit, auto-opens the modal.
 *
 * @param {number} cmid Course module id.
 * @param {boolean} shouldAutoShow Whether this user has not seen the intro yet.
 * @returns {void}
 */
export const init = (cmid, shouldAutoShow) => {
    const btn = document.querySelector(SELECTORS.HOWTOPLAY_BTN);
    if (btn) {
        btn.addEventListener('click', () => showIntroModal(cmid, false));
    }

    if (shouldAutoShow) {
        showIntroModal(cmid, true);
    }
};
