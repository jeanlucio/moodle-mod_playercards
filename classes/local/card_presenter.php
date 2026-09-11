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

    /**
     * Hydrates every field/Lore zone slot (as stored in match state: null, or a raw
     * ['uid', 'cardtype', 'cardid', 'posture'?, 'facedown'?, ...] entry) into the full
     * slot_structure() shape the Web service response needs, batching every guardian/lore
     * lookup across all given zones into a single pair of queries.
     *
     * @param array $zones Zone name => list of (null|array) raw slots, e.g.
     *  ['humanfield' => [...], 'humanlore' => [...], 'aifield' => [...], 'ailore' => [...]].
     * @return array Same zone names, each an array of slot_structure()-shaped entries.
     */
    public static function hydrate_zones(array $zones): array {
        global $DB;

        $guardianids = [];
        $loreids = [];
        foreach ($zones as $slots) {
            foreach ($slots as $slot) {
                if ($slot === null) {
                    continue;
                }
                if ($slot['cardtype'] === 'guardian') {
                    $guardianids[] = $slot['cardid'];
                } else {
                    $loreids[] = $slot['cardid'];
                }
            }
        }

        $guardians = $guardianids !== []
            ? $DB->get_records_list('playercards_guardians', 'id', array_unique($guardianids))
            : [];
        $lorecards = $loreids !== []
            ? $DB->get_records_list('playercards_lore', 'id', array_unique($loreids))
            : [];

        $emptyslot = [
            'occupied' => false,
            'uid' => '',
            'cardtype' => '',
            'cardid' => 0,
            'name' => '',
            'level' => 0,
            'atk' => 0,
            'def' => 0,
            'subtype' => '',
            'posture' => '',
            'facedown' => false,
            'sick' => false,
            'attackedthisturn' => false,
        ];

        $result = [];
        foreach ($zones as $zonename => $slots) {
            $hydrated = [];
            foreach ($slots as $slot) {
                if ($slot === null) {
                    $hydrated[] = $emptyslot;
                    continue;
                }

                $facedown = (bool) ($slot['facedown'] ?? false);

                if ($slot['cardtype'] === 'guardian') {
                    $card = $guardians[$slot['cardid']] ?? null;
                    $hydrated[] = array_merge($emptyslot, [
                        'occupied' => true,
                        'uid' => $slot['uid'],
                        'cardtype' => 'guardian',
                        'cardid' => (int) $slot['cardid'],
                        'name' => $card !== null ? format_string($card->name) : '',
                        'level' => $card !== null ? (int) $card->level : 0,
                        'atk' => $card !== null ? (int) $card->atk : 0,
                        'def' => $card !== null ? (int) $card->def : 0,
                        'posture' => $slot['posture'] ?? '',
                        'sick' => (bool) ($slot['sick'] ?? false),
                        'attackedthisturn' => (bool) ($slot['attackedthisturn'] ?? false),
                    ]);
                } else {
                    $card = $lorecards[$slot['cardid']] ?? null;
                    $hydrated[] = array_merge($emptyslot, [
                        'occupied' => true,
                        'uid' => $slot['uid'],
                        'cardtype' => 'lore',
                        'cardid' => (int) $slot['cardid'],
                        'name' => $facedown || $card === null ? '' : format_string($card->name),
                        'subtype' => $facedown || $card === null ? '' : $card->subtype,
                        'facedown' => $facedown,
                    ]);
                }
            }
            $result[$zonename] = $hydrated;
        }

        return $result;
    }
}
