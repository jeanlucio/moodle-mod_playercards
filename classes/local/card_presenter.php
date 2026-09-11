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
 * Hydrates raw card references from match state into client-displayable data.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playercards\local;

/**
 * Joins in Guardian/Lore metadata for a list of card references (SCOPE.md 5), batched to
 * avoid one database round-trip per card.
 */
class card_presenter {
    /**
     * Hydrates a list of card entries (as stored in match state: cardtype, cardid, uid)
     * with display metadata.
     *
     * @param array $entries List of ['cardtype' => 'guardian'|'lore', 'cardid' => int,
     *  'uid' => string].
     * @return array Same entries, each enriched with 'name' and either level/atk/def
     *  (guardian) or subtype (lore).
     */
    public static function hydrate(array $entries): array {
        global $DB;

        $guardianids = [];
        $loreids = [];
        foreach ($entries as $entry) {
            if ($entry['cardtype'] === 'guardian') {
                $guardianids[] = $entry['cardid'];
            } else {
                $loreids[] = $entry['cardid'];
            }
        }

        $guardians = $guardianids !== []
            ? $DB->get_records_list('playercards_guardians', 'id', array_unique($guardianids))
            : [];
        $lorecards = $loreids !== []
            ? $DB->get_records_list('playercards_lore', 'id', array_unique($loreids))
            : [];

        $result = [];
        foreach ($entries as $entry) {
            if ($entry['cardtype'] === 'guardian') {
                $card = $guardians[$entry['cardid']] ?? null;
                $result[] = [
                    'uid' => $entry['uid'],
                    'cardtype' => 'guardian',
                    'cardid' => (int) $entry['cardid'],
                    'name' => $card !== null ? format_string($card->name) : '',
                    'level' => $card !== null ? (int) $card->level : 0,
                    'atk' => $card !== null ? (int) $card->atk : 0,
                    'def' => $card !== null ? (int) $card->def : 0,
                ];
            } else {
                $card = $lorecards[$entry['cardid']] ?? null;
                $result[] = [
                    'uid' => $entry['uid'],
                    'cardtype' => 'lore',
                    'cardid' => (int) $entry['cardid'],
                    'name' => $card !== null ? format_string($card->name) : '',
                    'subtype' => $card->subtype ?? '',
                ];
            }
        }

        return $result;
    }
}
